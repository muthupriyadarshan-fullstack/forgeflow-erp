# ForgeFlow ERP - Relational Database Entity-Relationship Diagram

The ForgeFlow architecture enforces strict referential integrity, decimal precision for manufacturing weights and currency, and an immutable double-entry stock ledger.

```mermaid
erDiagram
    COMPANIES ||--o{ USERS : employs
    COMPANIES ||--o{ WAREHOUSES : operates
    COMPANIES ||--o{ PRODUCTS : catalogs
    COMPANIES ||--o{ SUPPLIERS : contracts
    COMPANIES ||--o{ PURCHASE_ORDERS : issues
    COMPANIES ||--o{ PRODUCTION_ORDERS : schedules
    COMPANIES ||--o{ STOCK_MOVEMENTS : audits

    WAREHOUSES ||--o{ WAREHOUSE_LOCATIONS : contains
    WAREHOUSES ||--o{ INVENTORY_BALANCES : holds
    WAREHOUSES ||--o{ STOCK_MOVEMENTS : routes

    PRODUCTS ||--o{ PRODUCT_VARIANTS : has
    PRODUCTS ||--o{ BILLS_OF_MATERIALS : defines
    PRODUCTS ||--o{ INVENTORY_BALANCES : maintains
    PRODUCTS ||--o{ STOCK_MOVEMENTS : tracks
    UNITS ||--o{ PRODUCTS : measures

    BILLS_OF_MATERIALS ||--o{ BOM_ITEMS : contains
    PRODUCTS ||--o{ BOM_ITEMS : supplies
    BILLS_OF_MATERIALS ||--o{ PRODUCTION_ORDERS : instructs

    SUPPLIERS ||--o{ PURCHASE_ORDERS : receives
    PURCHASE_ORDERS ||--o{ PURCHASE_ORDER_ITEMS : specifies
    PURCHASE_ORDERS ||--o{ GOODS_RECEIPTS : generates
    GOODS_RECEIPTS ||--o{ GOODS_RECEIPT_ITEMS : verifies
    PURCHASE_ORDERS ||--o{ SUPPLIER_INVOICES : bills

    PRODUCTION_ORDERS ||--o{ PRODUCTION_STAGES : progresses
    MACHINES ||--o{ PRODUCTION_STAGES : allocates
    EMPLOYEES ||--o{ PRODUCTION_STAGES : assigned
    PRODUCTION_ORDERS ||--o{ MATERIAL_RESERVATIONS : reserves
    PRODUCTION_ORDERS ||--o{ MATERIAL_ISSUES : consumes
    PRODUCTION_ORDERS ||--o{ PRODUCTION_OUTPUTS : produces

    COMPANIES {
        bigint id PK
        string name
        string gstin
        string pan
        string currency
    }

    WAREHOUSES {
        bigint id PK
        bigint company_id FK
        string code
        string name
        string type
    }

    PRODUCTS {
        bigint id PK
        string sku
        string name
        string type
        decimal standard_cost
        decimal min_stock_level
        decimal reorder_level
    }

    INVENTORY_BALANCES {
        bigint id PK
        bigint warehouse_id FK
        bigint product_id FK
        string batch_number
        decimal quantity_on_hand
        decimal quantity_reserved
        decimal quantity_available
        decimal average_cost_unit
    }

    STOCK_MOVEMENTS {
        bigint id PK
        string movement_number
        string movement_type
        bigint product_id FK
        bigint source_warehouse_id FK
        bigint dest_warehouse_id FK
        decimal quantity
        decimal unit_cost
        decimal total_cost
        timestamp created_at
    }

    PURCHASE_ORDERS {
        bigint id PK
        string po_number
        bigint supplier_id FK
        bigint warehouse_id FK
        decimal subtotal
        decimal tax_amount
        decimal total_amount
        string status
        string approval_status
    }

    PRODUCTION_ORDERS {
        bigint id PK
        string order_number
        bigint product_id FK
        bigint bill_of_materials_id FK
        decimal planned_quantity
        decimal produced_quantity
        decimal actual_cost
        string status
    }
```

## Immutable Double-Entry Ledger Principles

1. **Non-destructive Audit Ledger**: Once a row is written into `stock_movements`, Eloquent model events prevent any update or deletion.
2. **Row Locking (`lockForUpdate`)**: When adjusting or transferring stock, target `inventory_balances` records are locked inside a database transaction to prevent race conditions during concurrent shop floor issues.
3. **Negative Stock Prevention**: Stock on hand cannot drop below zero unless explicitly bypassed by an authorized system process.
4. **Weighted Average Valuation**: Every inbound movement re-computes `average_cost_unit = ((old_qty * old_cost) + (new_qty * new_cost)) / (old_qty + new_qty)`.

