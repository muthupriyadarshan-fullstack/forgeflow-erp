<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Finance\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(protected ReportService $reportService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id ?? 1;
        $summary = $this->reportService->getDashboardSummary($companyId);

        return response()->json($summary);
    }
}

