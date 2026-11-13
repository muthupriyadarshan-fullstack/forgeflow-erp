import {
    BillOfMaterial,
    DashboardData,
    InventoryBalance,
    Product,
    ProductCategory,
    ProductionOrder,
    PurchaseOrder,
    StockMovement,
    Supplier,
    Unit,
    User,
    Warehouse,
} from '../types/erp';

const API_BASE = '/api/v1';

class ApiService {
    private token: string | null = null;

    constructor() {
        this.token = localStorage.getItem('forgeflow_token');
    }

    setToken(token: string | null) {
        this.token = token;
        if (token) {
            localStorage.setItem('forgeflow_token', token);
        } else {
            localStorage.removeItem('forgeflow_token');
        }
    }

    getToken(): string | null {
        return this.token;
    }

    private async request<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
        const headers: Record<string, string> = {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(options.headers as Record<string, string>),
        };

        if (this.token) {
            headers.Authorization = `Bearer ${this.token}`;
        }

        const res = await fetch(`${API_BASE}${endpoint}`, {
            ...options,
            headers,
        });

        if (!res.ok) {
            const errData = await res.json().catch(() => ({ message: 'Server error' }));
            throw new Error(errData.message || `Request failed with status ${res.status}`);
        }

        return res.json();
    }

    // Auth
    async login(email: string, password: string): Promise<{ token: string; user: User }> {
        const data = await this.request<{ token: string; user: User }>('/auth/login', {
            method: 'POST',
            body: JSON.stringify({ email, password }),
        });
        this.setToken(data.token);
        return data;
    }

    async getMe(): Promise<{ user: User }> {
        return this.request<{ user: User }>('/auth/me');
    }

    async logout(): Promise<void> {
        try {
            await this.request('/auth/logout', { method: 'POST' });
        } finally {
            this.setToken(null);
        }
    }

    // Dashboard
    async getDashboard(): Promise<DashboardData> {
        return this.request<DashboardData>('/dashboard');
    }

    // Products & BOM
    async getProducts(params?: Record<string, string>): Promise<{ data: Product[] }> {
        const qs = params ? '?' + new URLSearchParams(params).toString() : '';
        return this.request<{ data: Product[] }>(`/products${qs}`);
    }

    async getProduct(id: number): Promise<Product> {
        return this.request<Product>(`/products/${id}`);
    }

    async getCategories(): Promise<ProductCategory[]> {
        return this.request<ProductCategory[]>('/categories');
    }

    async getUnits(): Promise<Unit[]> {
        return this.request<Unit[]>('/units');
    }

    async getBOMs(): Promise<{ data: BillOfMaterial[] }> {
        return this.request<{ data: BillOfMaterial[] }>('/boms');
    }

    async getBOM(id: number): Promise<{ bom: BillOfMaterial; calculated_material_cost: number }> {
        return this.request<{ bom: BillOfMaterial; calculated_material_cost: number }>(`/boms/${id}`);
    }

    async explodeBOM(id: number, quantity: number = 1.0): Promise<any> {
        return this.request<any>(`/boms/${id}/explode?quantity=${quantity}`);
    }

    async checkBOMAvailability(id: number, quantity: number = 1.0, warehouseId?: number): Promise<any> {
        const wh = warehouseId ? `&warehouse_id=${warehouseId}` : '';
        return this.request<any>(`/boms/${id}/check-availability?quantity=${quantity}${wh}`);
    }

    // Inventory & Ledger
    async getInventoryBalances(params?: Record<string, string>): Promise<{ data: InventoryBalance[] }> {
        const qs = params ? '?' + new URLSearchParams(params).toString() : '';
        return this.request<{ data: InventoryBalance[] }>(`/inventory/balances${qs}`);
    }

    async getStockMovements(params?: Record<string, string>): Promise<{ data: StockMovement[] }> {
        const qs = params ? '?' + new URLSearchParams(params).toString() : '';
        return this.request<{ data: StockMovement[] }>(`/inventory/movements${qs}`);
    }

    async transferStock(data: {
        product_id: number;
        source_warehouse_id: number;
        dest_warehouse_id: number;
        quantity: number;
        unit_id: number;
        reason?: string;
    }): Promise<any> {
        return this.request('/inventory/transfer', {
            method: 'POST',
            body: JSON.stringify(data),
        });
    }

    async adjustStock(data: {
        product_id: number;
        warehouse_id: number;
        quantity: number;
        unit_id: number;
        reason: string;
    }): Promise<any> {
        return this.request('/inventory/adjust', {
            method: 'POST',
            body: JSON.stringify(data),
        });
    }

    async getWarehouses(): Promise<Warehouse[]> {
        return this.request<Warehouse[]>('/inventory/warehouses');
    }

    // Purchasing & GRN
    async getPurchaseOrders(params?: Record<string, string>): Promise<{ data: PurchaseOrder[] }> {
        const qs = params ? '?' + new URLSearchParams(params).toString() : '';
        return this.request<{ data: PurchaseOrder[] }>(`/purchase/orders${qs}`);
    }

    async getPurchaseOrder(id: number): Promise<PurchaseOrder> {
        return this.request<PurchaseOrder>(`/purchase/orders/${id}`);
    }

    async approvePurchaseOrder(id: number, approved: boolean, comments?: string): Promise<any> {
        return this.request(`/purchase/orders/${id}/approve`, {
            method: 'POST',
            body: JSON.stringify({ approved, comments }),
        });
    }

    async processGRN(orderId: number, data: any): Promise<any> {
        return this.request(`/purchase/orders/${orderId}/grn`, {
            method: 'POST',
            body: JSON.stringify(data),
        });
    }

    async getSuppliers(): Promise<Supplier[]> {
        return this.request<Supplier[]>('/purchase/suppliers');
    }

    // Production
    async getProductionOrders(params?: Record<string, string>): Promise<{ data: ProductionOrder[] }> {
        const qs = params ? '?' + new URLSearchParams(params).toString() : '';
        return this.request<{ data: ProductionOrder[] }>(`/production/orders${qs}`);
    }

    async getProductionOrder(id: number): Promise<ProductionOrder> {
        return this.request<ProductionOrder>(`/production/orders/${id}`);
    }

    async reserveMaterials(orderId: number): Promise<any> {
        return this.request(`/production/orders/${orderId}/reserve`, {
            method: 'POST',
            body: JSON.stringify({}),
        });
    }

    async issueMaterials(orderId: number): Promise<any> {
        return this.request(`/production/orders/${orderId}/issue`, {
            method: 'POST',
            body: JSON.stringify({}),
        });
    }

    async completeProduction(orderId: number, data: any): Promise<any> {
        return this.request(`/production/orders/${orderId}/complete`, {
            method: 'POST',
            body: JSON.stringify(data),
        });
    }

    async getMachines(): Promise<any[]> {
        return this.request<any[]>('/production/machines');
    }

    async getEmployees(): Promise<any[]> {
        return this.request<any[]>('/production/employees');
    }

    // Reports & Valuation
    async getValuationReport(): Promise<any> {
        return this.request<any>('/reports/valuation');
    }

    getExportUrl(type: string): string {
        return `${API_BASE}/reports/export-csv?type=${type}`;
    }

    // Audit
    async getAuditLogs(): Promise<{ data: any[] }> {
        return this.request<{ data: any[] }>('/audit-logs');
    }
}

export const api = new ApiService();

