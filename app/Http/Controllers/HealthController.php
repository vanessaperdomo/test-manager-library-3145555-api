<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * GET /health - Endpoint público de comprobación de salud.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
        ]);
    }
}
