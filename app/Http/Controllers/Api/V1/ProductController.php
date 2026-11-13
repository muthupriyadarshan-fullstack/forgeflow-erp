<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Unit;
use App\Services\Audit\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id ?? 1;
        $query = Product::where('company_id', $companyId)
            ->with(['category', 'unit', 'variants', 'inventoryBalances.warehouse']);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('sku', 'like', "%{$s}%")
                    ->orWhere('name', 'like', "%{$s}%")
                    ->orWhere('hsn_code', 'like', "%{$s}%");
            });
        }

        $products = $query->orderBy('name')->paginate($request->input('per_page', 25));

        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id ?? 1;

        $validated = $request->validate([
            'category_id' => ['nullable', 'exists:product_categories,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'sku' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:raw_material,work_in_progress,finished_good,consumable'],
            'description' => ['nullable', 'string'],
            'specifications' => ['nullable', 'array'],
            'hsn_code' => ['nullable', 'string', 'max:20'],
            'min_stock_level' => ['numeric', 'min:0'],
            'reorder_level' => ['numeric', 'min:0'],
            'standard_cost' => ['numeric', 'min:0'],
            'selling_price' => ['numeric', 'min:0'],
            'lead_time_days' => ['integer', 'min:0'],
            'is_batch_tracked' => ['boolean'],
        ]);

        $validated['company_id'] = $companyId;

        $product = Product::create($validated);
        AuditService::log('product_created', $product, null, $product->toArray());

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product->load(['category', 'unit']),
        ], 201);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load([
            'category',
            'unit',
            'variants',
            'billsOfMaterials.items.componentProduct',
            'inventoryBalances.warehouse',
            'inventoryBalances.location',
        ]);

        return response()->json($product);
    }

    public function categories(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id ?? 1;
        $categories = ProductCategory::where('company_id', $companyId)
            ->withCount('products')
            ->orderBy('name')
            ->get();

        return response()->json($categories);
    }

    public function units(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id ?? 1;
        $units = Unit::where('company_id', $companyId)->orderBy('name')->get();

        return response()->json($units);
    }
}

