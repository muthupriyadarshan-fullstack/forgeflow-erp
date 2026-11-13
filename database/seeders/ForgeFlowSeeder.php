<?php

namespace Database\Seeders;

use App\Models\BillOfMaterial;
use App\Models\BomItem;
use App\Models\Company;
use App\Models\Employee;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryBalance;
use App\Models\Machine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\ProductionOutput;
use App\Models\ProductionStage;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequisition;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Models\SupplierInvoice;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ForgeFlowSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Roles and Permissions
        $permissions = [
            'inventory:view', 'inventory:create', 'inventory:transfer', 'inventory:adjust',
            'production:view', 'production:create', 'production:reserve', 'production:issue', 'production:complete',
            'purchase:view', 'purchase:create', 'purchase:approve', 'purchase:grn',
            'product:view', 'product:create', 'product:edit',
            'bom:view', 'bom:create',
            'finance:view', 'reports:export',
            'audit:view', 'users:manage'
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $rolesData = [
            'Super Admin' => $permissions,
            'Company Admin' => $permissions,
            'Production Manager' => [
                'production:view', 'production:create', 'production:reserve', 'production:issue', 'production:complete',
                'bom:view', 'bom:create', 'inventory:view', 'product:view', 'reports:export'
            ],
            'Inventory Manager' => [
                'inventory:view', 'inventory:create', 'inventory:transfer', 'inventory:adjust',
                'product:view', 'product:create', 'product:edit', 'purchase:view', 'reports:export'
            ],
            'Purchase Manager' => [
                'purchase:view', 'purchase:create', 'purchase:approve', 'purchase:grn',
                'inventory:view', 'product:view', 'reports:export'
            ],
            'Warehouse Staff' => [
                'inventory:view', 'inventory:transfer', 'purchase:grn', 'production:issue'
            ],
            'Finance Manager' => [
                'finance:view', 'reports:export', 'purchase:view', 'purchase:approve', 'audit:view'
            ],
            'Employee' => [
                'production:view', 'inventory:view'
            ],
        ];

        $createdRoles = [];
        foreach ($rolesData as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($perms);
            $createdRoles[$roleName] = $role;
        }

        // 2. Company Profile
        $company = Company::create([
            'name' => 'ForgeFlow Precision Engineering Ltd.',
            'legal_name' => 'ForgeFlow Heavy Forgings & Precision Systems India Private Limited',
            'cin_number' => 'U28910PN2015PTC154210',
            'gstin' => '27AABCF1234F1Z5',
            'pan' => 'AABCF1234F',
            'email' => 'contact@forgeflow.in',
            'phone' => '+91-20-6712-4000',
            'website' => 'https://forgeflow.in',
            'registered_address' => 'Plot No. C-14, Phase II, Chakan MIDC Industrial Area',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'postal_code' => '410501',
            'country' => 'India',
            'currency' => 'INR',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        // 3. Demo Users for all 8 roles
        $password = Hash::make('Password123!');
        $demoUsers = [
            [
                'name' => 'Vikramaditya Singhania',
                'email' => 'superadmin@forgeflow.in',
                'role' => 'Super Admin',
                'code' => 'EMP-001',
                'dept' => 'Executive',
            ],
            [
                'name' => 'Arunachalam Murugan',
                'email' => 'admin@forgeflow.in',
                'role' => 'Company Admin',
                'code' => 'EMP-002',
                'dept' => 'Plant Operations',
            ],
            [
                'name' => 'Devendra Deshmukh',
                'email' => 'production@forgeflow.in',
                'role' => 'Production Manager',
                'code' => 'EMP-003',
                'dept' => 'Manufacturing',
            ],
            [
                'name' => 'Kavita Iyer',
                'email' => 'inventory@forgeflow.in',
                'role' => 'Inventory Manager',
                'code' => 'EMP-004',
                'dept' => 'Materials & Stores',
            ],
            [
                'name' => 'Sanjay Bhattacharya',
                'email' => 'purchase@forgeflow.in',
                'role' => 'Purchase Manager',
                'code' => 'EMP-005',
                'dept' => 'Procurement',
            ],
            [
                'name' => 'Rameshwar Yadav',
                'email' => 'warehouse@forgeflow.in',
                'role' => 'Warehouse Staff',
                'code' => 'EMP-006',
                'dept' => 'Logistics & Warehousing',
            ],
            [
                'name' => 'Pooja Agarwal',
                'email' => 'finance@forgeflow.in',
                'role' => 'Finance Manager',
                'code' => 'EMP-007',
                'dept' => 'Accounts & Finance',
            ],
            [
                'name' => 'Suresh Shinde',
                'email' => 'employee@forgeflow.in',
                'role' => 'Employee',
                'code' => 'EMP-008',
                'dept' => 'Shop Floor Engineering',
            ],
        ];

        $usersByRole = [];
        foreach ($demoUsers as $ud) {
            $user = User::create([
                'company_id' => $company->id,
                'name' => $ud['name'],
                'email' => $ud['email'],
                'password' => $password,
                'employee_code' => $ud['code'],
                'phone' => '+91-98' . rand(10000000, 99999999),
                'department' => $ud['dept'],
                'status' => 'active',
            ]);
            $user->assignRole($ud['role']);
            $usersByRole[$ud['role']] = $user;
        }

        // 4. Units of Measurement
        $units = [
            ['name' => 'Metric Ton', 'symbol' => 'MT', 'type' => 'weight'],
            ['name' => 'Kilogram', 'symbol' => 'KG', 'type' => 'weight'],
            ['name' => 'Metre', 'symbol' => 'M', 'type' => 'length'],
            ['name' => 'Litre', 'symbol' => 'L', 'type' => 'volume'],
            ['name' => 'Piece', 'symbol' => 'PCS', 'type' => 'count'],
            ['name' => 'Set', 'symbol' => 'SET', 'type' => 'count'],
            ['name' => 'Box', 'symbol' => 'BOX', 'type' => 'count'],
        ];

        $createdUnits = [];
        foreach ($units as $u) {
            $createdUnits[$u['symbol']] = Unit::create(array_merge($u, ['company_id' => $company->id]));
        }

        // 5. Product Categories
        $categories = [
            ['code' => 'CAT-RM-STL', 'name' => 'Raw Materials - Forging Steels & Billets'],
            ['code' => 'CAT-RM-ALU', 'name' => 'Raw Materials - Aluminum & Alloys'],
            ['code' => 'CAT-CON', 'name' => 'Industrial Consumables & Fluids'],
            ['code' => 'CAT-WIP', 'name' => 'Intermediate Forged Blanks & Machined Parts'],
            ['code' => 'CAT-FG', 'name' => 'Finished Heavy Equipment & Systems'],
        ];

        $createdCats = [];
        foreach ($categories as $c) {
            $createdCats[$c['code']] = ProductCategory::create(array_merge($c, [
                'company_id' => $company->id,
                'description' => $c['name'],
            ]));
        }

        // 6. Warehouses and Locations
        $wh1 = Warehouse::create([
            'company_id' => $company->id,
            'code' => 'WH-PUN-RM',
            'name' => 'Bhosari Raw Materials Yard',
            'type' => 'raw_material',
            'address' => 'Plot 45, MIDC Bhosari',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'postal_code' => '411026',
            'manager_id' => $usersByRole['Inventory Manager']->id,
        ]);

        $locRm1 = WarehouseLocation::create([
            'warehouse_id' => $wh1->id,
            'code' => 'LOC-RM-BAY-01',
            'name' => 'Heavy Steel Billet Yard Bay 1',
            'zone' => 'North Yard',
            'aisle' => 'A1',
            'location_type' => 'storage',
            'max_capacity_kg' => 150000,
        ]);

        $locRm2 = WarehouseLocation::create([
            'warehouse_id' => $wh1->id,
            'code' => 'LOC-RM-RACK-02',
            'name' => 'Alloy Round Bar Shelving R-02',
            'zone' => 'Indoor Covered Shed',
            'aisle' => 'B2',
            'location_type' => 'storage',
            'max_capacity_kg' => 50000,
        ]);

        $wh2 = Warehouse::create([
            'company_id' => $company->id,
            'code' => 'WH-PUN-WIP',
            'name' => 'Chakan Production Staging & Component Depot',
            'type' => 'staging',
            'address' => 'Plant II, Gate 2, Chakan Industrial Estate',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'postal_code' => '410501',
            'manager_id' => $usersByRole['Production Manager']->id,
        ]);

        $locWip1 = WarehouseLocation::create([
            'warehouse_id' => $wh2->id,
            'code' => 'LOC-WIP-STG-01',
            'name' => 'Pre-Machining Staging Buffer 1',
            'zone' => 'Bay 4',
            'aisle' => 'S1',
            'location_type' => 'staging',
            'max_capacity_kg' => 40000,
        ]);

        $wh3 = Warehouse::create([
            'company_id' => $company->id,
            'code' => 'WH-PUN-FG',
            'name' => 'Talegaon Finished Machinery Logistics Center',
            'type' => 'finished_goods',
            'address' => 'Talegaon Industrial Park, Near NH 48',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'postal_code' => '410507',
            'manager_id' => $usersByRole['Warehouse Staff']->id,
        ]);

        $locFg1 = WarehouseLocation::create([
            'warehouse_id' => $wh3->id,
            'code' => 'LOC-FG-BAY-A',
            'name' => 'High-Capacity Crane Bay A',
            'zone' => 'Dispatch Zone',
            'aisle' => 'D1',
            'location_type' => 'dispatch',
            'max_capacity_kg' => 200000,
        ]);

        // 7. Products Master
        $productsData = [
            [
                'sku' => 'RM-EN24-RND',
                'name' => 'EN24 Alloy Steel Round Bar 80mm',
                'type' => 'raw_material',
                'category_code' => 'CAT-RM-STL',
                'unit_symbol' => 'KG',
                'hsn_code' => '72283029',
                'standard_cost' => 185.00,
                'min_stock_level' => 5000.00,
                'reorder_level' => 8000.00,
                'lead_time_days' => 10,
            ],
            [
                'sku' => 'RM-MS-BLT',
                'name' => 'Mild Steel Continuous Cast Billet 150x150mm',
                'type' => 'raw_material',
                'category_code' => 'CAT-RM-STL',
                'unit_symbol' => 'MT',
                'hsn_code' => '72071920',
                'standard_cost' => 58000.00,
                'min_stock_level' => 20.00,
                'reorder_level' => 35.00,
                'lead_time_days' => 14,
            ],
            [
                'sku' => 'RM-AL-6061',
                'name' => 'Aluminum 6061-T6 Extrusion Ingot',
                'type' => 'raw_material',
                'category_code' => 'CAT-RM-ALU',
                'unit_symbol' => 'KG',
                'hsn_code' => '76011010',
                'standard_cost' => 320.00,
                'min_stock_level' => 2000.00,
                'reorder_level' => 3500.00,
                'lead_time_days' => 7,
            ],
            [
                'sku' => 'CON-HYD-OIL',
                'name' => 'Industrial Hydraulic Fluid ISO VG 46',
                'type' => 'consumable',
                'category_code' => 'CAT-CON',
                'unit_symbol' => 'L',
                'hsn_code' => '27101980',
                'standard_cost' => 240.00,
                'min_stock_level' => 500.00,
                'reorder_level' => 800.00,
                'lead_time_days' => 5,
            ],
            [
                'sku' => 'WIP-PIN-SFT',
                'name' => 'CNC Machined Transmission Pinion Shaft',
                'type' => 'work_in_progress',
                'category_code' => 'CAT-WIP',
                'unit_symbol' => 'PCS',
                'hsn_code' => '84831099',
                'standard_cost' => 9850.00,
                'min_stock_level' => 15.00,
                'reorder_level' => 25.00,
                'lead_time_days' => 4,
            ],
            [
                'sku' => 'WIP-FLG-BLK',
                'name' => 'Hot Forged Flange Blank 300# DN100',
                'type' => 'work_in_progress',
                'category_code' => 'CAT-WIP',
                'unit_symbol' => 'PCS',
                'hsn_code' => '73079990',
                'standard_cost' => 4250.00,
                'min_stock_level' => 30.00,
                'reorder_level' => 50.00,
                'lead_time_days' => 3,
            ],
            [
                'sku' => 'FG-IND-GBX500',
                'name' => 'Heavy Duty Helical Industrial Gearbox 500 kW',
                'type' => 'finished_good',
                'category_code' => 'CAT-FG',
                'unit_symbol' => 'PCS',
                'hsn_code' => '84834000',
                'standard_cost' => 148500.00,
                'selling_price' => 225000.00,
                'min_stock_level' => 4.00,
                'reorder_level' => 8.00,
                'lead_time_days' => 18,
            ],
            [
                'sku' => 'FG-HYD-CYL',
                'name' => 'High-Pressure Heavy Hydraulic Cylinder 250 Bar',
                'type' => 'finished_good',
                'category_code' => 'CAT-FG',
                'unit_symbol' => 'PCS',
                'hsn_code' => '84122100',
                'standard_cost' => 52000.00,
                'selling_price' => 78500.00,
                'min_stock_level' => 6.00,
                'reorder_level' => 12.00,
                'lead_time_days' => 12,
            ],
        ];

        $prods = [];
        foreach ($productsData as $pd) {
            $cat = $createdCats[$pd['category_code']];
            $u = $createdUnits[$pd['unit_symbol']];
            $prods[$pd['sku']] = Product::create([
                'company_id' => $company->id,
                'category_id' => $cat->id,
                'unit_id' => $u->id,
                'sku' => $pd['sku'],
                'name' => $pd['name'],
                'type' => $pd['type'],
                'hsn_code' => $pd['hsn_code'],
                'standard_cost' => $pd['standard_cost'],
                'selling_price' => $pd['selling_price'] ?? ($pd['standard_cost'] * 1.35),
                'min_stock_level' => $pd['min_stock_level'],
                'reorder_level' => $pd['reorder_level'],
                'lead_time_days' => $pd['lead_time_days'],
                'is_batch_tracked' => true,
                'is_active' => true,
            ]);
        }

        // 8. Multi-Level Bills of Materials (BOM)
        // Sub-Assembly BOM for Pinion Shaft
        $bomSub = BillOfMaterial::create([
            'company_id' => $company->id,
            'product_id' => $prods['WIP-PIN-SFT']->id,
            'code' => 'BOM-SUB-PIN-01',
            'name' => 'Pinion Shaft Machining Sub-Assembly',
            'version' => '1.0',
            'batch_size' => 1.0,
            'output_quantity' => 1.0,
            'is_active' => true,
            'is_default' => true,
        ]);

        BomItem::create([
            'bill_of_materials_id' => $bomSub->id,
            'component_product_id' => $prods['RM-EN24-RND']->id,
            'unit_id' => $createdUnits['KG']->id,
            'quantity' => 45.0, // 45 kg raw alloy per shaft
            'scrap_percentage' => 3.5,
            'sequence' => 1,
            'is_sub_assembly' => false,
        ]);

        // Main Finished Good BOM for Industrial Gearbox 500 kW
        $bomMain = BillOfMaterial::create([
            'company_id' => $company->id,
            'product_id' => $prods['FG-IND-GBX500']->id,
            'code' => 'BOM-GBX-500KW',
            'name' => '500 kW Helical Gearbox Assembly BOM',
            'version' => '2.1',
            'batch_size' => 1.0,
            'output_quantity' => 1.0,
            'is_active' => true,
            'is_default' => true,
        ]);

        BomItem::create([
            'bill_of_materials_id' => $bomMain->id,
            'component_product_id' => $prods['WIP-PIN-SFT']->id,
            'unit_id' => $createdUnits['PCS']->id,
            'quantity' => 2.0,
            'scrap_percentage' => 0.0,
            'sequence' => 1,
            'is_sub_assembly' => true,
            'sub_bom_id' => $bomSub->id,
        ]);

        BomItem::create([
            'bill_of_materials_id' => $bomMain->id,
            'component_product_id' => $prods['WIP-FLG-BLK']->id,
            'unit_id' => $createdUnits['PCS']->id,
            'quantity' => 4.0,
            'scrap_percentage' => 1.0,
            'sequence' => 2,
            'is_sub_assembly' => false,
        ]);

        BomItem::create([
            'bill_of_materials_id' => $bomMain->id,
            'component_product_id' => $prods['CON-HYD-OIL']->id,
            'unit_id' => $createdUnits['L']->id,
            'quantity' => 40.0,
            'scrap_percentage' => 2.0,
            'sequence' => 3,
            'is_sub_assembly' => false,
        ]);

        // 9. Machines
        $machines = [
            [
                'code' => 'MCH-CNC-01',
                'name' => 'Mazak Integrex e-670H 4-Axis CNC Lathe',
                'type' => 'CNC Horizontal Lathe',
                'hourly_rate' => 1400.00,
                'capacity_per_hour' => 1.5,
                'location' => 'Machining Bay 2',
                'status' => 'operational',
            ],
            [
                'code' => 'MCH-PRS-2000',
                'name' => 'Schuler 2000-Ton Closed Die Forging Press',
                'type' => 'Hydraulic Forging Press',
                'hourly_rate' => 3800.00,
                'capacity_per_hour' => 4.0,
                'location' => 'Hot Forging Shop A',
                'status' => 'operational',
            ],
            [
                'code' => 'MCH-FRN-03',
                'name' => 'Surface Combustion 1100°C Heat Treatment Bogie Furnace',
                'type' => 'Heat Treatment Furnace',
                'hourly_rate' => 1950.00,
                'capacity_per_hour' => 10.0,
                'location' => 'Metallurgical Bay 1',
                'status' => 'operational',
            ],
            [
                'code' => 'MCH-WLD-ROB',
                'name' => 'ABB IRB 2600 Robotic MIG Welding System',
                'type' => 'Robotic Welder',
                'hourly_rate' => 900.00,
                'capacity_per_hour' => 3.0,
                'location' => 'Fabrication Bay',
                'status' => 'operational',
            ],
        ];

        foreach ($machines as $m) {
            Machine::create(array_merge($m, ['company_id' => $company->id]));
        }

        // 10. Employees
        $employees = [
            [
                'employee_code' => 'EMP-IND-01',
                'first_name' => 'Rajesh',
                'last_name' => 'Sharma',
                'designation' => 'Senior CNC Machining Technician',
                'department' => 'production',
                'shift' => 'morning',
                'hourly_rate' => 350.00,
            ],
            [
                'employee_code' => 'EMP-IND-02',
                'first_name' => 'Sunita',
                'last_name' => 'Rao',
                'designation' => 'Lead Quality Control Metrologist',
                'department' => 'quality_assurance',
                'shift' => 'general',
                'hourly_rate' => 420.00,
            ],
            [
                'employee_code' => 'EMP-IND-03',
                'first_name' => 'Vikram',
                'last_name' => 'Singh',
                'designation' => 'Master Hydraulic Press Craftsman',
                'department' => 'production',
                'shift' => 'morning',
                'hourly_rate' => 380.00,
            ],
        ];

        foreach ($employees as $e) {
            Employee::create(array_merge($e, ['company_id' => $company->id]));
        }

        // 11. Suppliers
        $suppliers = [
            [
                'code' => 'SUP-TATA-STL',
                'name' => 'Tata Steel Long Products Limited',
                'gstin' => '27AAACT2727Q1ZT',
                'pan' => 'AAACT2727Q',
                'email' => 'sales.west@tatasteellp.com',
                'phone' => '+91-20-2567-8900',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'payment_terms_days' => 45,
                'credit_limit' => 25000000.00,
                'rating' => 4.95,
            ],
            [
                'code' => 'SUP-HINDALCO',
                'name' => 'Hindalco Industries Limited',
                'gstin' => '27AAACH0933K1ZO',
                'pan' => 'AAACH0933K',
                'email' => 'alloys.procure@adityabirla.com',
                'phone' => '+91-22-6662-6666',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'payment_terms_days' => 30,
                'credit_limit' => 15000000.00,
                'rating' => 4.80,
            ],
            [
                'code' => 'SUP-SERVO-OIL',
                'name' => 'Indian Oil Servo Lubricants Industrial Depot',
                'gstin' => '27AACCI5523A1ZV',
                'pan' => 'AACCI5523A',
                'email' => 'servo.pune@indianoil.in',
                'phone' => '+91-20-2445-1200',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'payment_terms_days' => 30,
                'credit_limit' => 5000000.00,
                'rating' => 4.70,
            ],
        ];

        $createdSuppliers = [];
        foreach ($suppliers as $s) {
            $createdSuppliers[$s['code']] = Supplier::create(array_merge($s, [
                'company_id' => $company->id,
                'country' => 'India',
                'status' => 'active',
            ]));
        }

        // 12. Initial Stock Balances & Immutable Stock Movement Records
        $initialStocks = [
            [
                'product' => $prods['RM-EN24-RND'],
                'warehouse' => $wh1,
                'location' => $locRm2,
                'qty' => 12500.00,
                'cost' => 185.00,
                'batch' => 'LOT-EN24-202610-01',
            ],
            [
                'product' => $prods['RM-MS-BLT'],
                'warehouse' => $wh1,
                'location' => $locRm1,
                'qty' => 45.00,
                'cost' => 58000.00,
                'batch' => 'LOT-MS-202610-A',
            ],
            [
                'product' => $prods['RM-AL-6061'],
                'warehouse' => $wh1,
                'location' => $locRm2,
                'qty' => 4800.00,
                'cost' => 320.00,
                'batch' => 'LOT-AL-202610-03',
            ],
            [
                'product' => $prods['CON-HYD-OIL'],
                'warehouse' => $wh1,
                'location' => $locRm2,
                'qty' => 1400.00,
                'cost' => 240.00,
                'batch' => 'LOT-OIL-202610-99',
            ],
            [
                'product' => $prods['WIP-PIN-SFT'],
                'warehouse' => $wh2,
                'location' => $locWip1,
                'qty' => 28.00,
                'cost' => 9850.00,
                'batch' => 'BAT-PIN-202610-14',
            ],
            [
                'product' => $prods['WIP-FLG-BLK'],
                'warehouse' => $wh2,
                'location' => $locWip1,
                'qty' => 64.00,
                'cost' => 4250.00,
                'batch' => 'BAT-FLG-202610-08',
            ],
            [
                'product' => $prods['FG-IND-GBX500'],
                'warehouse' => $wh3,
                'location' => $locFg1,
                'qty' => 6.00,
                'cost' => 148500.00,
                'batch' => 'BAT-GBX-202610-F01',
            ],
            [
                'product' => $prods['FG-HYD-CYL'],
                'warehouse' => $wh3,
                'location' => $locFg1,
                'qty' => 14.00,
                'cost' => 52000.00,
                'batch' => 'BAT-CYL-202610-C05',
            ],
        ];

        foreach ($initialStocks as $idx => $st) {
            $p = $st['product'];
            $wh = $st['warehouse'];
            $loc = $st['location'];

            InventoryBalance::create([
                'company_id' => $company->id,
                'warehouse_id' => $wh->id,
                'warehouse_location_id' => $loc->id,
                'product_id' => $p->id,
                'batch_number' => $st['batch'],
                'quantity_on_hand' => $st['qty'],
                'quantity_reserved' => 0,
                'quantity_available' => $st['qty'],
                'average_cost_unit' => $st['cost'],
            ]);

            StockMovement::create([
                'company_id' => $company->id,
                'movement_number' => 'MOV-OPN-' . str_pad((string) ($idx + 1), 4, '0', STR_PAD_LEFT),
                'movement_type' => 'opening_stock',
                'product_id' => $p->id,
                'dest_warehouse_id' => $wh->id,
                'dest_location_id' => $loc->id,
                'quantity' => $st['qty'],
                'unit_id' => $p->unit_id,
                'unit_cost' => $st['cost'],
                'total_cost' => $st['qty'] * $st['cost'],
                'batch_number' => $st['batch'],
                'reason' => 'Opening inventory balance setup',
                'created_by_user_id' => $usersByRole['Inventory Manager']->id,
                'created_at' => now()->subDays(30),
            ]);
        }

        // 13. Realistic Historical Purchase Order & GRN
        $po = PurchaseOrder::create([
            'company_id' => $company->id,
            'po_number' => 'PO-202611-0082',
            'supplier_id' => $createdSuppliers['SUP-TATA-STL']->id,
            'warehouse_id' => $wh1->id,
            'order_date' => now()->subDays(12)->toDateString(),
            'expected_delivery_date' => now()->subDays(2)->toDateString(),
            'subtotal' => 925000.00,
            'tax_amount' => 166500.00,
            'shipping_amount' => 12000.00,
            'total_amount' => 1103500.00,
            'currency' => 'INR',
            'status' => 'received',
            'approval_status' => 'approved',
            'approved_by_user_id' => $usersByRole['Finance Manager']->id,
            'approved_at' => now()->subDays(11),
            'approval_notes' => 'Approved under Q3 Raw Material Procurement Budget.',
            'created_by_user_id' => $usersByRole['Purchase Manager']->id,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $prods['RM-EN24-RND']->id,
            'quantity_ordered' => 5000.00,
            'quantity_received' => 5000.00,
            'unit_id' => $createdUnits['KG']->id,
            'unit_price' => 185.00,
            'tax_percentage' => 18.00,
            'tax_amount' => 166500.00,
            'line_total' => 1091500.00,
            'hsn_code' => '72283029',
        ]);

        $grn = GoodsReceipt::create([
            'company_id' => $company->id,
            'grn_number' => 'GRN-202611-0041',
            'purchase_order_id' => $po->id,
            'supplier_id' => $po->supplier_id,
            'warehouse_id' => $wh1->id,
            'received_date' => now()->subDays(2)->toDateString(),
            'delivery_challan_number' => 'DC/TATA/99812',
            'transporter_name' => 'VRL Logistics Heavy Haulage',
            'vehicle_number' => 'MH-12-RN-8812',
            'status' => 'received',
            'received_by_user_id' => $usersByRole['Warehouse Staff']->id,
            'verified_by_user_id' => $usersByRole['Inventory Manager']->id,
        ]);

        GoodsReceiptItem::create([
            'goods_receipt_id' => $grn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $poItem->product_id,
            'warehouse_location_id' => $locRm2->id,
            'quantity_received' => 5000.00,
            'quantity_accepted' => 5000.00,
            'quantity_rejected' => 0.00,
            'batch_number' => 'LOT-EN24-202611-TATA',
            'unit_cost' => 185.00,
        ]);

        SupplierInvoice::create([
            'company_id' => $company->id,
            'supplier_id' => $po->supplier_id,
            'purchase_order_id' => $po->id,
            'invoice_number' => 'INV-TATA-2026-9041',
            'invoice_date' => now()->subDays(2)->toDateString(),
            'due_date' => now()->addDays(43)->toDateString(),
            'subtotal' => 925000.00,
            'tax_amount' => 166500.00,
            'total_amount' => 1091500.00,
            'paid_amount' => 0.00,
            'payment_status' => 'unpaid',
            'status' => 'verified',
            'notes' => 'Payment due in 45 days per standard corporate contract.',
        ]);

        // 14. Completed Production Order & Output
        $prodOrder = ProductionOrder::create([
            'company_id' => $company->id,
            'order_number' => 'WO-202611-0012',
            'product_id' => $prods['FG-IND-GBX500']->id,
            'bill_of_materials_id' => $bomMain->id,
            'target_warehouse_id' => $wh3->id,
            'planned_quantity' => 2.00,
            'produced_quantity' => 2.00,
            'rejected_quantity' => 0.00,
            'scrapped_quantity' => 0.00,
            'start_date' => now()->subDays(6)->toDateString(),
            'due_date' => now()->addDays(1)->toDateString(),
            'actual_start_at' => now()->subDays(6),
            'actual_completed_at' => now()->subDays(1),
            'status' => 'completed',
            'priority' => 'high',
            'estimated_cost' => 297000.00,
            'actual_material_cost' => 220000.00,
            'actual_labour_cost' => 38000.00,
            'actual_machine_cost' => 39000.00,
            'actual_cost' => 297000.00,
            'assigned_to_user_id' => $usersByRole['Production Manager']->id,
            'created_by_user_id' => $usersByRole['Production Manager']->id,
            'notes' => 'High priority order for Larsen & Toubro Heavy Engineering.',
        ]);

        ProductionOutput::create([
            'production_order_id' => $prodOrder->id,
            'output_number' => 'OUT-202611-0004',
            'product_id' => $prods['FG-IND-GBX500']->id,
            'warehouse_id' => $wh3->id,
            'warehouse_location_id' => $locFg1->id,
            'batch_number' => 'BAT-GBX-202611-LT',
            'quantity_produced' => 2.00,
            'quantity_rejected' => 0.00,
            'quantity_scrapped' => 0.00,
            'unit_production_cost' => 148500.00,
            'total_production_cost' => 297000.00,
            'received_by_user_id' => $usersByRole['Warehouse Staff']->id,
            'recorded_at' => now()->subDays(1),
        ]);
    }
}

