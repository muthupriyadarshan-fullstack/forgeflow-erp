# ForgeFlow – Industrial Manufacturing ERP System

![ForgeFlow Banner](https://img.shields.io/badge/Architecture-Modular%20Domain--Driven-blue?style=for-the-badge)
![Pest PHP](https://img.shields.io/badge/Pest%20Tests-9%2F9%20Passing-success?style=for-the-badge)
![Larastan](https://img.shields.io/badge/Larastan-Level%205%20Passed-brightgreen?style=for-the-badge)
![Laravel](https://img.shields.io/badge/Laravel-13-red?style=for-the-badge&logo=laravel)
![React](https://img.shields.io/badge/React-19%20%2B%20TypeScript-blue?style=for-the-badge&logo=react)
![Tailwind](https://img.shields.io/badge/Tailwind-CSS%20v4-38bdf8?style=for-the-badge&logo=tailwindcss)

**ForgeFlow** is a comprehensive, enterprise-grade industrial manufacturing ERP system designed for heavy engineering, precision forging, CNC machining, and discrete assembly plants.

The platform provides end-to-end control over the manufacturing lifecycle: from raw material procurement, supplier management, multi-level Bills of Materials (BOM), pessimistic row-locking inventory ledgers, shop-floor work orders, machine & labour tracking, to moving weighted average inventory valuation and financial reports.

---

## 🏭 Enterprise Capabilities & Modules

### 1. Advanced Double-Entry Stock Ledger
- **Ledger Immutability**: Historical movements in `stock_movements` cannot be updated or deleted silently.
- **Pessimistic Concurrency**: Uses `lockForUpdate()` inside database transactions to eliminate race conditions during concurrent shop floor issues.
- **Negative Stock Prevention**: Stock on hand cannot drop below zero unless explicitly bypassed.
- **Location-Level Granularity**: Tracks inventory by warehouse, zone, aisle, rack, and bin location.
- **Valuation**: Real-time moving weighted average unit cost calculation upon every goods receipt.

### 2. Manufacturing & Multi-Level BOM Engine
- **Recursive BOM Explosion**: Explodes complex multi-level subassemblies into component raw materials.
- **Scrap & Wastage Accounting**: Incorporates component-level scrap allowances into material requirements planning (MRP).
- **Material Bottleneck Check**: Verifies raw material availability across all plant warehouses before scheduling.
- **3-Phase Work Order Workflow**:
  1. `Reserve Materials`: Locks stock rows and reserves quantities.
  2. `Issue to Shop Floor`: Moves inventory into production consumption.
  3. `Complete Production`: Accounts for rejected/scrapped goods, records labour/machine costs, and stocks finished goods with batch tracking.

### 3. Procurement & Indian GST Compliance
- **Taxation & Format**: Full Indian context with INR (`₹`), 18% standard GST, 15-character GSTIN, PAN, and HSN codes.
- **Two-Step Approval**: PO submission &rarr; Manager approval &rarr; Goods Receipt Note (GRN).
- **Inspection**: Inspection quantities (received, accepted, rejected with rejection reason) and automatic `SupplierInvoice` generation.

---

## 👥 Demo User Accounts (All 8 Enterprise Roles)

All accounts share the default password: **`Password123!`**
*(You can also use the **Quick Role Switcher** dropdown in the top-right header of the web interface to switch roles with one click!)*

| # | Role | Email | Department | Key Permissions |
|---|------|-------|------------|-----------------|
| 1 | **Super Admin** | `superadmin@forgeflow.in` | Executive | Full Enterprise Access |
| 2 | **Company Admin** | `admin@forgeflow.in` | Plant Operations | Full Plant Administration |
| 3 | **Production Manager**| `production@forgeflow.in`| Manufacturing | Work Orders, Stages, BOMs |
| 4 | **Inventory Manager** | `inventory@forgeflow.in` | Materials & Stores | Stock Transfers, Adjustments |
| 5 | **Purchase Manager**  | `purchase@forgeflow.in`  | Procurement | Suppliers, POs, Approvals |
| 6 | **Warehouse Staff**   | `warehouse@forgeflow.in` | Logistics | GRN Receipts, Material Issues |
| 7 | **Finance Manager**   | `finance@forgeflow.in`   | Accounts & Finance | Invoices, Valuation, CSV |
| 8 | **Employee**          | `employee@forgeflow.in`  | Shop Floor | View Tasks, Stages |

---

## 🚀 Quick Start (Local Setup)

### Prerequisites
- PHP 8.3+ with extensions: `pdo_sqlite`, `pdo_pgsql`, `mbstring`, `intl`, `bcmath`, `curl`, `zip`
- Composer 2.x
- Node.js 20+ & npm

### Installation Steps

1. **Clone and Install PHP Dependencies**:
   ```bash
   composer install
   ```

2. **Setup Environment**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Run Migrations & Seed Realistic Industrial Data**:
   ```bash
   php artisan migrate:fresh --seed
   ```

4. **Install & Build Frontend Assets**:
   ```bash
   npm install
   npm run build
   ```

5. **Start Local Development Server**:
   ```bash
   php artisan serve
   ```
   Open your browser at [http://localhost:8000](http://localhost:8000).

---

## 🐳 Docker Compose Setup

Run the entire stack (PHP 8.3-FPM, Nginx, PostgreSQL 16, Redis, Horizon Worker) with a single command:

```bash
docker-compose up -d --build
docker-compose exec app php artisan migrate --seed
```

The application will be accessible at [http://localhost:8000](http://localhost:8000).

---

## 🧪 Automated Testing & Verification

The test suite covers the 9 critical business workflows:

```bash
./vendor/bin/pest
```

### Verified Scenarios:
1. `Stock receipt`: Creates immutable movement and recalculates weighted average unit cost.
2. `Stock transfers`: Relocates stock atomically between warehouses without balance leakage.
3. `Inventory adjustments`: Adjusts variance and enforces negative stock prohibition.
4. `Concurrent deductions`: Row locking prevents double-spend race conditions.
5. `Purchase order flow`: PO creation &rarr; Manager approval &rarr; GRN inspection &rarr; Stock balance credit.
6. `BOM calculations`: Recursive multi-level explosion and scrap percentage calculation.
7. `Material consumption`: Material reservation &rarr; Shop floor issue &rarr; Stock deduction.
8. `Finished goods generation`: Output recording, scrap calculation, batch allocation, and unit cost roll-up.
9. `Unauthorized access`: Sanctum token gate blocking unauthenticated access.

### Static Analysis:
```bash
./vendor/bin/phpstan analyse --memory-limit=1G
```
*Result: **0 errors** (Level 5).*

---

## 📁 Key Documentation
- [Architecture & Concurrency Walkthrough](docs/architecture.md)
- [Database ER Diagram & Relationships](docs/database-er-diagram.md)
- [OpenAPI 3.0 API Specification](docs/openapi.yaml)
- [CI Pipeline Workflow](.github/workflows/ci.yml)

---

## 📄 License
ForgeFlow is proprietary enterprise software developed for advanced manufacturing operations.
