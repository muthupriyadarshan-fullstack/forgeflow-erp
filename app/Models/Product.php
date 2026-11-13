<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'category_id',
        'unit_id',
        'sku',
        'name',
        'type',
        'description',
        'specifications',
        'hsn_code',
        'min_stock_level',
        'max_stock_level',
        'reorder_level',
        'standard_cost',
        'selling_price',
        'lead_time_days',
        'is_batch_tracked',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'specifications' => 'array',
            'min_stock_level' => 'decimal:4',
            'max_stock_level' => 'decimal:4',
            'reorder_level' => 'decimal:4',
            'standard_cost' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'is_batch_tracked' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function billsOfMaterials(): HasMany
    {
        return $this->hasMany(BillOfMaterial::class);
    }

    public function inventoryBalances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function getTotalQuantityOnHandAttribute(): float
    {
        return (float) $this->inventoryBalances()->sum('quantity_on_hand');
    }

    public function getTotalQuantityAvailableAttribute(): float
    {
        return (float) $this->inventoryBalances()->sum('quantity_available');
    }
}

