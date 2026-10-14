<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Member;
use App\Models\Book;

class LoanController extends Controller
{
    /**
     * POST /loans - Registrar un préstamo transaccional.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'member_id' => 'required|uuid',
            'items' => 'required|array|min:1|max:3',
            'items.*.book_id' => 'required|uuid',
        ]);

        $memberId = $request->input('member_id');
        $itemsData = $request->input('items');

        // CA-06.1: de 1 a 3 líneas
        if (count($itemsData) < 1 || count($itemsData) > 3) {
            return response()->json([
                'code' => 'MEMBER_LIMIT_EXCEEDED',
                'message' => 'Un préstamo debe contener entre 1 y 3 libros.',
                'details' => [],
            ], 422);
        }

        // CA-06.2: No se repite el mismo libro en un préstamo (422 DUPLICATE_BOOK_IN_LOAN)
        $bookIds = array_column($itemsData, 'book_id');
        if (count($bookIds) !== count(array_unique($bookIds))) {
            return response()->json([
                'code' => 'DUPLICATE_BOOK_IN_LOAN',
                'message' => 'No se permite incluir el mismo libro varias veces en el mismo préstamo.',
                'details' => [],
            ], 422);
        }

        // Verificar existencia del socio
        $member = Member::find($memberId);
        if (!$member) {
            return response()->json([
                'code' => 'NOT_FOUND',
                'message' => 'Socio no encontrado.',
                'details' => [],
            ], 404);
        }

        // CA-06.5: Un socio con algún libro vencido no puede pedir otro (409 MEMBER_HAS_OVERDUE)
        $hasOverdue = LoanItem::whereHas('loan', function ($q) use ($memberId) {
            $q->where('member_id', $memberId);
        })->whereNull('returned_at')
          ->where('due_at', '<', Carbon::now())
          ->exists();

        if ($hasOverdue) {
            return response()->json([
                'code' => 'MEMBER_HAS_OVERDUE',
                'message' => 'El socio presenta libros vencidos sin devolver y no puede realizar nuevos préstamos.',
                'details' => [],
            ], 409);
        }

        // CA-06.4: Un socio no puede tener más de 3 libros sin devolver en total (422)
        $currentUnreturned = $member->active_loans_count;
        if (($currentUnreturned + count($itemsData)) > 3) {
            return response()->json([
                'code' => 'MEMBER_LIMIT_EXCEEDED',
                'message' => "El socio superaría el límite de 3 libros sin devolver (actualmente tiene {$currentUnreturned}).",
                'details' => [],
            ], 422);
        }

        // Obtener usuario autenticado que registra el préstamo
        $user = $request->user() ?: $request->attributes->get('auth_user');

        try {
            $loan = DB::transaction(function () use ($memberId, $user, $bookIds) {
                $now = Carbon::now();
                $dueAt = (clone $now)->addDays(14); // CA-06.6: Vencimiento en 14 días

                // Bloquear libros concurrentemente con FOR UPDATE y verificar stock
                $books = Book::with('category')->whereIn('id', $bookIds)->lockForUpdate()->get()->keyBy('id');

                foreach ($bookIds as $bId) {
                    $book = $books->get($bId);
                    if (!$book || $book->deleted_at !== null) {
                        throw new \Exception("BOOK_NOT_FOUND:{$bId}");
                    }

                    // CA-06.3: Sin ejemplares disponibles -> 409 BOOK_NOT_AVAILABLE
                    if ($book->available_copies < 1) {
                        throw new \Exception("BOOK_NOT_AVAILABLE:{$book->title}");
                    }
                }

                // Crear cabecera del préstamo
                $loanId = Str::uuid()->toString();
                DB::statement('
                    INSERT INTO library.loan (id, member_id, loaned_by_user_id, loaned_at)
                    VALUES (?, ?, ?, ?);
                ', [$loanId, $memberId, $user->id, $now]);

                // Descontar disponible y crear líneas congeladas
                foreach ($bookIds as $bId) {
                    $book = $books->get($bId);

                    // Descontar 1 ejemplar
                    $book->available_copies -= 1;
                    $book->save();

                    // Insertar línea congelada
                    $itemId = Str::uuid()->toString();
                    DB::statement('
                        INSERT INTO library.loan_item (id, loan_id, book_id, book_title, isbn, category_name, due_at, returned_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NULL);
                    ', [
                        $itemId,
                        $loanId,
                        $book->id,
                        $book->title,
                        $book->isbn,
                        $book->category->name,
                        $dueAt,
                    ]);
                }

                return Loan::with('items')->find($loanId);
            });

            return response()->json([
                'id' => $loan->id,
                'member_id' => $loan->member_id,
                'loaned_at' => $loan->loaned_at->toISOString(),
                'status' => 'open',
                'items' => $loan->items->map(fn($item) => [
                    'id' => $item->id,
                    'book_id' => $item->book_id,
                    'book_title' => $item->book_title,
                    'isbn' => trim($item->isbn),
                    'category_name' => $item->category_name,
                    'due_at' => $item->due_at->toISOString(),
                    'returned_at' => null,
                ]),
            ], 201);

        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if (str_starts_with($msg, 'BOOK_NOT_AVAILABLE:')) {
                $bookTitle = substr($msg, 19);
                return response()->json([
                    'code' => 'BOOK_NOT_AVAILABLE',
                    'message' => "No quedan ejemplares disponibles de «{$bookTitle}».",
                    'details' => [],
                ], 409);
            }

            if (str_starts_with($msg, 'BOOK_NOT_FOUND:')) {
                return response()->json([
                    'code' => 'NOT_FOUND',
                    'message' => 'Uno de los libros solicitados no existe o está dado de baja.',
                    'details' => [],
                ], 404);
            }

            return response()->json([
                'code' => 'VALIDATION_ERROR',
                'message' => 'Error al procesar el préstamo.',
                'details' => [],
            ], 422);
        }
    }

    /**
     * GET /loans - Listar préstamos con filtros.
     */
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $size = min(50, max(1, (int) $request->input('size', 20)));

        $query = Loan::with(['items', 'member:id,full_name,document_number']);

        if ($request->filled('member_id')) {
            $query->where('member_id', $request->input('member_id'));
        }

        if ($request->filled('from')) {
            $query->where('loaned_at', '>=', Carbon::parse($request->input('from'))->startOfDay());
        }

        if ($request->filled('to')) {
            $query->where('loaned_at', '<=', Carbon::parse($request->input('to'))->endOfDay());
        }

        $loans = $query->orderBy('loaned_at', 'desc')->get();

        // Filtrado por status calculado si se especifica
        if ($request->filled('status')) {
            $statusFilter = $request->input('status');
            $loans = $loans->filter(fn($l) => $l->status === $statusFilter)->values();
        }

        $total = $loans->count();
        $paginated = $loans->slice(($page - 1) * $size, $size)->values();

        $items = $paginated->map(function ($loan) {
            return [
                'id' => $loan->id,
                'member_id' => $loan->member_id,
                'member_name' => $loan->member?->full_name,
                'loaned_at' => $loan->loaned_at->toISOString(),
                'status' => $loan->status,
                'items' => $loan->items->map(fn($item) => [
                    'id' => $item->id,
                    'book_id' => $item->book_id,
                    'book_title' => $item->book_title,
                    'isbn' => trim($item->isbn),
                    'category_name' => $item->category_name,
                    'due_at' => $item->due_at->toISOString(),
                    'returned_at' => $item->returned_at?->toISOString(),
                ]),
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
     * GET /loans/{id} - Detalle de préstamo.
     */
    public function show(string $id): JsonResponse
    {
        $loan = Loan::with(['items', 'member:id,full_name,document_number', 'user:id,username'])->find($id);

        if (!$loan) {
            return response()->json([
                'code' => 'NOT_FOUND',
                'message' => 'Préstamo no encontrado.',
                'details' => [],
            ], 404);
        }

        return response()->json([
            'id' => $loan->id,
            'member_id' => $loan->member_id,
            'member_name' => $loan->member?->full_name,
            'loaned_at' => $loan->loaned_at->toISOString(),
            'status' => $loan->status,
            'items' => $loan->items->map(fn($item) => [
                'id' => $item->id,
                'book_id' => $item->book_id,
                'book_title' => $item->book_title,
                'isbn' => trim($item->isbn),
                'category_name' => $item->category_name,
                'due_at' => $item->due_at->toISOString(),
                'returned_at' => $item->returned_at?->toISOString(),
            ]),
        ], 200);
    }

    /**
     * POST /loans/{loan_id}/items/{item_id}/return - Registrar devolución de una línea.
     */
    public function returnItem(string $loanId, string $itemId): JsonResponse
    {
        $item = LoanItem::where('id', $itemId)->where('loan_id', $loanId)->first();

        if (!$item) {
            return response()->json([
                'code' => 'NOT_FOUND',
                'message' => 'Línea de préstamo no encontrada.',
                'details' => [],
            ], 404);
        }

        // CA-07.2: Devolver una línea ya devuelta: 409 ITEM_ALREADY_RETURNED
        if ($item->returned_at !== null) {
            return response()->json([
                'code' => 'ITEM_ALREADY_RETURNED',
                'message' => 'Esta línea de préstamo ya fue devuelta previamente.',
                'details' => [],
            ], 409);
        }

        DB::transaction(function () use ($item) {
            $item->returned_at = Carbon::now();
            $item->save();

            // Sumar 1 a ejemplares disponibles del catálogo
            $book = Book::find($item->book_id);
            if ($book) {
                $book->available_copies += 1;
                $book->save();
            }
        });

        return response()->json([
            'id' => $item->id,
            'book_id' => $item->book_id,
            'book_title' => $item->book_title,
            'isbn' => trim($item->isbn),
            'category_name' => $item->category_name,
            'due_at' => $item->due_at->toISOString(),
            'returned_at' => $item->returned_at->toISOString(),
        ], 200);
    }

    /**
     * GET /loans/overdue - Líneas de préstamo vencidas (CA-09).
     */
    public function overdue(): JsonResponse
    {
        $now = Carbon::now();

        $overdueItems = LoanItem::with(['loan.member'])
            ->whereNull('returned_at')
            ->where('due_at', '<', $now)
            ->get();

        $items = $overdueItems->map(function ($item) use ($now) {
            $daysLate = (int) $item->due_at->diffInDays($now);
            return [
                'loan_id' => $item->loan_id,
                'item_id' => $item->id,
                'book_title' => $item->book_title,
                'member_name' => $item->loan?->member?->full_name ?? 'Desconocido',
                'due_at' => $item->due_at->toISOString(),
                'days_late' => max(1, $daysLate),
            ];
        })->sortByDesc('days_late')->values();

        return response()->json([
            'items' => $items,
        ], 200);
    }
}
