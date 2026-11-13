<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_order_id',
        'issue_number',
        'product_id',
        'warehouse_id',
        'warehouse_location_id',
        'quantity_issued',
        'unit_id',
        'unit_cost',
        'batch_number',
        'issued_by_user_id',
        'issued_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_issued' => 'decimal:4',
            'unit_cost' => 'decimal:2',
            'issued_at' => 'datetime',
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

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }
}

