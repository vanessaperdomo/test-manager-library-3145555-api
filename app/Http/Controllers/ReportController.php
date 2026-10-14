<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * GET /reports/most-borrowed - Reporte de libros más prestados (solo admin).
     * CA-10.1 / CA-10.2 / CA-10.3 / CA-10.4
     */
    public function mostBorrowed(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date',
        ]);

        $fromDate = Carbon::parse($request->input('from'))->startOfDay();
        $toDate = Carbon::parse($request->input('to'))->endOfDay();

        // CA-10.2: Fin anterior al inicio: 422 INVALID_DATE_RANGE
        if ($fromDate->isAfter($toDate)) {
            return response()->json([
                'code' => 'INVALID_DATE_RANGE',
                'message' => 'El rango de fechas es inválido: la fecha final es anterior a la fecha inicial.',
                'details' => [
                    ['field' => 'to', 'issue' => 'debe ser posterior o igual a from']
                ],
            ], 422);
        }

        // Consultar agregando por datos congelados sin exponer datos personales (ADR-004)
        $rows = DB::table('library.loan_item as li')
            ->join('library.loan as l', 'li.loan_id', '=', 'l.id')
            ->where('l.loaned_at', '>=', $fromDate)
            ->where('l.loaned_at', '<=', $toDate)
            ->select(
                'li.book_id',
                'li.book_title',
                'li.category_name',
                DB::raw('COUNT(*) as times_borrowed')
            )
            ->groupBy('li.book_id', 'li.book_title', 'li.category_name')
            ->orderByDesc('times_borrowed')
            ->orderBy('li.book_title', 'asc')
            ->get();

        $formattedRows = $rows->map(function ($row) {
            return [
                'book_id' => $row->book_id,
                'book_title' => $row->book_title,
                'category_name' => $row->category_name,
                'times_borrowed' => (int) $row->times_borrowed,
            ];
        });

        return response()->json([
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'rows' => $formattedRows,
        ], 200);
    }
}
