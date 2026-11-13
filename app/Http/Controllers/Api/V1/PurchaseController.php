<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\Supplier;
use App\Services\Audit\AuditService;
use App\Services\Purchase\PurchaseService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function __construct(protected PurchaseService $purchaseService)
    {
    }

    public function indexOrders(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id ?? 1;

        $query = PurchaseOrder::where('company_id', $companyId)
            ->with(['supplier', 'warehouse', 'createdBy', 'approvedBy', 'items.product.unit']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        $orders = $query->orderByDesc('id')->paginate($request->input('per_page', 20));

        return response()->json($orders);
    }

    public function storeOrder(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id ?? 1;
        $userId = $request->user()->id;

        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'order_date' => ['nullable', 'date'],
            'expected_delivery_date' => ['required', 'date'],
            'shipping_amount' => ['nullable', 'numeric', 'min:0'],
            'terms_and_conditions' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.unit_id' => ['required', 'exists:units,id'],
            'items.*.quantity_ordered' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.tax_percentage' => ['nullable', 'numeric', 'min:0'],
            'items.*.hsn_code' => ['nullable', 'string'],
        ]);

        $data = [
            'company_id' => $companyId,
            'supplier_id' => $validated['supplier_id'],
            'warehouse_id' => $validated['warehouse_id'],
            'order_date' => $validated['order_date'] ?? now()->toDateString(),
            'expected_delivery_date' => $validated['expected_delivery_date'],
            'shipping_amount' => $validated['shipping_amount'] ?? 0,
            'terms_and_conditions' => $validated['terms_and_conditions'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'created_by_user_id' => $userId,
        ];

        $po = $this->purchaseService->createPurchaseOrder($data, $validated['items']);

        return response()->json([
            'message' => 'Purchase order created successfully and submitted for approval',
            'purchase_order' => $po->load(['supplier', 'warehouse', 'items.product']),
        ], 201);
    }

    public function showOrder(PurchaseOrder $order): JsonResponse
    {
        $order->load([
            'supplier.contacts',
            'warehouse',
            'createdBy',
            'approvedBy',
            'items.product.unit',
            'goodsReceipts.items.product',
            'invoices',
        ]);

        return response()->json($order);
    }

    public function approveOrder(PurchaseOrder $order, Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $validated = $request->validate([
            'approved' => ['required', 'boolean'],
            'comments' => ['nullable', 'string'],
        ]);

        try {
            $updated = $this->purchaseService->approvePurchaseOrder(
                $order,
                $userId,
                $validated['approved'],
                $validated['comments'] ?? null
            );

            return response()->json([
                'message' => $validated['approved'] ? 'Purchase order approved' : 'Purchase order rejected',
                'purchase_order' => $updated,
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function processGrn(PurchaseOrder $order, Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'received_date' => ['nullable', 'date'],
            'delivery_challan_number' => ['nullable', 'string'],
            'transporter_name' => ['nullable', 'string'],
            'vehicle_number' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'exists:purchase_order_items,id'],
            'items.*.warehouse_location_id' => ['nullable', 'exists:warehouse_locations,id'],
            'items.*.quantity_received' => ['required', 'numeric', 'min:0.0001'],
            'items.*.quantity_accepted' => ['required', 'numeric', 'min:0'],
            'items.*.quantity_rejected' => ['nullable', 'numeric', 'min:0'],
            'items.*.rejection_reason' => ['nullable', 'string'],
            'items.*.batch_number' => ['nullable', 'string'],
            'items.*.lot_number' => ['nullable', 'string'],
            'items.*.expiry_date' => ['nullable', 'date'],
        ]);

        try {
            $grn = $this->purchaseService->processGoodsReceipt($order, $validated, $validated['items'], $userId);

            return response()->json([
                'message' => 'Goods Receipt Note processed and inventory updated',
                'goods_receipt' => $grn->load(['items.product.unit', 'supplier', 'warehouse']),
            ], 201);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function suppliers(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id ?? 1;
        $suppliers = Supplier::where('company_id', $companyId)
            ->with('contacts')
            ->withCount('purchaseOrders')
            ->orderBy('name')
            ->get();

        return response()->json($suppliers);
    }

    public function storeSupplier(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id ?? 1;

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'gstin' => ['nullable', 'string', 'max:15'],
            'pan' => ['nullable', 'string', 'max:10'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string'],
            'state' => ['nullable', 'string'],
            'postal_code' => ['nullable', 'string'],
            'payment_terms_days' => ['integer', 'min:0'],
            'credit_limit' => ['numeric', 'min:0'],
        ]);

        $validated['company_id'] = $companyId;
        $supplier = Supplier::create($validated);
        AuditService::log('supplier_created', $supplier, null, $supplier->toArray());

        return response()->json([
            'message' => 'Supplier registered successfully',
            'supplier' => $supplier,
        ], 201);
    }

    public function requisitions(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id ?? 1;
        $prs = PurchaseRequisition::where('company_id', $companyId)
            ->with(['requestedBy', 'approvedBy'])
            ->orderByDesc('id')
            ->paginate($request->input('per_page', 20));

        return response()->json($prs);
    }
}

