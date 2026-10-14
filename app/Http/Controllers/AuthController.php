<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Services\TokenService;

class AuthController extends Controller
{
    /**
     * POST /auth/login - Autenticación con usuario y clave.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $username = strtolower(trim($request->input('username')));
        $password = $request->input('password');

        $user = User::where('username', $username)->first();

        // CA-01.2: Con credenciales inválidas responde 401 con mensaje opaco
        if (!$user || !Hash::check($password, $user->password_hash)) {
            return response()->json([
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'Credenciales inválidas.',
                'details' => [],
            ], 401);
        }

        $tokenData = TokenService::generateToken($user);

        return response()->json($tokenData, 200);
    }
}
