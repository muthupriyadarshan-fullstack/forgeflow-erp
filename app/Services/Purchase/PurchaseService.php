<?php

namespace App\Services\Purchase;

use App\Events\PurchaseOrderApprovedEvent;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequisition;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\Audit\AuditService;
use App\Services\Inventory\StockService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseService
{
    public function __construct(protected StockService $stockService)
    {
    }

    /**
     * Create a Purchase Order from items or requisition.
     */
    public function createPurchaseOrder(array $data, array $items): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $items) {
            $companyId = $data['company_id'];
            $poNumber = $data['po_number'] ?? ('PO-' . date('Ym') . '-' . strtoupper(Str::random(5)));

            $subtotal = 0;
            $totalTax = 0;

            foreach ($items as $item) {
                $qty = (float) $item['quantity_ordered'];
                $price = (float) $item['unit_price'];
                $taxPct = (float) ($item['tax_percentage'] ?? 18.00); // 18% Indian GST
                $lineSubtotal = $qty * $price;
                $lineTax = round($lineSubtotal * ($taxPct / 100), 2);
                $subtotal += $lineSubtotal;
                $totalTax += $lineTax;
            }

            $shipping = (float) ($data['shipping_amount'] ?? 0);
            $totalAmount = $subtotal + $totalTax + $shipping;

            $po = PurchaseOrder::create([
                'company_id' => $companyId,
                'po_number' => $poNumber,
                'purchase_requisition_id' => $data['purchase_requisition_id'] ?? null,
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? now()->addDays(14)->toDateString(),
                'subtotal' => round($subtotal, 2),
                'tax_amount' => round($totalTax, 2),
                'shipping_amount' => round($shipping, 2),
                'total_amount' => round($totalAmount, 2),
                'currency' => 'INR',
                'status' => 'pending_approval',
                'approval_status' => 'pending',
                'terms_and_conditions' => $data['terms_and_conditions'] ?? 'Standard Indian Commercial & Industrial Terms. GST applicable.',
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $data['created_by_user_id'],
            ]);

            foreach ($items as $item) {
                $qty = (float) $item['quantity_ordered'];
                $price = (float) $item['unit_price'];
                $taxPct = (float) ($item['tax_percentage'] ?? 18.00);
                $lineSubtotal = $qty * $price;
                $lineTax = round($lineSubtotal * ($taxPct / 100), 2);

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'] ?? null,
                    'quantity_ordered' => $qty,
                    'quantity_received' => 0,
                    'unit_id' => $item['unit_id'],
                    'unit_price' => $price,
                    'tax_percentage' => $taxPct,
                    'tax_amount' => $lineTax,
                    'line_total' => round($lineSubtotal + $lineTax, 2),
                    'hsn_code' => $item['hsn_code'] ?? null,
                ]);
            }

            AuditService::log('purchase_order_created', $po, null, $po->toArray());

            return $po;
        });
    }

    /**
     * Approve or reject a Purchase Order.
     */
    public function approvePurchaseOrder(PurchaseOrder $po, int $approverId, bool $approved, ?string $comments = null): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $approverId, $approved, $comments) {
            if ($po->approval_status !== 'pending') {
                throw new Exception("Purchase Order {$po->po_number} is already in '{$po->approval_status}' state.");
            }

            $newStatus = $approved ? 'approved' : 'rejected';
            $poStatus = $approved ? 'approved' : 'cancelled';

            $po->update([
                'approval_status' => $newStatus,
                'status' => $poStatus,
                'approved_by_user_id' => $approverId,
                'approved_at' => now(),
                'approval_notes' => $comments,
            ]);

            AuditService::log('purchase_order_approval', $po, null, [
                'status' => $newStatus,
                'approver_id' => $approverId,
                'comments' => $comments,
            ]);

            if ($approved) {
                event(new PurchaseOrderApprovedEvent($po));
            }

            return $po;
        });
    }

    /**
     * Process Goods Receipt Note (GRN) upon physical arrival and quality inspection.
     * Records stock receipts in the inventory ledger and generates supplier invoice.
     */
    public function processGoodsReceipt(PurchaseOrder $po, array $grnData, array $receivedItems, int $userId): GoodsReceipt
    {
        return DB::transaction(function () use ($po, $grnData, $receivedItems, $userId) {
            if ($po->status !== 'approved' && $po->status !== 'partially_received') {
                throw new Exception("Cannot receive goods: Purchase Order {$po->po_number} must be in approved status.");
            }

            $grnNumber = $grnData['grn_number'] ?? ('GRN-' . date('Ym') . '-' . strtoupper(Str::random(5)));

            $grn = GoodsReceipt::create([
                'company_id' => $po->company_id,
                'grn_number' => $grnNumber,
                'purchase_order_id' => $po->id,
                'supplier_id' => $po->supplier_id,
                'warehouse_id' => $grnData['warehouse_id'] ?? $po->warehouse_id,
                'received_date' => $grnData['received_date'] ?? now()->toDateString(),
                'delivery_challan_number' => $grnData['delivery_challan_number'] ?? null,
                'transporter_name' => $grnData['transporter_name'] ?? null,
                'vehicle_number' => $grnData['vehicle_number'] ?? null,
                'status' => 'received',
                'received_by_user_id' => $userId,
                'verified_by_user_id' => $userId,
                'notes' => $grnData['notes'] ?? null,
            ]);

            $totalAcceptedValue = 0;

            foreach ($receivedItems as $itemData) {
                /** @var PurchaseOrderItem $poItem */
                $poItem = PurchaseOrderItem::findOrFail($itemData['purchase_order_item_id']);

                $qtyReceived = (float) $itemData['quantity_received'];
                $qtyAccepted = (float) $itemData['quantity_accepted'];
                $qtyRejected = (float) ($itemData['quantity_rejected'] ?? ($qtyReceived - $qtyAccepted));
                $batchNumber = $itemData['batch_number'] ?? ('LOT-' . date('Ymd') . '-' . strtoupper(Str::random(4)));
                $unitCost = (float) $poItem->unit_price;

                $grnItem = GoodsReceiptItem::create([
                    'goods_receipt_id' => $grn->id,
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $poItem->product_id,
                    'warehouse_location_id' => $itemData['warehouse_location_id'] ?? null,
                    'quantity_received' => $qtyReceived,
                    'quantity_accepted' => $qtyAccepted,
                    'quantity_rejected' => $qtyRejected,
                    'rejection_reason' => $itemData['rejection_reason'] ?? null,
                    'batch_number' => $batchNumber,
                    'lot_number' => $itemData['lot_number'] ?? null,
                    'expiry_date' => $itemData['expiry_date'] ?? null,
                    'unit_cost' => $unitCost,
                ]);

                // Update PO item quantity_received
                $poItem->increment('quantity_received', $qtyAccepted);

                // Add accepted items into inventory through double-entry stock movement!
                if ($qtyAccepted > 0) {
                    $this->stockService->recordMovement([
                        'company_id' => $po->company_id,
                        'movement_type' => 'purchase_receipt',
                        'product_id' => $poItem->product_id,
                        'product_variant_id' => $poItem->product_variant_id,
                        'dest_warehouse_id' => $grn->warehouse_id,
                        'dest_location_id' => $grnItem->warehouse_location_id,
                        'quantity' => $qtyAccepted,
                        'unit_id' => $poItem->unit_id,
                        'unit_cost' => $unitCost,
                        'batch_number' => $batchNumber,
                        'lot_number' => $itemData['lot_number'] ?? null,
                        'reference_type' => 'GoodsReceipt',
                        'reference_id' => $grn->id,
                        'reason' => "Purchase receipt against PO #{$po->po_number} (GRN #{$grn->grn_number})",
                        'created_by_user_id' => $userId,
                    ]);

                    $totalAcceptedValue += ($qtyAccepted * $unitCost);
                }
            }

            // Check if PO is completely or partially received
            $allReceived = true;
            $anyReceived = false;
            /** @var PurchaseOrderItem $item */
            foreach ($po->items()->get() as $item) {
                if ((float) $item->quantity_received < (float) $item->quantity_ordered) {
                    $allReceived = false;
                }
                if ((float) $item->quantity_received > 0) {
                    $anyReceived = true;
                }
            }

            $newPoStatus = $allReceived ? 'received' : ($anyReceived ? 'partially_received' : 'approved');
            $po->update(['status' => $newPoStatus]);

            // Auto-generate supplier invoice for the accepted goods
            if ($totalAcceptedValue > 0) {
                $supplier = $po->supplier;
                $creditDays = $supplier->payment_terms_days ?? 30;
                $tax = round($totalAcceptedValue * 0.18, 2); // 18% GST standard

                SupplierInvoice::create([
                    'company_id' => $po->company_id,
                    'supplier_id' => $po->supplier_id,
                    'purchase_order_id' => $po->id,
                    'invoice_number' => 'INV-' . date('Ym') . '-' . strtoupper(Str::random(5)),
                    'invoice_date' => now()->toDateString(),
                    'due_date' => now()->addDays($creditDays)->toDateString(),
                    'subtotal' => round($totalAcceptedValue, 2),
                    'tax_amount' => $tax,
                    'total_amount' => round($totalAcceptedValue + $tax, 2),
                    'paid_amount' => 0,
                    'payment_status' => 'unpaid',
                    'status' => 'verified',
                    'notes' => "Auto-generated from GRN #{$grn->grn_number}",
                ]);
            }

            AuditService::log('goods_received', $grn, null, [
                'grn_number' => $grn->grn_number,
                'po_number' => $po->po_number,
                'total_accepted_value' => $totalAcceptedValue,
            ]);

            return $grn;
        });
    }
}

