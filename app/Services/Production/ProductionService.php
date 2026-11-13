<?php

namespace App\Services\Production;

use App\Events\ProductionCompletedEvent;
use App\Models\BillOfMaterial;
use App\Models\InventoryBalance;
use App\Models\MaterialIssue;
use App\Models\MaterialReservation;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionOutput;
use App\Models\ProductionStage;
use App\Services\Audit\AuditService;
use App\Services\Inventory\StockService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductionService
{
    public function __construct(
        protected BOMService $bomService,
        protected StockService $stockService
    ) {
    }

    /**
     * Create a new Production Order with default sequential stages.
     */
    public function createOrder(array $data): ProductionOrder
    {
        return DB::transaction(function () use ($data) {
            $companyId = $data['company_id'];
            $productId = $data['product_id'];
            $bomId = $data['bill_of_materials_id'];
            $targetWarehouseId = $data['target_warehouse_id'];
            $plannedQty = (float) $data['planned_quantity'];
            $orderNumber = $data['order_number'] ?? ('WO-' . date('Ym') . '-' . strtoupper(Str::random(5)));

            $bom = BillOfMaterial::findOrFail($bomId);

            // Calculate estimated production cost
            $components = $this->bomService->explodeBOM($bom, $plannedQty);
            $estimatedMaterialCost = array_sum(array_column($components, 'total_cost'));

            $order = ProductionOrder::create([
                'company_id' => $companyId,
                'order_number' => $orderNumber,
                'product_id' => $productId,
                'bill_of_materials_id' => $bomId,
                'target_warehouse_id' => $targetWarehouseId,
                'planned_quantity' => $plannedQty,
                'produced_quantity' => 0,
                'rejected_quantity' => 0,
                'scrapped_quantity' => 0,
                'start_date' => $data['start_date'] ?? now()->toDateString(),
                'due_date' => $data['due_date'] ?? now()->addDays(7)->toDateString(),
                'status' => 'planned',
                'priority' => $data['priority'] ?? 'normal',
                'estimated_cost' => $estimatedMaterialCost,
                'assigned_to_user_id' => $data['assigned_to_user_id'] ?? null,
                'created_by_user_id' => $data['created_by_user_id'],
                'notes' => $data['notes'] ?? null,
            ]);

            // Create standard manufacturing stages
            $stages = [
                ['name' => 'Raw Material Staging & Inspection', 'sequence' => 1, 'duration' => 60],
                ['name' => 'Forging / Machining Operation', 'sequence' => 2, 'duration' => 240],
                ['name' => 'Heat Treatment & Surface Hardening', 'sequence' => 3, 'duration' => 180],
                ['name' => 'Precision Grinding & Assembly', 'sequence' => 4, 'duration' => 120],
                ['name' => 'Quality Control & Final Acceptance', 'sequence' => 5, 'duration' => 90],
            ];

            foreach ($stages as $s) {
                ProductionStage::create([
                    'production_order_id' => $order->id,
                    'stage_name' => $s['name'],
                    'sequence' => $s['sequence'],
                    'status' => 'pending',
                    'planned_duration_minutes' => $s['duration'],
                ]);
            }

            AuditService::log('production_order_created', $order, null, $order->toArray());

            return $order;
        });
    }

    /**
     * Reserve raw materials for a production order.
     * Locks balance rows and decrements available while incrementing reserved quantity.
     */
    public function reserveMaterials(ProductionOrder $order, ?int $warehouseId = null): array
    {
        return DB::transaction(function () use ($order, $warehouseId) {
            $bom = BillOfMaterial::findOrFail($order->bill_of_materials_id);
            $plannedQty = (float) $order->planned_quantity;
            $whId = $warehouseId ?? $order->target_warehouse_id;

            $exploded = $this->bomService->explodeBOM($bom, $plannedQty);
            $reservations = [];

            foreach ($exploded as $comp) {
                $compProductId = $comp['component_id'];
                $requiredQty = (float) $comp['gross_quantity'];

                // Lock inventory balance
                /** @var InventoryBalance|null $balance */
                $balance = InventoryBalance::where('company_id', $order->company_id)
                    ->where('product_id', $compProductId)
                    ->when($whId, fn ($q) => $q->where('warehouse_id', $whId))
                    ->lockForUpdate()
                    ->first();

                $available = (float) ($balance->quantity_available ?? 0);

                if (!$balance || $available < $requiredQty) {
                    throw new Exception("Cannot reserve material: Insufficient stock for {$comp['name']} ({$comp['sku']}). Required: {$requiredQty}, Available: {$available}");
                }

                $balance->increment('quantity_reserved', $requiredQty);
                $balance->decrement('quantity_available', $requiredQty);

                $reservation = MaterialReservation::create([
                    'production_order_id' => $order->id,
                    'product_id' => $compProductId,
                    'warehouse_id' => $balance->warehouse_id,
                    'warehouse_location_id' => $balance->warehouse_location_id,
                    'quantity_reserved' => $requiredQty,
                    'quantity_issued' => 0,
                    'status' => 'reserved',
                ]);

                $reservations[] = $reservation;
            }

            $order->update(['status' => 'material_reserved']);
            AuditService::log('material_reserved', $order, null, ['reserved_items_count' => count($reservations)]);

            return $reservations;
        });
    }

    /**
     * Issue materials from warehouse into the production shop-floor (Stock deduction).
     */
    public function issueMaterials(ProductionOrder $order, int $userId): array
    {
        return DB::transaction(function () use ($order, $userId) {
            $reservations = $order->reservations()->where('status', 'reserved')->get();

            if ($reservations->isEmpty()) {
                // If not pre-reserved, reserve first
                $reservations = $this->reserveMaterials($order);
            }

            $issues = [];
            $totalMaterialCost = 0;

            foreach ($reservations as $res) {
                $qtyToIssue = (float) $res->quantity_reserved - (float) $res->quantity_issued;
                if ($qtyToIssue <= 0) {
                    continue;
                }

                $prod = $res->product;
                $unitCost = (float) $prod->standard_cost;

                // Move stock out of warehouse as production consumption
                $movement = $this->stockService->recordMovement([
                    'company_id' => $order->company_id,
                    'movement_type' => 'production_consumption',
                    'product_id' => $res->product_id,
                    'source_warehouse_id' => $res->warehouse_id,
                    'source_location_id' => $res->warehouse_location_id,
                    'quantity' => $qtyToIssue,
                    'unit_id' => $prod->unit_id,
                    'unit_cost' => $unitCost,
                    'reference_type' => 'ProductionOrder',
                    'reference_id' => $order->id,
                    'reason' => "Issued for Production Order #{$order->order_number}",
                    'created_by_user_id' => $userId,
                ]);

                // Decrement reservation
                $balance = InventoryBalance::where('warehouse_id', $res->warehouse_id)
                    ->where('product_id', $res->product_id)
                    ->lockForUpdate()
                    ->first();

                if ($balance) {
                    $balance->decrement('quantity_reserved', $qtyToIssue);
                }

                $res->update([
                    'quantity_issued' => $res->quantity_reserved,
                    'status' => 'fully_issued',
                ]);

                $issue = MaterialIssue::create([
                    'production_order_id' => $order->id,
                    'issue_number' => 'ISS-' . date('Ym') . '-' . strtoupper(Str::random(5)),
                    'product_id' => $res->product_id,
                    'warehouse_id' => $res->warehouse_id,
                    'warehouse_location_id' => $res->warehouse_location_id,
                    'quantity_issued' => $qtyToIssue,
                    'unit_id' => $prod->unit_id,
                    'unit_cost' => $unitCost,
                    'issued_by_user_id' => $userId,
                    'issued_at' => now(),
                    'notes' => "Shop floor issue for work order {$order->order_number}",
                ]);

                $totalMaterialCost += ($qtyToIssue * $unitCost);
                $issues[] = $issue;
            }

            $order->update([
                'status' => 'in_progress',
                'actual_start_at' => now(),
                'actual_material_cost' => $totalMaterialCost,
            ]);

            AuditService::log('material_issued', $order, null, [
                'total_material_cost' => $totalMaterialCost,
                'items_issued' => count($issues),
            ]);

            return $issues;
        });
    }

    /**
     * Complete production work order:
     * - Records finished goods output into target warehouse.
     * - Accounts for rejected goods and scrap.
     * - Computes final unit cost and actual production cost.
     */
    public function completeProduction(
        ProductionOrder $order,
        float $quantityProduced,
        float $quantityRejected = 0,
        float $quantityScrapped = 0,
        float $labourCost = 0,
        float $machineCost = 0,
        ?string $batchNumber = null,
        int $userId = 1
    ): ProductionOutput {
        return DB::transaction(function () use (
            $order,
            $quantityProduced,
            $quantityRejected,
            $quantityScrapped,
            $labourCost,
            $machineCost,
            $batchNumber,
            $userId
        ) {
            if ($quantityProduced <= 0) {
                throw new Exception("Produced quantity must be greater than zero.");
            }

            $batch = $batchNumber ?? ('BAT-' . date('Ymd') . '-' . strtoupper(Str::random(4)));
            $matCost = (float) $order->actual_material_cost;
            $totalActualCost = $matCost + $labourCost + $machineCost;
            $unitCost = round($totalActualCost / $quantityProduced, 2);

            $product = Product::findOrFail($order->product_id);

            // 1. Add finished goods to inventory via stock movement
            $movement = $this->stockService->recordMovement([
                'company_id' => $order->company_id,
                'movement_type' => 'production_output',
                'product_id' => $order->product_id,
                'dest_warehouse_id' => $order->target_warehouse_id,
                'quantity' => $quantityProduced,
                'unit_id' => $product->unit_id,
                'unit_cost' => $unitCost,
                'batch_number' => $batch,
                'reference_type' => 'ProductionOrder',
                'reference_id' => $order->id,
                'reason' => "Production order #{$order->order_number} finished goods receipt",
                'created_by_user_id' => $userId,
            ]);

            // 2. If there are scrapped goods, record scrap movement
            if ($quantityScrapped > 0) {
                // Scrapped goods movement
                $this->stockService->recordMovement([
                    'company_id' => $order->company_id,
                    'movement_type' => 'damaged_goods',
                    'product_id' => $order->product_id,
                    'dest_warehouse_id' => $order->target_warehouse_id,
                    'quantity' => $quantityScrapped,
                    'unit_id' => $product->unit_id,
                    'unit_cost' => 0,
                    'batch_number' => $batch . '-SCRAP',
                    'reference_type' => 'ProductionOrder',
                    'reference_id' => $order->id,
                    'reason' => "Production order #{$order->order_number} scrap/wastage",
                    'created_by_user_id' => $userId,
                ]);
            }

            // 3. Create ProductionOutput record
            $output = ProductionOutput::create([
                'production_order_id' => $order->id,
                'output_number' => 'OUT-' . date('Ym') . '-' . strtoupper(Str::random(5)),
                'product_id' => $order->product_id,
                'warehouse_id' => $order->target_warehouse_id,
                'batch_number' => $batch,
                'quantity_produced' => $quantityProduced,
                'quantity_rejected' => $quantityRejected,
                'quantity_scrapped' => $quantityScrapped,
                'unit_production_cost' => $unitCost,
                'total_production_cost' => $totalActualCost,
                'received_by_user_id' => $userId,
                'recorded_at' => now(),
                'notes' => "Completed with batch {$batch}",
            ]);

            // 4. Update stages to completed
            $order->stages()->where('status', '!=', 'completed')->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // 5. Update Order status and financial roll-up
            $order->update([
                'produced_quantity' => $quantityProduced,
                'rejected_quantity' => $quantityRejected,
                'scrapped_quantity' => $quantityScrapped,
                'actual_labour_cost' => $labourCost,
                'actual_machine_cost' => $machineCost,
                'actual_cost' => $totalActualCost,
                'actual_completed_at' => now(),
                'status' => 'completed',
            ]);

            AuditService::log('production_completed', $order, null, [
                'batch_number' => $batch,
                'quantity_produced' => $quantityProduced,
                'unit_cost' => $unitCost,
                'total_cost' => $totalActualCost,
            ]);

            event(new ProductionCompletedEvent($order));

            return $output;
        });
    }
}

