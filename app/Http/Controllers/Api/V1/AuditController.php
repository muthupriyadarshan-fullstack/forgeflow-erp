<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id ?? 1;

        $query = AuditLog::where('company_id', $companyId)->with('user');

        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        $logs = $query->orderByDesc('id')->paginate($request->input('per_page', 30));

        return response()->json($logs);
    }
}

