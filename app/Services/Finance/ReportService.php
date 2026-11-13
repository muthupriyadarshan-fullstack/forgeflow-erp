<?php

namespace App\Services\Finance;

use App\Models\InventoryBalance;
use App\Models\Notification;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\SupplierInvoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Compute real-time dashboard KPIs and analytics for the enterprise dashboard.
     */
    public function getDashboardSummary(int $companyId): array
    {
        $currentMonthStart = Carbon::now()->startOfMonth();
        $currentMonthEnd = Carbon::now()->endOfMonth();

        // 1. Production Metrics
        $totalProductionOrders = ProductionOrder::where('company_id', $companyId)->count();
        $completedProductionOrders = ProductionOrder::where('company_id', $companyId)
            ->where('status', 'completed')
            ->count();
        $inProgressProductionOrders = ProductionOrder::where('company_id', $companyId)
            ->where('status', 'in_progress')
            ->count();
        $totalUnitsProduced = (float) ProductionOrder::where('company_id', $companyId)
            ->sum('produced_quantity');
        $totalProductionValue = (float) ProductionOrder::where('company_id', $companyId)
            ->sum('actual_cost');

        // Production Efficiency: completed vs planned and scrap percentage
        $totalScrapped = (float) ProductionOrder::where('company_id', $companyId)->sum('scrapped_quantity');
        $scrapRate = ($totalUnitsProduced + $totalScrapped > 0)
            ? round(($totalScrapped / ($totalUnitsProduced + $totalScrapped)) * 100, 2)
            : 0;

        $efficiency = ($totalProductionOrders > 0)
            ? round(($completedProductionOrders / $totalProductionOrders) * 100, 1)
            : 85.0;

        // 2. Inventory Metrics
        $rawMaterialBalances = InventoryBalance::where('inventory_balances.company_id', $companyId)
            ->join('products', 'inventory_balances.product_id', '=', 'products.id')
            ->where('products.type', 'raw_material')
            ->selectRaw('SUM(inventory_balances.quantity_on_hand) as total_qty, SUM(inventory_balances.quantity_on_hand * inventory_balances.average_cost_unit) as total_val')
            ->first();

        $finishedGoodsBalances = InventoryBalance::where('inventory_balances.company_id', $companyId)
            ->join('products', 'inventory_balances.product_id', '=', 'products.id')
            ->where('products.type', 'finished_good')
            ->selectRaw('SUM(inventory_balances.quantity_on_hand) as total_qty, SUM(inventory_balances.quantity_on_hand * inventory_balances.average_cost_unit) as total_val')
            ->first();

        $totalInventoryValuation = (float) (InventoryBalance::where('company_id', $companyId)
            ->selectRaw('SUM(quantity_on_hand * average_cost_unit) as total_val')
            ->value('total_val') ?? 0.0);

        // 3. Purchase Metrics
        $pendingPOs = PurchaseOrder::where('company_id', $companyId)
            ->whereIn('status', ['pending_approval', 'approved', 'partially_received'])
            ->count();
        $pendingPOValue = (float) PurchaseOrder::where('company_id', $companyId)
            ->whereIn('status', ['pending_approval', 'approved', 'partially_received'])
            ->sum('total_amount');

        $monthlyProcurement = (float) PurchaseOrder::where('company_id', $companyId)
            ->where('order_date', '>=', $currentMonthStart->toDateString())
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount');

        // 4. Low stock alerts
        $lowStockProducts = Product::where('company_id', $companyId)
            ->where('reorder_level', '>', 0)
            ->with(['unit', 'category'])
            ->get()
            ->filter(function (Product $prod) {
                return $prod->total_quantity_on_hand <= (float) $prod->reorder_level;
            })
            ->values();

        $lowStockCount = $lowStockProducts->count();

        // 5. Monthly Trend Chart Data (Last 6 months)
        $monthlyTrends = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $mStart = $month->copy()->startOfMonth()->toDateString();
            $mEnd = $month->copy()->endOfMonth()->toDateString();

            $procurementAmt = (float) PurchaseOrder::where('company_id', $companyId)
                ->whereBetween('order_date', [$mStart, $mEnd])
                ->where('status', '!=', 'cancelled')
                ->sum('total_amount');

            $productionAmt = (float) ProductionOrder::where('company_id', $companyId)
                ->whereBetween('start_date', [$mStart, $mEnd])
                ->sum('actual_cost');

            $monthlyTrends[] = [
                'month' => $month->format('M Y'),
                'procurement' => round($procurementAmt, 2),
                'production' => round($productionAmt, 2),
            ];
        }

        // 6. Recent Stock Movements
        $recentMovements = StockMovement::where('company_id', $companyId)
            ->with(['product.unit', 'sourceWarehouse', 'destWarehouse'])
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return [
            'metrics' => [
                'total_production_volume' => round($totalUnitsProduced, 2),
                'total_production_value' => round($totalProductionValue, 2),
                'raw_material_value' => round((float) ($rawMaterialBalances->total_val ?? 0), 2),
                'raw_material_qty' => round((float) ($rawMaterialBalances->total_qty ?? 0), 2),
                'finished_goods_value' => round((float) ($finishedGoodsBalances->total_val ?? 0), 2),
                'finished_goods_qty' => round((float) ($finishedGoodsBalances->total_qty ?? 0), 2),
                'total_inventory_valuation' => round($totalInventoryValuation, 2),
                'pending_purchase_orders_count' => $pendingPOs,
                'pending_purchase_orders_value' => round($pendingPOValue, 2),
                'monthly_procurement_value' => round($monthlyProcurement, 2),
                'production_efficiency_pct' => $efficiency,
                'scrap_rate_pct' => $scrapRate,
                'low_stock_count' => $lowStockCount,
            ],
            'low_stock_items' => $lowStockProducts->map(function ($p) {
                return [
                    'id' => $p->id,
                    'sku' => $p->sku,
                    'name' => $p->name,
                    'category' => $p->category->name ?? 'General',
                    'current_stock' => $p->total_quantity_on_hand,
                    'reorder_level' => (float) $p->reorder_level,
                    'unit' => $p->unit->symbol ?? 'PCS',
                ];
            }),
            'monthly_trends' => $monthlyTrends,
            'recent_movements' => $recentMovements,
        ];
    }

    /**
     * Get Detailed Inventory Valuation Report with FIFO / Weighted Average cost.
     */
    public function getInventoryValuationReport(int $companyId): array
    {
        $balances = InventoryBalance::where('inventory_balances.company_id', $companyId)
            ->join('products', 'inventory_balances.product_id', '=', 'products.id')
            ->join('warehouses', 'inventory_balances.warehouse_id', '=', 'warehouses.id')
            ->join('units', 'products.unit_id', '=', 'units.id')
            ->select([
                'products.sku',
                'products.name as product_name',
                'products.type as product_type',
                'warehouses.name as warehouse_name',
                'units.symbol as unit',
                'inventory_balances.batch_number',
                'inventory_balances.quantity_on_hand',
                'inventory_balances.quantity_reserved',
                'inventory_balances.quantity_available',
                'inventory_balances.average_cost_unit',
                DB::raw('(inventory_balances.quantity_on_hand * inventory_balances.average_cost_unit) as total_valuation')
            ])
            ->orderBy('products.type')
            ->orderBy('products.name')
            ->get();

        $totalValuation = $balances->sum('total_valuation');

        return [
            'total_valuation' => round((float) $totalValuation, 2),
            'items_count' => $balances->count(),
            'rows' => $balances,
        ];
    }

    /**
     * Generate CSV export of stock ledger / inventory valuation / production.
     */
    public function generateCsvExport(string $reportType, int $companyId): string
    {
        $output = fopen('php://temp', 'r+');

        if ($reportType === 'stock_ledger') {
            fputcsv($output, [
                'Movement Number',
                'Date',
                'Type',
                'SKU',
                'Product Name',
                'Source Warehouse',
                'Dest Warehouse',
                'Quantity',
                'Unit',
                'Unit Cost (INR)',
                'Total Cost (INR)',
                'Batch Number',
                'Reason'
            ]);

            $movements = StockMovement::where('company_id', $companyId)
                ->with(['product.unit', 'sourceWarehouse', 'destWarehouse'])
                ->orderByDesc('id')
                ->get();

            foreach ($movements as $m) {
                fputcsv($output, [
                    $m->movement_number,
                    $m->created_at ? Carbon::parse($m->created_at)->format('d-m-Y H:i') : '',
                    $m->movement_type,
                    $m->product->sku ?? '',
                    $m->product->name ?? '',
                    $m->sourceWarehouse->name ?? 'N/A',
                    $m->destWarehouse->name ?? 'N/A',
                    $m->quantity,
                    $m->unit->symbol ?? 'PCS',
                    $m->unit_cost,
                    $m->total_cost,
                    $m->batch_number ?? '',
                    $m->reason ?? '',
                ]);
            }
        } elseif ($reportType === 'inventory_valuation') {
            fputcsv($output, [
                'SKU',
                'Product Name',
                'Type',
                'Warehouse',
                'Batch',
                'Qty On Hand',
                'Qty Reserved',
                'Qty Available',
                'Unit',
                'Average Cost (INR)',
                'Total Valuation (INR)'
            ]);

            $report = $this->getInventoryValuationReport($companyId);
            foreach ($report['rows'] as $r) {
                fputcsv($output, [
                    $r->sku,
                    $r->product_name,
                    $r->product_type,
                    $r->warehouse_name,
                    $r->batch_number ?? 'Standard',
                    $r->quantity_on_hand,
                    $r->quantity_reserved,
                    $r->quantity_available,
                    $r->unit,
                    $r->average_cost_unit,
                    $r->total_valuation,
                ]);
            }
        } elseif ($reportType === 'production_orders') {
            fputcsv($output, [
                'Order Number',
                'Product SKU',
                'Product Name',
                'Status',
                'Planned Qty',
                'Produced Qty',
                'Rejected Qty',
                'Scrapped Qty',
                'Estimated Cost (INR)',
                'Actual Material Cost (INR)',
                'Actual Cost (INR)',
                'Start Date',
                'Due Date'
            ]);

            $orders = ProductionOrder::where('company_id', $companyId)
                ->with('product')
                ->orderByDesc('id')
                ->get();

            foreach ($orders as $o) {
                fputcsv($output, [
                    $o->order_number,
                    $o->product->sku ?? '',
                    $o->product->name ?? '',
                    $o->status,
                    $o->planned_quantity,
                    $o->produced_quantity,
                    $o->rejected_quantity,
                    $o->scrapped_quantity,
                    $o->estimated_cost,
                    $o->actual_material_cost,
                    $o->actual_cost,
                    $o->start_date ? Carbon::parse($o->start_date)->format('d-m-Y') : '',
                    $o->due_date ? Carbon::parse($o->due_date)->format('d-m-Y') : '',
                ]);
            }
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent ?: '';
    }
}

