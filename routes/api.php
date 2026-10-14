<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\ReportController;

/*
|--------------------------------------------------------------------------
| API Routes - Manager Library (Spec v1)
|--------------------------------------------------------------------------
| Prefix: /api/v1 (configurado en bootstrap/app.php)
*/

// Endpoints públicos
Route::get('/health', [HealthController::class, 'index']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Endpoints autenticados (admin y librarian)
Route::middleware(['auth.bearer'])->group(function () {
    // Categorías
    Route::get('/categories', [CategoryController::class, 'index']);

    // Libros (consulta y portada)
    Route::get('/books', [BookController::class, 'index']);
    Route::get('/books/{id}', [BookController::class, 'show']);
    Route::get('/books/{id}/cover', [BookController::class, 'getCover']);

    // Socios
    Route::get('/members', [MemberController::class, 'index']);
    Route::get('/members/{id}', [MemberController::class, 'show']);
    Route::post('/members', [MemberController::class, 'store']);

    // Préstamos y Devoluciones
    Route::post('/loans', [LoanController::class, 'store']);
    Route::get('/loans', [LoanController::class, 'index']);
    Route::get('/loans/overdue', [LoanController::class, 'overdue']);
    Route::get('/loans/{id}', [LoanController::class, 'show']);
    Route::post('/loans/{loan_id}/items/{item_id}/return', [LoanController::class, 'returnItem']);

    // Endpoints exclusivos de administrador (role:admin)
    Route::middleware(['role:admin'])->group(function () {
        // Gestión de usuarios
        Route::post('/users', [UserController::class, 'store']);

        // Gestión del catálogo de libros
        Route::post('/books', [BookController::class, 'store']);
        Route::put('/books/{id}', [BookController::class, 'update']);
        Route::delete('/books/{id}', [BookController::class, 'destroy']);
        Route::put('/books/{id}/cover', [BookController::class, 'uploadCover']);
        Route::delete('/books/{id}/cover', [BookController::class, 'deleteCover']);

        // Reportes
        Route::get('/reports/most-borrowed', [ReportController::class, 'mostBorrowed']);
    });
});
