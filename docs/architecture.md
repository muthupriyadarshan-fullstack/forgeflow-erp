# ForgeFlow Industrial Manufacturing ERP - Architecture & Domain Model

## 1. Domain-Driven Modular Design

ForgeFlow is structured into isolated domain services to keep business logic out of controllers and decoupled from HTTP layers:

- **Inventory Domain** (`App\Services\Inventory\StockService`):
  - Manages atomic stock transitions.
  - Implements pessimistic concurrency control via `DB::transaction()` and `->lockForUpdate()`.
  - Dispatches `StockMovedEvent` and `LowStockDetectedEvent`.
  - Enforces ledger immutability: records in `stock_movements` cannot be updated or deleted.

- **Manufacturing & BOM Domain** (`App\Services\Production\BOMService`, `App\Services\Production\ProductionService`):
  - Recursive multi-level Bill of Materials explosion.
  - Real-time stock shortage & bottleneck calculation across plant warehouses.
  - 3-Phase shop floor execution: `reserveMaterials` &rarr; `issueMaterials` &rarr; `completeProduction`.
  - Tracks scrap percentages and accounts for direct labour and machine run-time costs.

- **Procurement Domain** (`App\Services\Purchase\PurchaseService`):
  - Two-step PO approval workflow.
  - Goods Receipt Note (GRN) verification with inspection quantities (accepted vs rejected).
  - Automatically updates PO fulfillment status and generates `SupplierInvoice` matched to vendor credit terms.

- **Finance & Costing Domain** (`App\Services\Finance\ReportService`):
  - Moving weighted average inventory valuation.
  - Aggregates operational KPIs (total production, scrap rates, procurement spend, efficiency).
  - Streams CSV exports directly from memory for large datasets.

## 2. Concurrency & Integrity Guarantees

```
[ Request: Deduct Stock ]
         │
         ▼
[ DB::transaction() ]
         │
         ▼
[ InventoryBalance::where(...)->lockForUpdate()->first() ]
         │
         ├──> Stock on hand < Requested? ──(Yes)──> Throw InsufficientStockException (Rollback)
         │
         └──> (No)
                ├──> Decrement on_hand & available
                ├──> Insert immutable StockMovement record
                └──> Dispatch StockMovedEvent & Check Low Stock Buffer
         │
         ▼
[ Commit Transaction & Release Locks ]
```

## 3. Indian Manufacturing Standards Compliance

- **Currency**: Indian Rupee (`INR` / `₹`) with Lakh and Crore formatting.
- **Taxation**: 18% standard Indian Goods & Services Tax (GST) calculated and tracked across supplier invoices and purchase orders.
- **Tax Identifiers**: 15-character GSTIN (e.g. `27AABCF1234F1Z5`), 10-character PAN, and Corporate Identification Numbers (CIN).
- **Metric System**: MT (Metric Ton), KG (Kilogram), Metre, Litre, PCS.
- **Classification**: Harmonized System of Nomenclature (HSN) codes assigned to all steel billets, alloy bars, hydraulic components, and gearboxes.

