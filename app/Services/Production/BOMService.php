<?php

namespace App\Services\Production;

use App\Models\BillOfMaterial;
use App\Models\InventoryBalance;
use App\Models\Product;

class BOMService
{
    /**
     * Recursively explode a Bill of Materials for a target production quantity.
     * Returns a flat or nested tree of all raw materials, sub-assemblies, quantities, and scrap allowances.
     *
     * @param BillOfMaterial $bom
     * @param float $requiredQuantity
     * @param int $depth
     * @return array<int, array{
     *     component_id: int,
     *     sku: string,
     *     name: string,
     *     type: string,
     *     unit: string,
     *     net_quantity: float,
     *     scrap_percentage: float,
     *     gross_quantity: float,
     *     standard_cost: float,
     *     total_cost: float,
     *     is_sub_assembly: bool,
     *     depth: int,
     *     sub_components?: array
     * }>
     */
    public function explodeBOM(BillOfMaterial $bom, float $requiredQuantity = 1.0, int $depth = 0): array
    {
        $components = [];
        $batchSize = (float) ($bom->batch_size > 0 ? $bom->batch_size : 1.0);
        $ratio = $requiredQuantity / $batchSize;

        $items = $bom->items()->with(['componentProduct.unit', 'subBom.items'])->get();

        /** @var \App\Models\BomItem $item */
        foreach ($items as $item) {
            /** @var \App\Models\Product $comp */
            $comp = $item->componentProduct;
            $netQty = (float) $item->quantity * $ratio;
            $scrapPct = (float) $item->scrap_percentage;
            $grossQty = $netQty * (1 + ($scrapPct / 100));
            $unitCost = (float) ($comp->standard_cost ?? 0);
            $totalCost = $grossQty * $unitCost;

            $record = [
                'component_id' => $comp->id,
                'sku' => $comp->sku,
                'name' => $comp->name,
                'type' => $comp->type,
                'unit' => $item->unit->symbol ?? ($comp->unit->symbol ?? 'PCS'),
                'net_quantity' => round($netQty, 4),
                'scrap_percentage' => $scrapPct,
                'gross_quantity' => round($grossQty, 4),
                'standard_cost' => round($unitCost, 2),
                'total_cost' => round($totalCost, 2),
                'is_sub_assembly' => (bool) $item->is_sub_assembly,
                'depth' => $depth,
            ];

            if ($item->is_sub_assembly && $item->subBom) {
                $record['sub_components'] = $this->explodeBOM($item->subBom, $grossQty, $depth + 1);
            }

            $components[] = $record;
        }

        return $components;
    }

    /**
     * Check raw material availability across warehouses for a planned BOM output.
     *
     * @param BillOfMaterial $bom
     * @param float $plannedQuantity
     * @param int|null $warehouseId If null, checks across all warehouses in company
     * @return array{
     *     can_produce: bool,
     *     bottlenecks: array,
     *     materials: array
     * }
     */
    public function checkMaterialAvailability(BillOfMaterial $bom, float $plannedQuantity, ?int $warehouseId = null): array
    {
        $exploded = $this->explodeBOM($bom, $plannedQuantity);
        $materials = [];
        $bottlenecks = [];
        $canProduce = true;

        foreach ($exploded as $item) {
            $productId = $item['component_id'];
            $required = $item['gross_quantity'];

            $balanceQuery = InventoryBalance::where('product_id', $productId);
            if ($warehouseId) {
                $balanceQuery->where('warehouse_id', $warehouseId);
            }

            $availableQty = (float) $balanceQuery->sum('quantity_available');
            $shortage = max(0, $required - $availableQty);
            $isAvailable = $availableQty >= $required;

            if (!$isAvailable) {
                $canProduce = false;
                $bottlenecks[] = [
                    'product_id' => $productId,
                    'sku' => $item['sku'],
                    'name' => $item['name'],
                    'required' => $required,
                    'available' => $availableQty,
                    'shortage' => round($shortage, 4),
                    'unit' => $item['unit'],
                ];
            }

            $materials[] = [
                'product_id' => $productId,
                'sku' => $item['sku'],
                'name' => $item['name'],
                'required' => $required,
                'available' => $availableQty,
                'shortage' => round($shortage, 4),
                'is_sufficient' => $isAvailable,
                'unit' => $item['unit'],
            ];
        }

        return [
            'can_produce' => $canProduce,
            'bottlenecks' => $bottlenecks,
            'materials' => $materials,
        ];
    }
}

