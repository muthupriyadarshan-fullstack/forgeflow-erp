<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\InventoryBalance;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Inventory\StockService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(protected StockService $stockService)
    {
    }

    public function balances(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id ?? 1;

        $query = InventoryBalance::where('inventory_balances.company_id', $companyId)
            ->with(['product.unit', 'product.category', 'warehouse', 'location']);

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('type')) {
            $query->whereHas('product', fn ($q) => $q->where('type', $request->type));
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->whereHas('product', function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('sku', 'like', "%{$s}%");
            });
        }

        $balances = $query->orderByDesc('quantity_on_hand')->paginate($request->input('per_page', 25));

        return response()->json($balances);
    }

    public function movements(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id ?? 1;

        $query = StockMovement::where('company_id', $companyId)
            ->with(['product.unit', 'sourceWarehouse', 'sourceLocation', 'destWarehouse', 'destLocation', 'creator']);

        if ($request->filled('movement_type')) {
            $query->where('movement_type', $request->movement_type);
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('warehouse_id')) {
            $whId = $request->warehouse_id;
            $query->where(function ($q) use ($whId) {
                $q->where('source_warehouse_id', $whId)
                    ->orWhere('dest_warehouse_id', $whId);
            });
        }

        $movements = $query->orderByDesc('id')->paginate($request->input('per_page', 30));

        return response()->json($movements);
    }

    public function transfer(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id ?? 1;
        $userId = $request->user()->id;

        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'source_warehouse_id' => ['required', 'exists:warehouses,id'],
            'dest_warehouse_id' => ['required', 'exists:warehouses,id', 'different:source_warehouse_id'],
            'source_location_id' => ['nullable', 'exists:warehouse_locations,id'],
            'dest_location_id' => ['nullable', 'exists:warehouse_locations,id'],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'unit_id' => ['required', 'exists:units,id'],
            'batch_number' => ['nullable', 'string'],
            'reason' => ['nullable', 'string'],
        ]);

        try {
            $movement = $this->stockService->transferStock(
                companyId: $companyId,
                productId: $validated['product_id'],
                sourceWarehouseId: $validated['source_warehouse_id'],
                destWarehouseId: $validated['dest_warehouse_id'],
                quantity: (float) $validated['quantity'],
                unitId: $validated['unit_id'],
                sourceLocationId: $validated['source_location_id'] ?? null,
                destLocationId: $validated['dest_location_id'] ?? null,
                batchNumber: $validated['batch_number'] ?? null,
                reason: $validated['reason'] ?? 'Internal stock transfer',
                userId: $userId
            );

            return response()->json([
                'message' => 'Stock transferred successfully',
                'movement' => $movement->load(['product.unit', 'sourceWarehouse', 'destWarehouse']),
            ], 201);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function adjust(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id ?? 1;
        $userId = $request->user()->id;

        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'location_id' => ['nullable', 'exists:warehouse_locations,id'],
            'quantity' => ['required', 'numeric'], // Can be positive (addition) or negative (deduction)
            'unit_id' => ['required', 'exists:units,id'],
            'batch_number' => ['nullable', 'string'],
            'reason' => ['required', 'string', 'min:3'],
        ]);

        try {
            $movement = $this->stockService->adjustStock(
                companyId: $companyId,
                productId: $validated['product_id'],
                warehouseId: $validated['warehouse_id'],
                adjustedQuantity: (float) $validated['quantity'],
                unitId: $validated['unit_id'],
                locationId: $validated['location_id'] ?? null,
                batchNumber: $validated['batch_number'] ?? null,
                reason: $validated['reason'],
                userId: $userId
            );

            return response()->json([
                'message' => 'Stock adjusted successfully',
                'movement' => $movement->load(['product.unit', 'sourceWarehouse', 'destWarehouse']),
            ], 201);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function warehouses(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id ?? 1;
        $warehouses = Warehouse::where('company_id', $companyId)
            ->with(['locations', 'manager'])
            ->withCount('balances')
            ->orderBy('name')
            ->get();

        return response()->json($warehouses);
    }
}

