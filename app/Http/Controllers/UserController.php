<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;

class UserController extends Controller
{
    /**
     * POST /users - Alta de bibliotecarios (solo admin).
     * CA-02.1 / CA-02.2 / CA-02.3
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string|min:6',
            'role' => 'required|string',
        ]);

        $role = trim($request->input('role'));

        // CA-02.3: Pedir el rol admin por la API responde 422
        if ($role !== 'librarian') {
            return response()->json([
                'code' => 'VALIDATION_ERROR',
                'message' => 'Solo se permite dar de alta usuarios con rol librarian.',
                'details' => [
                    ['field' => 'role', 'issue' => 'El rol admin no puede ser otorgado por la API']
                ],
            ], 422);
        }

        // CA-02.2: Se guarda en minúsculas y recortado; es único. Repetido: 409
        $username = strtolower(trim($request->input('username')));

        if (User::where('username', $username)->exists()) {
            return response()->json([
                'code' => 'USERNAME_TAKEN',
                'message' => "El nombre de usuario «{$username}» ya está en uso.",
                'details' => [
                    ['field' => 'username', 'issue' => 'nombre de usuario repetido']
                ],
            ], 409);
        }

        $user = User::create([
            'id' => Str::uuid()->toString(),
            'username' => $username,
            'password_hash' => Hash::make($request->input('password')),
            'role' => 'librarian',
        ]);

        // 201 sin password ni password_hash
        return response()->json([
            'id' => $user->id,
            'username' => $user->username,
            'role' => $user->role,
        ], 201);
    }
}
