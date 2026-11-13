<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Machine;
use App\Models\ProductionOrder;
use App\Services\Production\ProductionService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionController extends Controller
{
    public function __construct(protected ProductionService $productionService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id ?? 1;

        $query = ProductionOrder::where('company_id', $companyId)
            ->with(['product.unit', 'targetWarehouse', 'assignedTo', 'createdBy']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        $orders = $query->orderByDesc('id')->paginate($request->input('per_page', 20));

        return response()->json($orders);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id ?? 1;
        $userId = $request->user()->id;

        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'bill_of_materials_id' => ['required', 'exists:bills_of_materials,id'],
            'target_warehouse_id' => ['required', 'exists:warehouses,id'],
            'planned_quantity' => ['required', 'numeric', 'min:0.0001'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['required', 'date'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'assigned_to_user_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['company_id'] = $companyId;
        $validated['created_by_user_id'] = $userId;

        try {
            $order = $this->productionService->createOrder($validated);

            return response()->json([
                'message' => 'Production order created successfully',
                'production_order' => $order->load(['product.unit', 'billOfMaterial', 'stages']),
            ], 201);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(ProductionOrder $order): JsonResponse
    {
        $order->load([
            'product.unit',
            'billOfMaterial.items.componentProduct.unit',
            'targetWarehouse',
            'assignedTo',
            'createdBy',
            'stages.machine',
            'stages.assignedEmployee',
            'reservations.product.unit',
            'issues.product.unit',
            'outputs.product.unit',
        ]);

        return response()->json($order);
    }

    public function reserve(ProductionOrder $order, Request $request): JsonResponse
    {
        try {
            $whId = $request->filled('warehouse_id') ? (int) $request->warehouse_id : null;
            $reservations = $this->productionService->reserveMaterials($order, $whId);

            return response()->json([
                'message' => 'Raw materials reserved for production',
                'order' => $order->fresh()->load('reservations.product'),
                'reservations' => $reservations,
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function issue(ProductionOrder $order, Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        try {
            $issues = $this->productionService->issueMaterials($order, $userId);

            return response()->json([
                'message' => 'Materials issued to production shop-floor and inventory updated',
                'order' => $order->fresh()->load('issues.product'),
                'issues' => $issues,
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function complete(ProductionOrder $order, Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'quantity_produced' => ['required', 'numeric', 'min:0.0001'],
            'quantity_rejected' => ['nullable', 'numeric', 'min:0'],
            'quantity_scrapped' => ['nullable', 'numeric', 'min:0'],
            'labour_cost' => ['nullable', 'numeric', 'min:0'],
            'machine_cost' => ['nullable', 'numeric', 'min:0'],
            'batch_number' => ['nullable', 'string'],
        ]);

        try {
            $output = $this->productionService->completeProduction(
                order: $order,
                quantityProduced: (float) $validated['quantity_produced'],
                quantityRejected: (float) ($validated['quantity_rejected'] ?? 0),
                quantityScrapped: (float) ($validated['quantity_scrapped'] ?? 0),
                labourCost: (float) ($validated['labour_cost'] ?? 0),
                machineCost: (float) ($validated['machine_cost'] ?? 0),
                batchNumber: $validated['batch_number'] ?? null,
                userId: $userId
            );

            return response()->json([
                'message' => 'Production completed and finished goods added to inventory',
                'output' => $output->load('product'),
                'order' => $order->fresh()->load('stages'),
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function machines(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id ?? 1;
        $machines = Machine::where('company_id', $companyId)->orderBy('name')->get();

        return response()->json($machines);
    }

    public function employees(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id ?? 1;
        $employees = Employee::where('company_id', $companyId)->orderBy('first_name')->get();

        return response()->json($employees);
    }
}

