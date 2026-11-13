<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Finance\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reportService)
    {
    }

    public function valuation(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id ?? 1;
        $report = $this->reportService->getInventoryValuationReport($companyId);

        return response()->json($report);
    }

    public function exportCsv(Request $request): Response
    {
        $companyId = $request->user()->company_id ?? 1;
        $type = $request->input('type', 'stock_ledger');

        $csv = $this->reportService->generateCsvExport($type, $companyId);
        $filename = "forgeflow_{$type}_" . date('Ymd_His') . ".csv";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}

