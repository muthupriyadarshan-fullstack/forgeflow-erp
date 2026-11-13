<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'order_number',
        'product_id',
        'bill_of_materials_id',
        'target_warehouse_id',
        'planned_quantity',
        'produced_quantity',
        'rejected_quantity',
        'scrapped_quantity',
        'start_date',
        'due_date',
        'actual_start_at',
        'actual_completed_at',
        'status',
        'priority',
        'estimated_cost',
        'actual_material_cost',
        'actual_labour_cost',
        'actual_machine_cost',
        'actual_cost',
        'assigned_to_user_id',
        'created_by_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'planned_quantity' => 'decimal:4',
            'produced_quantity' => 'decimal:4',
            'rejected_quantity' => 'decimal:4',
            'scrapped_quantity' => 'decimal:4',
            'estimated_cost' => 'decimal:2',
            'actual_material_cost' => 'decimal:2',
            'actual_labour_cost' => 'decimal:2',
            'actual_machine_cost' => 'decimal:2',
            'actual_cost' => 'decimal:2',
            'start_date' => 'date',
            'due_date' => 'date',
            'actual_start_at' => 'datetime',
            'actual_completed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function billOfMaterial(): BelongsTo
    {
        return $this->belongsTo(BillOfMaterial::class, 'bill_of_materials_id');
    }

    public function targetWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'target_warehouse_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(ProductionStage::class)->orderBy('sequence');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(MaterialReservation::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(MaterialIssue::class);
    }

    public function outputs(): HasMany
    {
        return $this->hasMany(ProductionOutput::class);
    }
}

