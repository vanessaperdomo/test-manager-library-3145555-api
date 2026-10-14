<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use App\Models\Category;

class CategoryController extends Controller
{
    /**
     * GET /categories - Listar las cinco categorías, orden por nombre.
     */
    public function index(): JsonResponse
    {
        $categories = Category::orderBy('name', 'asc')->get(['id', 'name']);

        return response()->json($categories, 200);
    }
}
