<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user() ?: $request->attributes->get('auth_user');

        if (!$user || !in_array($user->role, $roles, true)) {
            return response()->json([
                'code' => 'FORBIDDEN_ROLE',
                'message' => 'No tiene permisos suficientes para realizar esta acción.',
                'details' => [],
            ], 403);
        }

        return $next($request);
    }
}
