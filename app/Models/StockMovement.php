<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    public $timestamps = false; // We use created_at only (immutable audit ledger)

    protected $fillable = [
        'company_id',
        'movement_number',
        'movement_type',
        'product_id',
        'product_variant_id',
        'source_warehouse_id',
        'source_location_id',
        'dest_warehouse_id',
        'dest_location_id',
        'quantity',
        'unit_id',
        'unit_cost',
        'total_cost',
        'batch_number',
        'lot_number',
        'expiry_date',
        'reference_type',
        'reference_id',
        'reason',
        'notes',
        'created_by_user_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'expiry_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Enforce ledger immutability: Never allow updates or deletes on historical stock ledger
        static::updating(function () {
            throw new \RuntimeException('Stock movements are immutable audit records and cannot be updated.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Stock movements are immutable audit records and cannot be deleted.');
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'source_location_id');
    }

    public function destWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'dest_warehouse_id');
    }

    public function destLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'dest_location_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}

