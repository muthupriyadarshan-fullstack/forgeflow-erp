<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillOfMaterial extends Model
{
    use HasFactory;

    protected $table = 'bills_of_materials';

    protected $fillable = [
        'company_id',
        'product_id',
        'variant_id',
        'code',
        'name',
        'version',
        'batch_size',
        'output_quantity',
        'is_active',
        'is_default',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'batch_size' => 'decimal:4',
            'output_quantity' => 'decimal:4',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BomItem::class, 'bill_of_materials_id')->orderBy('sequence');
    }

    public function calculateTotalMaterialCost(): float
    {
        $cost = 0;
        /** @var BomItem $item */
        foreach ($this->items as $item) {
            $compCost = (float) ($item->componentProduct->standard_cost ?? 0);
            $grossQty = (float) $item->quantity * (1 + ((float) $item->scrap_percentage / 100));
            $cost += ($grossQty * $compCost);
        }
        return $cost;
    }
}

