<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'bill_of_materials_id',
        'component_product_id',
        'component_variant_id',
        'unit_id',
        'quantity',
        'scrap_percentage',
        'sequence',
        'is_sub_assembly',
        'sub_bom_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'scrap_percentage' => 'decimal:2',
            'is_sub_assembly' => 'boolean',
        ];
    }

    public function billOfMaterial(): BelongsTo
    {
        return $this->belongsTo(BillOfMaterial::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function componentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function componentVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'component_variant_id');
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<BillOfMaterial, $this>
     */
    public function subBom(): BelongsTo
    {
        return $this->belongsTo(BillOfMaterial::class, 'sub_bom_id');
    }
}

