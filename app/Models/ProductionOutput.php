<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOutput extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_order_id',
        'output_number',
        'product_id',
        'warehouse_id',
        'warehouse_location_id',
        'batch_number',
        'quantity_produced',
        'quantity_rejected',
        'quantity_scrapped',
        'unit_production_cost',
        'total_production_cost',
        'received_by_user_id',
        'recorded_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_produced' => 'decimal:4',
            'quantity_rejected' => 'decimal:4',
            'quantity_scrapped' => 'decimal:4',
            'unit_production_cost' => 'decimal:2',
            'total_production_cost' => 'decimal:2',
            'recorded_at' => 'datetime',
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

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }
}

