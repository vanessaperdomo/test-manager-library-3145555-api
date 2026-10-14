<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Models\Book;
use App\Models\Category;
use App\Models\LoanItem;

class BookController extends Controller
{
    /**
     * GET /books - Búsqueda de libros paginada y filtrada.
     */
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $size = min(50, max(1, (int) $request->input('size', 20)));

        $query = Book::with('category:id,name')->whereNull('deleted_at');

        if ($request->filled('q')) {
            $q = trim($request->input('q'));
            $query->where(function ($b) use ($q) {
                $b->where('title', 'ilike', "%{$q}%")
                  ->orWhere('author', 'ilike', "%{$q}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        $total = $query->count();
        $books = $query->orderBy('title', 'asc')
                      ->skip(($page - 1) * $size)
                      ->take($size)
                      ->get();

        $items = $books->map(function ($book) {
            return [
                'id' => $book->id,
                'title' => $book->title,
                'author' => $book->author,
                'isbn' => trim($book->isbn),
                'category' => [
                    'id' => $book->category->id,
                    'name' => $book->category->name,
                ],
                'total_copies' => (int) $book->total_copies,
                'available_copies' => (int) $book->available_copies,
                'cover_key' => $book->cover_key,
            ];
        });

        return response()->json([
            'items' => $items,
            'page' => $page,
            'size' => $size,
            'total' => $total,
        ], 200);
    }

    /**
     * GET /books/{id} - Detalle de un libro.
     */
    public function show(string $id): JsonResponse
    {
        $book = Book::with('category:id,name')
                    ->where('id', $id)
                    ->whereNull('deleted_at')
                    ->first();

        if (!$book) {
            return response()->json([
                'code' => 'NOT_FOUND',
                'message' => 'Libro no encontrado o dado de baja.',
                'details' => [],
            ], 404);
        }

        return response()->json([
            'id' => $book->id,
            'title' => $book->title,
            'author' => $book->author,
            'isbn' => trim($book->isbn),
            'category' => [
                'id' => $book->category->id,
                'name' => $book->category->name,
            ],
            'total_copies' => (int) $book->total_copies,
            'available_copies' => (int) $book->available_copies,
            'cover_key' => $book->cover_key,
        ], 200);
    }

    /**
     * POST /books - Crear libro (solo admin).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:250',
            'author' => 'required|string|max:200',
            'isbn' => 'required|string|size:13',
            'category_id' => ['required', 'uuid', \Illuminate\Validation\Rule::exists(Category::class, 'id')],
            'total_copies' => 'required|integer|min:1',
        ]);

        $isbn = trim($request->input('isbn'));

        // CA-03.2: ISBN único entre activos
        if (Book::where('isbn', $isbn)->whereNull('deleted_at')->exists()) {
            return response()->json([
                'code' => 'ISBN_TAKEN',
                'message' => "El ISBN «{$isbn}» ya está registrado en un libro activo.",
                'details' => [['field' => 'isbn', 'issue' => 'ISBN ya en uso']],
            ], 409);
        }

        $totalCopies = (int) $request->input('total_copies');

        $book = Book::create([
            'id' => Str::uuid()->toString(),
            'title' => trim($request->input('title')),
            'author' => trim($request->input('author')),
            'isbn' => $isbn,
            'category_id' => $request->input('category_id'),
            'total_copies' => $totalCopies,
            'available_copies' => $totalCopies, // CA-03.1: disponibles arrancan iguales al total
            'cover_key' => null,
            'deleted_at' => null,
        ]);

        $book->load('category:id,name');

        return response()->json([
            'id' => $book->id,
            'title' => $book->title,
            'author' => $book->author,
            'isbn' => trim($book->isbn),
            'category' => [
                'id' => $book->category->id,
                'name' => $book->category->name,
            ],
            'total_copies' => (int) $book->total_copies,
            'available_copies' => (int) $book->available_copies,
            'cover_key' => $book->cover_key,
        ], 201);
    }

    /**
     * PUT /books/{id} - Actualizar libro (solo admin).
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $book = Book::where('id', $id)->whereNull('deleted_at')->first();

        if (!$book) {
            return response()->json([
                'code' => 'NOT_FOUND',
                'message' => 'Libro no encontrado.',
                'details' => [],
            ], 404);
        }

        $request->validate([
            'title' => 'required|string|max:250',
            'author' => 'required|string|max:200',
            'category_id' => ['required', 'uuid', \Illuminate\Validation\Rule::exists(Category::class, 'id')],
            'total_copies' => 'required|integer|min:1',
        ]);

        $newTotal = (int) $request->input('total_copies');
        $diff = $newTotal - $book->total_copies;
        $newAvailable = $book->available_copies + $diff;

        // CA-03.3: nunca deja disponibles < 0 (422)
        if ($newAvailable < 0) {
            return response()->json([
                'code' => 'VALIDATION_ERROR',
                'message' => 'El nuevo total dejaría los ejemplares disponibles en negativo.',
                'details' => [['field' => 'total_copies', 'issue' => 'disponibles quedarían < 0']],
            ], 422);
        }

        $book->title = trim($request->input('title'));
        $book->author = trim($request->input('author'));
        $book->category_id = $request->input('category_id');
        $book->total_copies = $newTotal;
        $book->available_copies = $newAvailable;
        $book->save();

        $book->load('category:id,name');

        return response()->json([
            'id' => $book->id,
            'title' => $book->title,
            'author' => $book->author,
            'isbn' => trim($book->isbn),
            'category' => [
                'id' => $book->category->id,
                'name' => $book->category->name,
            ],
            'total_copies' => (int) $book->total_copies,
            'available_copies' => (int) $book->available_copies,
            'cover_key' => $book->cover_key,
        ], 200);
    }

    /**
     * DELETE /books/{id} - Baja lógica de libro (solo admin).
     */
    public function destroy(string $id): JsonResponse
    {
        $book = Book::where('id', $id)->whereNull('deleted_at')->first();

        if (!$book) {
            return response()->json([
                'code' => 'NOT_FOUND',
                'message' => 'Libro no encontrado.',
                'details' => [],
            ], 404);
        }

        // CA-03.5: Un libro con préstamos abiertos no se da de baja (409)
        $hasOpenLoans = LoanItem::where('book_id', $id)->whereNull('returned_at')->exists();
        if ($hasOpenLoans) {
            return response()->json([
                'code' => 'BOOK_HAS_OPEN_LOANS',
                'message' => 'El libro cuenta con préstamos abiertos activos y no puede ser dado de baja.',
                'details' => [],
            ], 409);
        }

        $book->deleted_at = now();
        $book->save();

        return response()->json(null, 204);
    }

    /**
     * PUT /books/{id}/cover - Subir portada de libro (solo admin).
     */
    public function uploadCover(Request $request, string $id): JsonResponse
    {
        $book = Book::where('id', $id)->whereNull('deleted_at')->first();

        if (!$book) {
            return response()->json([
                'code' => 'NOT_FOUND',
                'message' => 'Libro no encontrado.',
                'details' => [],
            ], 404);
        }

        $request->validate([
            'file' => 'required|file|mimes:jpeg,jpg,png|max:2048',
        ]);

        $file = $request->file('file');
        $ext = $file->getClientOriginalExtension();
        $coverKey = Str::uuid()->toString() . '.' . $ext;

        // Guardar archivo en disco local
        Storage::disk('local')->putFileAs('covers', $file, $coverKey);

        $book->cover_key = $coverKey;
        $book->save();

        return response()->json([
            'cover_key' => $coverKey,
        ], 200);
    }

    /**
     * GET /books/{id}/cover - Descargar binario de portada.
     */
    public function getCover(string $id)
    {
        $book = Book::where('id', $id)->whereNull('deleted_at')->first();

        if (!$book || !$book->cover_key || !Storage::disk('local')->exists('covers/' . $book->cover_key)) {
            return response()->json([
                'code' => 'NOT_FOUND',
                'message' => 'Portada no encontrada.',
                'details' => [],
            ], 404);
        }

        return Storage::disk('local')->response('covers/' . $book->cover_key);
    }

    /**
     * DELETE /books/{id}/cover - Eliminar portada (solo admin).
     */
    public function deleteCover(string $id): JsonResponse
    {
        $book = Book::where('id', $id)->whereNull('deleted_at')->first();

        if (!$book) {
            return response()->json([
                'code' => 'NOT_FOUND',
                'message' => 'Libro no encontrado.',
                'details' => [],
            ], 404);
        }

        if ($book->cover_key) {
            $oldKey = $book->cover_key;
            $book->cover_key = null;
            $book->save();

            Storage::disk('local')->delete('covers/' . $oldKey);
        }

        return response()->json(null, 204);
    }
}
