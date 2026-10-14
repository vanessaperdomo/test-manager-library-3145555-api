<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\TokenService;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateBearer
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'Token de autenticación no proporcionado.',
                'details' => [],
            ], 401);
        }

        $token = substr($authHeader, 7);
        $user = TokenService::verifyToken($token);

        if (!$user) {
            return response()->json([
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'Token inválido o expirado.',
                'details' => [],
            ], 401);
        }

        // Asignar el usuario autenticado a la petición
        $request->setUserResolver(fn() => $user);
        $request->attributes->set('auth_user', $user);

        return $next($request);
    }
}
