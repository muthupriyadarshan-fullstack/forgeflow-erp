export type RoleName =
    | 'Super Admin'
    | 'Company Admin'
    | 'Production Manager'
    | 'Inventory Manager'
    | 'Purchase Manager'
    | 'Warehouse Staff'
    | 'Finance Manager'
    | 'Employee';

export interface User {
    id: number;
    name: string;
    email: string;
    employee_code?: string;
    department?: string;
    roles: RoleName[];
    permissions: string[];
}

export interface Company {
    id: number;
    name: string;
    legal_name?: string;
    gstin?: string;
    pan?: string;
    city?: string;
    state?: string;
    currency: string;
}

export interface Unit {
    id: number;
    name: string;
    symbol: string;
    type: string;
}

export interface ProductCategory {
    id: number;
    code: string;
    name: string;
    products_count?: number;
}

export interface Product {
    id: number;
    sku: string;
    name: string;
    type: 'raw_material' | 'work_in_progress' | 'finished_good' | 'consumable';
    description?: string;
    hsn_code?: string;
    standard_cost: number;
    selling_price: number;
    min_stock_level: number;
    reorder_level: number;
    lead_time_days: number;
    unit?: Unit;
    category?: ProductCategory;
    total_quantity_on_hand?: number;
    total_quantity_available?: number;
}

export interface BomItem {
    id: number;
    component_product?: Product;
    unit?: Unit;
    quantity: number;
    scrap_percentage: number;
    sequence: number;
    is_sub_assembly: boolean;
}

export interface BillOfMaterial {
    id: number;
    product_id: number;
    code: string;
    name: string;
    version: string;
    batch_size: number;
    output_quantity: number;
    is_active: boolean;
    product?: Product;
    items?: BomItem[];
    calculated_material_cost?: number;
}

export interface WarehouseLocation {
    id: number;
    warehouse_id: number;
    code: string;
    name: string;
    zone?: string;
    location_type: string;
}

export interface Warehouse {
    id: number;
    code: string;
    name: string;
    type: string;
    city?: string;
    locations?: WarehouseLocation[];
    balances_count?: number;
}

export interface InventoryBalance {
    id: number;
    warehouse_id: number;
    product_id: number;
    batch_number?: string;
    quantity_on_hand: number;
    quantity_reserved: number;
    quantity_available: number;
    average_cost_unit: number;
    product?: Product;
    warehouse?: Warehouse;
    location?: WarehouseLocation;
}

export interface StockMovement {
    id: number;
    movement_number: string;
    movement_type:
        | 'opening_stock'
        | 'purchase_receipt'
        | 'production_consumption'
        | 'production_output'
        | 'stock_transfer'
        | 'stock_adjustment'
        | 'damaged_goods'
        | 'returned_goods';
    product?: Product;
    source_warehouse?: Warehouse;
    dest_warehouse?: Warehouse;
    quantity: number;
    unit_cost: number;
    total_cost: number;
    batch_number?: string;
    reason?: string;
    created_at: string;
}

export interface Supplier {
    id: number;
    code: string;
    name: string;
    gstin?: string;
    email?: string;
    phone?: string;
    city?: string;
    payment_terms_days: number;
    rating: number;
    purchase_orders_count?: number;
}

export interface PurchaseOrderItem {
    id: number;
    product_id: number;
    product?: Product;
    quantity_ordered: number;
    quantity_received: number;
    unit_price: number;
    tax_percentage: number;
    tax_amount: number;
    line_total: number;
}

export interface PurchaseOrder {
    id: number;
    po_number: string;
    supplier_id: number;
    warehouse_id: number;
    order_date: string;
    expected_delivery_date: string;
    subtotal: number;
    tax_amount: number;
    shipping_amount: number;
    total_amount: number;
    currency: string;
    status: 'draft' | 'pending_approval' | 'approved' | 'partially_received' | 'received' | 'cancelled';
    approval_status: 'pending' | 'approved' | 'rejected';
    supplier?: Supplier;
    warehouse?: Warehouse;
    items?: PurchaseOrderItem[];
}

export interface ProductionStage {
    id: number;
    stage_name: string;
    sequence: number;
    status: 'pending' | 'in_progress' | 'completed' | 'skipped';
    planned_duration_minutes: number;
}

export interface ProductionOrder {
    id: number;
    order_number: string;
    product_id: number;
    product?: Product;
    bill_of_material?: BillOfMaterial;
    target_warehouse?: Warehouse;
    planned_quantity: number;
    produced_quantity: number;
    rejected_quantity: number;
    scrapped_quantity: number;
    status: 'draft' | 'planned' | 'material_reserved' | 'in_progress' | 'completed' | 'cancelled';
    priority: 'low' | 'normal' | 'high' | 'urgent';
    start_date: string;
    due_date: string;
    estimated_cost: number;
    actual_material_cost: number;
    actual_labour_cost: number;
    actual_machine_cost: number;
    actual_cost: number;
    stages?: ProductionStage[];
}

export interface DashboardMetrics {
    total_production_volume: number;
    total_production_value: number;
    raw_material_value: number;
    raw_material_qty: number;
    finished_goods_value: number;
    finished_goods_qty: number;
    total_inventory_valuation: number;
    pending_purchase_orders_count: number;
    pending_purchase_orders_value: number;
    monthly_procurement_value: number;
    production_efficiency_pct: number;
    scrap_rate_pct: number;
    low_stock_count: number;
}

export interface DashboardData {
    metrics: DashboardMetrics;
    low_stock_items: Array<{
        id: number;
        sku: string;
        name: string;
        category: string;
        current_stock: number;
        reorder_level: number;
        unit: string;
    }>;
    monthly_trends: Array<{
        month: string;
        procurement: number;
        production: number;
    }>;
    recent_movements: StockMovement[];
}

