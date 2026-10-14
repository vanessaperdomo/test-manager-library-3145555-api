<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Http\Middleware\AuthenticateBearer;
use App\Http\Middleware\EnsureRole;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'auth.bearer' => AuthenticateBearer::class,
            'role' => EnsureRole::class,
        ]);
        // Permitir CORS para peticiones desde el frontend
        $middleware->validateCsrfTokens(except: ['*']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ValidationException $e, $request) {
            $details = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $msg) {
                    $details[] = ['field' => $field, 'issue' => $msg];
                }
            }
            return response()->json([
                'code' => 'VALIDATION_ERROR',
                'message' => 'Los datos suministrados no son válidos.',
                'details' => $details,
            ], 422);
        });

        $exceptions->render(function (NotFoundHttpException $e, $request) {
            return response()->json([
                'code' => 'NOT_FOUND',
                'message' => 'El recurso solicitado no existe.',
                'details' => [],
            ], 404);
        });

        $exceptions->render(function (HttpException $e, $request) {
            return response()->json([
                'code' => 'HTTP_ERROR',
                'message' => $e->getMessage() ?: 'Error en la petición.',
                'details' => [],
            ], $e->getStatusCode());
        });
    })->create();
