<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BillOfMaterial;
use App\Models\BomItem;
use App\Models\Product;
use App\Services\Audit\AuditService;
use App\Services\Production\BOMService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BOMController extends Controller
{
    public function __construct(protected BOMService $bomService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id ?? 1;

        $boms = BillOfMaterial::where('company_id', $companyId)
            ->with(['product.unit', 'items.componentProduct.unit'])
            ->orderBy('name')
            ->paginate($request->input('per_page', 20));

        return response()->json($boms);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id ?? 1;

        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'variant_id' => ['nullable', 'exists:product_variants,id'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:10'],
            'batch_size' => ['required', 'numeric', 'min:0.0001'],
            'output_quantity' => ['required', 'numeric', 'min:0.0001'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.component_product_id' => ['required', 'exists:products,id'],
            'items.*.unit_id' => ['required', 'exists:units,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.scrap_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.is_sub_assembly' => ['nullable', 'boolean'],
            'items.*.sub_bom_id' => ['nullable', 'exists:bills_of_materials,id'],
        ]);

        $bom = DB::transaction(function () use ($validated, $companyId) {
            $bom = BillOfMaterial::create([
                'company_id' => $companyId,
                'product_id' => $validated['product_id'],
                'variant_id' => $validated['variant_id'] ?? null,
                'code' => $validated['code'],
                'name' => $validated['name'],
                'version' => $validated['version'] ?? '1.0',
                'batch_size' => $validated['batch_size'],
                'output_quantity' => $validated['output_quantity'],
                'notes' => $validated['notes'] ?? null,
                'is_active' => true,
                'is_default' => true,
            ]);

            foreach ($validated['items'] as $index => $item) {
                BomItem::create([
                    'bill_of_materials_id' => $bom->id,
                    'component_product_id' => $item['component_product_id'],
                    'unit_id' => $item['unit_id'],
                    'quantity' => $item['quantity'],
                    'scrap_percentage' => $item['scrap_percentage'] ?? 0,
                    'sequence' => $index + 1,
                    'is_sub_assembly' => $item['is_sub_assembly'] ?? false,
                    'sub_bom_id' => $item['sub_bom_id'] ?? null,
                ]);
            }

            AuditService::log('bom_created', $bom, null, $bom->toArray());

            return $bom->load(['product.unit', 'items.componentProduct.unit']);
        });

        return response()->json([
            'message' => 'Bill of Materials created successfully',
            'bom' => $bom,
        ], 201);
    }

    public function show(BillOfMaterial $bom): JsonResponse
    {
        $bom->load(['product.unit', 'items.componentProduct.unit', 'items.subBom.items.componentProduct']);

        return response()->json([
            'bom' => $bom,
            'calculated_material_cost' => $bom->calculateTotalMaterialCost(),
        ]);
    }

    public function explode(BillOfMaterial $bom, Request $request): JsonResponse
    {
        $quantity = (float) $request->input('quantity', 1.0);
        $explosion = $this->bomService->explodeBOM($bom, $quantity);

        return response()->json([
            'bom_code' => $bom->code,
            'product_name' => $bom->product ? $bom->product->name : '',
            'target_quantity' => $quantity,
            'components' => $explosion,
            'total_material_cost' => round(array_sum(array_column($explosion, 'total_cost')), 2),
        ]);
    }

    public function checkAvailability(BillOfMaterial $bom, Request $request): JsonResponse
    {
        $quantity = (float) $request->input('quantity', 1.0);
        $warehouseId = $request->filled('warehouse_id') ? (int) $request->warehouse_id : null;

        $result = $this->bomService->checkMaterialAvailability($bom, $quantity, $warehouseId);

        return response()->json($result);
    }
}

