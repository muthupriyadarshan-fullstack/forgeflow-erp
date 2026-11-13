<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AuditController;
use App\Http\Controllers\Api\V1\BOMController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductionController;
use App\Http\Controllers\Api\V1\PurchaseController;
use App\Http\Controllers\Api\V1\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ForgeFlow API v1 Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Public Authentication
    Route::post('/auth/login', [AuthController::class, 'login'])->name('api.v1.auth.login');

    // Protected Routes (Sanctum)
    Route::middleware('auth:sanctum')->group(function () {
        // User profile & logout
        Route::get('/auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');

        // Dashboard Metrics & KPIs
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('api.v1.dashboard');

        // Products, Categories & Units
        Route::get('/products', [ProductController::class, 'index'])->name('api.v1.products.index');
        Route::post('/products', [ProductController::class, 'store'])->name('api.v1.products.store');
        Route::get('/products/{product}', [ProductController::class, 'show'])->name('api.v1.products.show');
        Route::get('/categories', [ProductController::class, 'categories'])->name('api.v1.categories.index');
        Route::get('/units', [ProductController::class, 'units'])->name('api.v1.units.index');

        // Bills of Materials (BOM)
        Route::get('/boms', [BOMController::class, 'index'])->name('api.v1.boms.index');
        Route::post('/boms', [BOMController::class, 'store'])->name('api.v1.boms.store');
        Route::get('/boms/{bom}', [BOMController::class, 'show'])->name('api.v1.boms.show');
        Route::get('/boms/{bom}/explode', [BOMController::class, 'explode'])->name('api.v1.boms.explode');
        Route::get('/boms/{bom}/check-availability', [BOMController::class, 'checkAvailability'])->name('api.v1.boms.check-availability');

        // Inventory Management & Stock Ledger
        Route::get('/inventory/balances', [InventoryController::class, 'balances'])->name('api.v1.inventory.balances');
        Route::get('/inventory/movements', [InventoryController::class, 'movements'])->name('api.v1.inventory.movements');
        Route::post('/inventory/transfer', [InventoryController::class, 'transfer'])->name('api.v1.inventory.transfer');
        Route::post('/inventory/adjust', [InventoryController::class, 'adjust'])->name('api.v1.inventory.adjust');
        Route::get('/inventory/warehouses', [InventoryController::class, 'warehouses'])->name('api.v1.inventory.warehouses');

        // Purchasing & Supplier Management
        Route::get('/purchase/orders', [PurchaseController::class, 'indexOrders'])->name('api.v1.purchase.orders.index');
        Route::post('/purchase/orders', [PurchaseController::class, 'storeOrder'])->name('api.v1.purchase.orders.store');
        Route::get('/purchase/orders/{order}', [PurchaseController::class, 'showOrder'])->name('api.v1.purchase.orders.show');
        Route::post('/purchase/orders/{order}/approve', [PurchaseController::class, 'approveOrder'])->name('api.v1.purchase.orders.approve');
        Route::post('/purchase/orders/{order}/grn', [PurchaseController::class, 'processGrn'])->name('api.v1.purchase.orders.grn');
        Route::get('/purchase/suppliers', [PurchaseController::class, 'suppliers'])->name('api.v1.purchase.suppliers.index');
        Route::post('/purchase/suppliers', [PurchaseController::class, 'storeSupplier'])->name('api.v1.purchase.suppliers.store');
        Route::get('/purchase/requisitions', [PurchaseController::class, 'requisitions'])->name('api.v1.purchase.requisitions.index');

        // Production Management & Shop Floor
        Route::get('/production/orders', [ProductionController::class, 'index'])->name('api.v1.production.orders.index');
        Route::post('/production/orders', [ProductionController::class, 'store'])->name('api.v1.production.orders.store');
        Route::get('/production/orders/{order}', [ProductionController::class, 'show'])->name('api.v1.production.orders.show');
        Route::post('/production/orders/{order}/reserve', [ProductionController::class, 'reserve'])->name('api.v1.production.orders.reserve');
        Route::post('/production/orders/{order}/issue', [ProductionController::class, 'issue'])->name('api.v1.production.orders.issue');
        Route::post('/production/orders/{order}/complete', [ProductionController::class, 'complete'])->name('api.v1.production.orders.complete');
        Route::get('/production/machines', [ProductionController::class, 'machines'])->name('api.v1.production.machines.index');
        Route::get('/production/employees', [ProductionController::class, 'employees'])->name('api.v1.production.employees.index');

        // Finance, Valuation & Reports
        Route::get('/reports/valuation', [ReportController::class, 'valuation'])->name('api.v1.reports.valuation');
        Route::get('/reports/export-csv', [ReportController::class, 'exportCsv'])->name('api.v1.reports.export-csv');

        // Audit Trail Logs
        Route::get('/audit-logs', [AuditController::class, 'index'])->name('api.v1.audit-logs.index');
    });
});
