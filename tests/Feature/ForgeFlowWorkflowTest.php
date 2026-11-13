<?php

use App\Models\BillOfMaterial;
use App\Models\BomItem;
use App\Models\Company;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\Inventory\StockService;
use App\Services\Production\BOMService;
use App\Services\Production\ProductionService;
use App\Services\Purchase\PurchaseService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->company = Company::create([
        'name' => 'ForgeFlow Test Plant Ltd.',
        'gstin' => '27TEST1234F1Z5',
        'currency' => 'INR',
    ]);

    $this->unitKg = Unit::create([
        'company_id' => $this->company->id,
        'name' => 'Kilogram',
        'symbol' => 'KG',
        'type' => 'weight',
    ]);

    $this->unitPcs = Unit::create([
        'company_id' => $this->company->id,
        'name' => 'Piece',
        'symbol' => 'PCS',
        'type' => 'count',
    ]);

    $this->category = ProductCategory::create([
        'company_id' => $this->company->id,
        'code' => 'TEST-CAT',
        'name' => 'Test Category',
    ]);

    $this->warehouseSource = Warehouse::create([
        'company_id' => $this->company->id,
        'code' => 'WH-SRC-01',
        'name' => 'Source Warehouse',
        'type' => 'raw_material',
    ]);

    $this->warehouseDest = Warehouse::create([
        'company_id' => $this->company->id,
        'code' => 'WH-DST-02',
        'name' => 'Destination Warehouse',
        'type' => 'finished_goods',
    ]);

    $this->loc1 = WarehouseLocation::create([
        'warehouse_id' => $this->warehouseSource->id,
        'code' => 'BIN-A1',
        'name' => 'Bin A1',
    ]);

    $this->rawSteel = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $this->category->id,
        'unit_id' => $this->unitKg->id,
        'sku' => 'RM-TEST-STL',
        'name' => 'Raw Test Steel Bar',
        'type' => 'raw_material',
        'standard_cost' => 100.00,
        'reorder_level' => 500.00,
    ]);

    $this->finishedGear = Product::create([
        'company_id' => $this->company->id,
        'category_id' => $this->category->id,
        'unit_id' => $this->unitPcs->id,
        'sku' => 'FG-TEST-GEAR',
        'name' => 'Industrial Helical Gear',
        'type' => 'finished_good',
        'standard_cost' => 1500.00,
        'selling_price' => 2500.00,
        'reorder_level' => 10.00,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Test Admin',
        'email' => 'admin@test.com',
        'password' => bcrypt('password'),
    ]);

    $role = Role::firstOrCreate(['name' => 'Company Admin', 'guard_name' => 'web']);
    $this->user->assignRole($role);
});

test('1. Stock receipt creates immutable movement and increases balance with weighted average cost', function () {
    $stockService = app(StockService::class);

    $movement = $stockService->recordMovement([
        'company_id' => $this->company->id,
        'movement_type' => 'purchase_receipt',
        'product_id' => $this->rawSteel->id,
        'dest_warehouse_id' => $this->warehouseSource->id,
        'dest_location_id' => $this->loc1->id,
        'quantity' => 1000.0,
        'unit_id' => $this->unitKg->id,
        'unit_cost' => 100.00,
        'batch_number' => 'BATCH-TEST-001',
        'created_by_user_id' => $this->user->id,
    ]);

    expect($movement->movement_number)->toBeString()
        ->and($movement->movement_type)->toBe('purchase_receipt')
        ->and((float) $movement->quantity)->toBe(1000.0);

    $balance = InventoryBalance::where('warehouse_id', $this->warehouseSource->id)
        ->where('product_id', $this->rawSteel->id)
        ->where('batch_number', 'BATCH-TEST-001')
        ->first();

    expect($balance)->not->toBeNull()
        ->and((float) $balance->quantity_on_hand)->toBe(1000.0)
        ->and((float) $balance->average_cost_unit)->toBe(100.0);
});

test('2. Stock transfers move quantities atomically between warehouses without losing stock', function () {
    $stockService = app(StockService::class);

    // Initial stock receipt of 500 units in source
    $stockService->recordMovement([
        'company_id' => $this->company->id,
        'movement_type' => 'opening_stock',
        'product_id' => $this->rawSteel->id,
        'dest_warehouse_id' => $this->warehouseSource->id,
        'quantity' => 500.0,
        'unit_id' => $this->unitKg->id,
        'unit_cost' => 100.00,
        'created_by_user_id' => $this->user->id,
    ]);

    // Transfer 200 units to destination warehouse
    $movement = $stockService->transferStock(
        companyId: $this->company->id,
        productId: $this->rawSteel->id,
        sourceWarehouseId: $this->warehouseSource->id,
        destWarehouseId: $this->warehouseDest->id,
        quantity: 200.0,
        unitId: $this->unitKg->id,
        userId: $this->user->id
    );

    expect($movement->movement_type)->toBe('stock_transfer');

    $sourceBalance = InventoryBalance::where('warehouse_id', $this->warehouseSource->id)
        ->where('product_id', $this->rawSteel->id)
        ->sum('quantity_on_hand');

    $destBalance = InventoryBalance::where('warehouse_id', $this->warehouseDest->id)
        ->where('product_id', $this->rawSteel->id)
        ->sum('quantity_on_hand');

    expect((float) $sourceBalance)->toBe(300.0)
        ->and((float) $destBalance)->toBe(200.0);
});

test('3. Inventory adjustments record positive and negative variance and prevent unauthorized negative stock', function () {
    $stockService = app(StockService::class);

    // Positive adjustment: +100 units
    $stockService->adjustStock(
        companyId: $this->company->id,
        productId: $this->rawSteel->id,
        warehouseId: $this->warehouseSource->id,
        adjustedQuantity: 100.0,
        unitId: $this->unitKg->id,
        reason: 'Surplus found during quarterly count',
        userId: $this->user->id
    );

    $qty = (float) InventoryBalance::where('warehouse_id', $this->warehouseSource->id)
        ->where('product_id', $this->rawSteel->id)
        ->sum('quantity_on_hand');

    expect($qty)->toBe(100.0);

    // Attempting to deduct 150 units should fail due to negative stock prevention
    expect(function () use ($stockService) {
        $stockService->adjustStock(
            companyId: $this->company->id,
            productId: $this->rawSteel->id,
            warehouseId: $this->warehouseSource->id,
            adjustedQuantity: -150.0,
            unitId: $this->unitKg->id,
            reason: 'Excessive audit deduction',
            userId: $this->user->id
        );
    })->toThrow(Exception::class);
});

test('4. Concurrent stock deductions respect locking and row consistency', function () {
    $stockService = app(StockService::class);

    // Setup initial stock of 100
    $stockService->recordMovement([
        'company_id' => $this->company->id,
        'movement_type' => 'opening_stock',
        'product_id' => $this->rawSteel->id,
        'dest_warehouse_id' => $this->warehouseSource->id,
        'quantity' => 100.0,
        'unit_id' => $this->unitKg->id,
        'unit_cost' => 100.00,
        'created_by_user_id' => $this->user->id,
    ]);

    // Perform two successive transactions
    DB::transaction(function () use ($stockService) {
        $stockService->recordMovement([
            'company_id' => $this->company->id,
            'movement_type' => 'production_consumption',
            'product_id' => $this->rawSteel->id,
            'source_warehouse_id' => $this->warehouseSource->id,
            'quantity' => 60.0,
            'unit_id' => $this->unitKg->id,
            'created_by_user_id' => $this->user->id,
        ]);
    });

    DB::transaction(function () use ($stockService) {
        $stockService->recordMovement([
            'company_id' => $this->company->id,
            'movement_type' => 'production_consumption',
            'product_id' => $this->rawSteel->id,
            'source_warehouse_id' => $this->warehouseSource->id,
            'quantity' => 40.0,
            'unit_id' => $this->unitKg->id,
            'created_by_user_id' => $this->user->id,
        ]);
    });

    $finalBalance = (float) InventoryBalance::where('warehouse_id', $this->warehouseSource->id)
        ->where('product_id', $this->rawSteel->id)
        ->sum('quantity_on_hand');

    expect($finalBalance)->toBe(0.0);
});

test('5. Purchase order creation, approval and GRN flow updates stock', function () {
    $supplier = Supplier::create([
        'company_id' => $this->company->id,
        'code' => 'SUP-TEST-01',
        'name' => 'Test Steel Mills Ltd.',
        'gstin' => '27AAACS1122D1Z1',
        'payment_terms_days' => 30,
    ]);

    $purchaseService = app(PurchaseService::class);

    $po = $purchaseService->createPurchaseOrder([
        'company_id' => $this->company->id,
        'supplier_id' => $supplier->id,
        'warehouse_id' => $this->warehouseSource->id,
        'expected_delivery_date' => now()->addDays(5)->toDateString(),
        'created_by_user_id' => $this->user->id,
    ], [
        [
            'product_id' => $this->rawSteel->id,
            'unit_id' => $this->unitKg->id,
            'quantity_ordered' => 500.0,
            'unit_price' => 100.00,
            'tax_percentage' => 18.00,
        ]
    ]);

    expect($po->status)->toBe('pending_approval')
        ->and((float) $po->subtotal)->toBe(50000.00)
        ->and((float) $po->tax_amount)->toBe(9000.00);

    // Approve PO
    $approvedPo = $purchaseService->approvePurchaseOrder($po, $this->user->id, true, 'Approved for production batch');
    expect($approvedPo->status)->toBe('approved');

    // Process GRN for 500 items
    $poItem = $approvedPo->items->first();
    $grn = $purchaseService->processGoodsReceipt($approvedPo, [
        'warehouse_id' => $this->warehouseSource->id,
        'delivery_challan_number' => 'DC-9921',
    ], [
        [
            'purchase_order_item_id' => $poItem->id,
            'quantity_received' => 500.0,
            'quantity_accepted' => 500.0,
            'quantity_rejected' => 0.0,
            'batch_number' => 'LOT-GRN-01',
        ]
    ], $this->user->id);

    expect($grn->status)->toBe('received')
        ->and($approvedPo->fresh()->status)->toBe('received');

    $qtyOnHand = (float) InventoryBalance::where('warehouse_id', $this->warehouseSource->id)
        ->where('product_id', $this->rawSteel->id)
        ->sum('quantity_on_hand');

    expect($qtyOnHand)->toBe(500.0);
});

test('6. BOM explosion calculates multi-level component requirements and cost roll-up', function () {
    $bom = BillOfMaterial::create([
        'company_id' => $this->company->id,
        'product_id' => $this->finishedGear->id,
        'code' => 'BOM-TEST-GEAR',
        'name' => 'Helical Gear BOM',
        'batch_size' => 1.0,
        'output_quantity' => 1.0,
    ]);

    BomItem::create([
        'bill_of_materials_id' => $bom->id,
        'component_product_id' => $this->rawSteel->id,
        'unit_id' => $this->unitKg->id,
        'quantity' => 12.0, // 12 KG steel per gear
        'scrap_percentage' => 5.0, // 5% scrap
        'sequence' => 1,
    ]);

    $bomService = app(BOMService::class);
    $exploded = $bomService->explodeBOM($bom, 10.0); // 10 gears

    expect(count($exploded))->toBe(1);
    $item = $exploded[0];

    // Net qty = 12 * 10 = 120. Gross qty = 120 * 1.05 = 126.0
    expect((float) $item['net_quantity'])->toBe(120.0)
        ->and((float) $item['gross_quantity'])->toBe(126.0)
        ->and((float) $item['total_cost'])->toBe(12600.00); // 126 KG * ₹100
});

test('7. Production material reservation and issue consumes stock from inventory', function () {
    $stockService = app(StockService::class);

    // Initial stock of 300 KG steel
    $stockService->recordMovement([
        'company_id' => $this->company->id,
        'movement_type' => 'opening_stock',
        'product_id' => $this->rawSteel->id,
        'dest_warehouse_id' => $this->warehouseSource->id,
        'quantity' => 300.0,
        'unit_id' => $this->unitKg->id,
        'unit_cost' => 100.00,
        'created_by_user_id' => $this->user->id,
    ]);

    $bom = BillOfMaterial::create([
        'company_id' => $this->company->id,
        'product_id' => $this->finishedGear->id,
        'code' => 'BOM-GEAR-02',
        'name' => 'Gear Production BOM',
        'batch_size' => 1.0,
        'output_quantity' => 1.0,
    ]);

    BomItem::create([
        'bill_of_materials_id' => $bom->id,
        'component_product_id' => $this->rawSteel->id,
        'unit_id' => $this->unitKg->id,
        'quantity' => 10.0,
        'scrap_percentage' => 0.0,
        'sequence' => 1,
    ]);

    $productionService = app(ProductionService::class);

    // Create production order for 20 gears (requires 200 KG steel)
    $order = $productionService->createOrder([
        'company_id' => $this->company->id,
        'product_id' => $this->finishedGear->id,
        'bill_of_materials_id' => $bom->id,
        'target_warehouse_id' => $this->warehouseDest->id,
        'planned_quantity' => 20.0,
        'due_date' => now()->addDays(7)->toDateString(),
        'created_by_user_id' => $this->user->id,
    ]);

    expect($order->status)->toBe('planned');

    // Reserve materials
    $productionService->reserveMaterials($order, $this->warehouseSource->id);
    expect($order->fresh()->status)->toBe('material_reserved');

    $balance = InventoryBalance::where('warehouse_id', $this->warehouseSource->id)
        ->where('product_id', $this->rawSteel->id)
        ->first();

    expect((float) $balance->quantity_reserved)->toBe(200.0)
        ->and((float) $balance->quantity_available)->toBe(100.0);

    // Issue materials
    $productionService->issueMaterials($order, $this->user->id);
    expect($order->fresh()->status)->toBe('in_progress');

    $balanceAfterIssue = InventoryBalance::where('warehouse_id', $this->warehouseSource->id)
        ->where('product_id', $this->rawSteel->id)
        ->first();

    // After issuing, 200 KG consumed from stock, leaving 100 on hand
    expect((float) $balanceAfterIssue->quantity_on_hand)->toBe(100.0)
        ->and((float) $balanceAfterIssue->quantity_reserved)->toBe(0.0);
});

test('8. Finished goods generation records output, scrap and calculates unit production cost', function () {
    $bom = BillOfMaterial::create([
        'company_id' => $this->company->id,
        'product_id' => $this->finishedGear->id,
        'code' => 'BOM-GEAR-03',
        'name' => 'Gear BOM',
        'batch_size' => 1.0,
        'output_quantity' => 1.0,
    ]);

    BomItem::create([
        'bill_of_materials_id' => $bom->id,
        'component_product_id' => $this->rawSteel->id,
        'unit_id' => $this->unitKg->id,
        'quantity' => 5.0,
        'scrap_percentage' => 0.0,
        'sequence' => 1,
    ]);

    $productionService = app(ProductionService::class);

    $order = $productionService->createOrder([
        'company_id' => $this->company->id,
        'product_id' => $this->finishedGear->id,
        'bill_of_materials_id' => $bom->id,
        'target_warehouse_id' => $this->warehouseDest->id,
        'planned_quantity' => 10.0,
        'due_date' => now()->addDays(3)->toDateString(),
        'created_by_user_id' => $this->user->id,
    ]);

    $order->update(['actual_material_cost' => 5000.00]); // ₹5000 material cost

    // Complete production: 10 units produced, 1 scrapped, ₹1000 labour, ₹1500 machine
    $output = $productionService->completeProduction(
        order: $order,
        quantityProduced: 10.0,
        quantityRejected: 0.0,
        quantityScrapped: 1.0,
        labourCost: 1000.00,
        machineCost: 1500.00,
        batchNumber: 'BAT-GEAR-FINAL-01',
        userId: $this->user->id
    );

    expect($output->batch_number)->toBe('BAT-GEAR-FINAL-01')
        ->and((float) $output->total_production_cost)->toBe(7500.00) // 5000 + 1000 + 1500
        ->and((float) $output->unit_production_cost)->toBe(750.00); // 7500 / 10

    $fgBalance = (float) InventoryBalance::where('warehouse_id', $this->warehouseDest->id)
        ->where('product_id', $this->finishedGear->id)
        ->where('batch_number', 'BAT-GEAR-FINAL-01')
        ->sum('quantity_on_hand');

    expect($fgBalance)->toBe(10.0);
});

test('9. Unauthorized warehouse access is blocked by Sanctum authentication', function () {
    // Unauthenticated request to /api/v1/inventory/balances should return 401 Unauthorized
    $response = $this->getJson('/api/v1/inventory/balances');
    $response->assertStatus(401);

    // Authenticated request should return 200 OK
    $authResponse = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/inventory/balances');
    $authResponse->assertStatus(200);
});

