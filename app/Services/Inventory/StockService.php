<?php

namespace App\Services\Inventory;

use App\Events\LowStockDetectedEvent;
use App\Events\StockMovedEvent;
use App\Models\InventoryBalance;
use App\Models\Notification;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Audit\AuditService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockService
{
    /**
     * Record a stock movement in the immutable ledger and adjust inventory balances atomically.
     *
     * @param array{
     *     company_id: int,
     *     movement_type: string,
     *     product_id: int,
     *     product_variant_id?: ?int,
     *     source_warehouse_id?: ?int,
     *     source_location_id?: ?int,
     *     dest_warehouse_id?: ?int,
     *     dest_location_id?: ?int,
     *     quantity: float,
     *     unit_id: int,
     *     unit_cost?: ?float,
     *     batch_number?: ?string,
     *     lot_number?: ?string,
     *     expiry_date?: ?string,
     *     reference_type?: ?string,
     *     reference_id?: ?int,
     *     reason?: ?string,
     *     notes?: ?string,
     *     created_by_user_id?: ?int,
     *     allow_negative?: bool
     * } $data
     * @return StockMovement
     * @throws Exception
     */
    public function recordMovement(array $data): StockMovement
    {
        return DB::transaction(function () use ($data) {
            $companyId = $data['company_id'];
            $productId = $data['product_id'];
            $variantId = $data['product_variant_id'] ?? null;
            $quantity = (float) $data['quantity'];
            $movementType = $data['movement_type'];
            $allowNegative = $data['allow_negative'] ?? false;
            $unitCost = isset($data['unit_cost']) ? (float) $data['unit_cost'] : 0.0;
            $batchNumber = $data['batch_number'] ?? null;
            $lotNumber = $data['lot_number'] ?? null;
            $expiryDate = $data['expiry_date'] ?? null;

            if ($quantity <= 0) {
                throw new Exception("Movement quantity must be strictly positive: {$quantity} provided.");
            }

            $sourceWarehouseId = $data['source_warehouse_id'] ?? null;
            $sourceLocationId = $data['source_location_id'] ?? null;
            $destWarehouseId = $data['dest_warehouse_id'] ?? null;
            $destLocationId = $data['dest_location_id'] ?? null;

            // Generate unique sequential movement number
            $movementNumber = 'MOV-' . date('Ym') . '-' . strtoupper(Str::random(6));

            // 1. Process Source Warehouse (Deduction)
            if ($sourceWarehouseId) {
                $sourceBalanceQuery = InventoryBalance::where('company_id', $companyId)
                    ->where('warehouse_id', $sourceWarehouseId)
                    ->where('product_id', $productId);

                if ($sourceLocationId) {
                    $sourceBalanceQuery->where('warehouse_location_id', $sourceLocationId);
                }
                if ($variantId) {
                    $sourceBalanceQuery->where('product_variant_id', $variantId);
                }
                if ($batchNumber) {
                    $sourceBalanceQuery->where('batch_number', $batchNumber);
                }

                /** @var InventoryBalance|null $sourceBalance */
                $sourceBalance = $sourceBalanceQuery->lockForUpdate()->first();

                if (!$sourceBalance) {
                    if (!$allowNegative) {
                        throw new Exception("Insufficient stock: No inventory balance record exists for product ID {$productId} in warehouse ID {$sourceWarehouseId}.");
                    }

                    $sourceBalance = InventoryBalance::create([
                        'company_id' => $companyId,
                        'warehouse_id' => $sourceWarehouseId,
                        'warehouse_location_id' => $sourceLocationId,
                        'product_id' => $productId,
                        'product_variant_id' => $variantId,
                        'batch_number' => $batchNumber,
                        'lot_number' => $lotNumber,
                        'expiry_date' => $expiryDate,
                        'quantity_on_hand' => 0,
                        'quantity_reserved' => 0,
                        'quantity_available' => 0,
                        'average_cost_unit' => $unitCost,
                    ]);
                    $sourceBalance = InventoryBalance::where('id', $sourceBalance->id)->lockForUpdate()->first();
                }

                $currentOnHand = (float) $sourceBalance->quantity_on_hand;
                $currentReserved = (float) $sourceBalance->quantity_reserved;

                if (!$allowNegative && ($currentOnHand < $quantity)) {
                    throw new Exception("Insufficient stock for product ID {$productId}: requested {$quantity}, but only {$currentOnHand} is on hand in warehouse ID {$sourceWarehouseId}.");
                }

                $newOnHand = $currentOnHand - $quantity;
                $newAvailable = max(0, $newOnHand - $currentReserved);

                $sourceBalance->update([
                    'quantity_on_hand' => $newOnHand,
                    'quantity_available' => $newAvailable,
                ]);

                if ($unitCost <= 0 && $sourceBalance->average_cost_unit > 0) {
                    $unitCost = (float) $sourceBalance->average_cost_unit;
                }
            }

            // 2. Process Destination Warehouse (Addition)
            if ($destWarehouseId) {
                $destBalanceQuery = InventoryBalance::where('company_id', $companyId)
                    ->where('warehouse_id', $destWarehouseId)
                    ->where('product_id', $productId);

                if ($destLocationId) {
                    $destBalanceQuery->where('warehouse_location_id', $destLocationId);
                } else {
                    $destBalanceQuery->whereNull('warehouse_location_id');
                }

                if ($variantId) {
                    $destBalanceQuery->where('product_variant_id', $variantId);
                } else {
                    $destBalanceQuery->whereNull('product_variant_id');
                }

                if ($batchNumber) {
                    $destBalanceQuery->where('batch_number', $batchNumber);
                } else {
                    $destBalanceQuery->whereNull('batch_number');
                }

                /** @var InventoryBalance|null $destBalance */
                $destBalance = $destBalanceQuery->lockForUpdate()->first();

                if (!$destBalance) {
                    $destBalance = InventoryBalance::create([
                        'company_id' => $companyId,
                        'warehouse_id' => $destWarehouseId,
                        'warehouse_location_id' => $destLocationId,
                        'product_id' => $productId,
                        'product_variant_id' => $variantId,
                        'batch_number' => $batchNumber,
                        'lot_number' => $lotNumber,
                        'expiry_date' => $expiryDate,
                        'quantity_on_hand' => 0,
                        'quantity_reserved' => 0,
                        'quantity_available' => 0,
                        'average_cost_unit' => $unitCost,
                    ]);
                    $destBalance = InventoryBalance::where('id', $destBalance->id)->lockForUpdate()->first();
                }

                $oldQty = (float) $destBalance->quantity_on_hand;
                $oldCost = (float) $destBalance->average_cost_unit;
                $newQty = $oldQty + $quantity;

                // Moving weighted average cost calculation
                $newAvgCost = ($newQty > 0)
                    ? (($oldQty * $oldCost) + ($quantity * $unitCost)) / $newQty
                    : $unitCost;

                $newAvailable = max(0, $newQty - (float) $destBalance->quantity_reserved);

                $destBalance->update([
                    'quantity_on_hand' => $newQty,
                    'quantity_available' => $newAvailable,
                    'average_cost_unit' => round($newAvgCost, 2),
                ]);
            }

            $totalCost = round($quantity * $unitCost, 2);

            // 3. Create Immutable Stock Ledger Record
            $movement = StockMovement::create([
                'company_id' => $companyId,
                'movement_number' => $movementNumber,
                'movement_type' => $movementType,
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'source_warehouse_id' => $sourceWarehouseId,
                'source_location_id' => $sourceLocationId,
                'dest_warehouse_id' => $destWarehouseId,
                'dest_location_id' => $destLocationId,
                'quantity' => $quantity,
                'unit_id' => $data['unit_id'],
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'batch_number' => $batchNumber,
                'lot_number' => $lotNumber,
                'expiry_date' => $expiryDate,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $data['created_by_user_id'] ?? null,
                'created_at' => now(),
            ]);

            // 4. Log Audit Trail
            AuditService::log('stock_moved', $movement, null, [
                'movement_number' => $movementNumber,
                'movement_type' => $movementType,
                'product_id' => $productId,
                'quantity' => $quantity,
                'source_warehouse_id' => $sourceWarehouseId,
                'dest_warehouse_id' => $destWarehouseId,
            ], $companyId, $data['created_by_user_id'] ?? null);

            // 5. Fire Stock Movement Event
            event(new StockMovedEvent($movement));

            // 6. Check Low Stock Thresholds and alert
            $this->checkLowStockAlert($productId, $companyId);

            return $movement;
        });
    }

    /**
     * Transfer stock between warehouses or locations atomically.
     */
    public function transferStock(
        int $companyId,
        int $productId,
        int $sourceWarehouseId,
        int $destWarehouseId,
        float $quantity,
        int $unitId,
        ?int $sourceLocationId = null,
        ?int $destLocationId = null,
        ?string $batchNumber = null,
        ?string $reason = 'Inter-warehouse stock transfer',
        ?int $userId = null
    ): StockMovement {
        return $this->recordMovement([
            'company_id' => $companyId,
            'movement_type' => 'stock_transfer',
            'product_id' => $productId,
            'source_warehouse_id' => $sourceWarehouseId,
            'source_location_id' => $sourceLocationId,
            'dest_warehouse_id' => $destWarehouseId,
            'dest_location_id' => $destLocationId,
            'quantity' => $quantity,
            'unit_id' => $unitId,
            'batch_number' => $batchNumber,
            'reason' => $reason,
            'created_by_user_id' => $userId,
        ]);
    }

    /**
     * Perform stock adjustment (positive or negative count variance).
     */
    public function adjustStock(
        int $companyId,
        int $productId,
        int $warehouseId,
        float $adjustedQuantity, // positive to add, negative to deduct
        int $unitId,
        ?int $locationId = null,
        ?string $batchNumber = null,
        string $reason = 'Physical inventory audit variance',
        ?int $userId = null
    ): StockMovement {
        if ($adjustedQuantity > 0) {
            return $this->recordMovement([
                'company_id' => $companyId,
                'movement_type' => 'stock_adjustment',
                'product_id' => $productId,
                'dest_warehouse_id' => $warehouseId,
                'dest_location_id' => $locationId,
                'quantity' => $adjustedQuantity,
                'unit_id' => $unitId,
                'batch_number' => $batchNumber,
                'reason' => $reason,
                'created_by_user_id' => $userId,
            ]);
        } else {
            return $this->recordMovement([
                'company_id' => $companyId,
                'movement_type' => 'stock_adjustment',
                'product_id' => $productId,
                'source_warehouse_id' => $warehouseId,
                'source_location_id' => $locationId,
                'quantity' => abs($adjustedQuantity),
                'unit_id' => $unitId,
                'batch_number' => $batchNumber,
                'reason' => $reason,
                'created_by_user_id' => $userId,
            ]);
        }
    }

    /**
     * Check if a product's available quantity has fallen below reorder levels.
     */
    public function checkLowStockAlert(int $productId, int $companyId): void
    {
        $product = Product::find($productId);
        if (!$product) {
            return;
        }

        $totalOnHand = (float) InventoryBalance::where('company_id', $companyId)
            ->where('product_id', $productId)
            ->sum('quantity_on_hand');

        $reorderLevel = (float) $product->reorder_level;

        if ($reorderLevel > 0 && $totalOnHand <= $reorderLevel) {
            event(new LowStockDetectedEvent($product, $totalOnHand, $reorderLevel));

            // Prevent spamming duplicate unread notifications
            $existing = Notification::where('company_id', $companyId)
                ->where('type', 'low_stock')
                ->where('is_read', false)
                ->where('data->product_id', $productId)
                ->exists();

            if (!$existing) {
                $unitSymbol = $product->unit ? $product->unit->symbol : 'units';
                Notification::create([
                    'company_id' => $companyId,
                    'type' => 'low_stock',
                    'title' => "Low Stock Alert: {$product->name} ({$product->sku})",
                    'message' => "Current stock on hand is {$totalOnHand} {$unitSymbol}, which is at or below the reorder threshold of {$reorderLevel} {$unitSymbol}.",
                    'data' => [
                        'product_id' => $productId,
                        'sku' => $product->sku,
                        'current_qty' => $totalOnHand,
                        'reorder_level' => $reorderLevel,
                    ],
                    'is_read' => false,
                ]);
            }
        }
    }
}

