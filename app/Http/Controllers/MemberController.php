<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\Member;

class MemberController extends Controller
{
    /**
     * GET /members - Listar y buscar socios (admin y librarian).
     */
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $size = min(50, max(1, (int) $request->input('size', 20)));

        $query = Member::query();

        if ($request->filled('q')) {
            $q = trim($request->input('q'));
            $query->where(function ($m) use ($q) {
                $m->where('full_name', 'ilike', "%{$q}%")
                  ->orWhere('document_number', 'ilike', "%{$q}%");
            });
        }

        $total = $query->count();
        $members = $query->orderBy('full_name', 'asc')
                         ->skip(($page - 1) * $size)
                         ->take($size)
                         ->get();

        $items = $members->map(function ($member) {
            return [
                'id' => $member->id,
                'full_name' => $member->full_name,
                'document_number' => $member->document_number,
                'active_loans' => $member->active_loans_count,
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
     * GET /members/{id} - Detalle de socio con préstamos activos.
     */
    public function show(string $id): JsonResponse
    {
        $member = Member::find($id);

        if (!$member) {
            return response()->json([
                'code' => 'NOT_FOUND',
                'message' => 'Socio no encontrado.',
                'details' => [],
            ], 404);
        }

        return response()->json([
            'id' => $member->id,
            'full_name' => $member->full_name,
            'document_number' => $member->document_number,
            'active_loans' => $member->active_loans_count,
        ], 200);
    }

    /**
     * POST /members - Crear socio (admin y librarian).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'full_name' => 'required|string|max:200',
            'document_number' => 'required|string|max:30',
        ]);

        $docNumber = trim($request->input('document_number'));

        // CA-05.1: El documento es único: repetido 409 DOCUMENT_TAKEN
        if (Member::where('document_number', $docNumber)->exists()) {
            return response()->json([
                'code' => 'DOCUMENT_TAKEN',
                'message' => "El número de documento «{$docNumber}» ya se encuentra registrado.",
                'details' => [['field' => 'document_number', 'issue' => 'documento duplicado']],
            ], 409);
        }

        $member = Member::create([
            'id' => Str::uuid()->toString(),
            'full_name' => trim($request->input('full_name')),
            'document_number' => $docNumber,
        ]);

        return response()->json([
            'id' => $member->id,
            'full_name' => $member->full_name,
            'document_number' => $member->document_number,
            'active_loans' => 0,
        ], 201);
    }
}
