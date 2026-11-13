<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_order_id',
        'product_id',
        'warehouse_id',
        'warehouse_location_id',
        'quantity_reserved',
        'quantity_issued',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity_reserved' => 'decimal:4',
            'quantity_issued' => 'decimal:4',
        ];
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'warehouse_location_id');
    }
}

