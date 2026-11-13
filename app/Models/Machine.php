<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Machine extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'type',
        'model_number',
        'serial_number',
        'capacity_per_hour',
        'location',
        'status',
        'hourly_rate',
        'last_maintenance_date',
        'next_maintenance_due',
    ];

    protected function casts(): array
    {
        return [
            'capacity_per_hour' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'last_maintenance_date' => 'date',
            'next_maintenance_due' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(ProductionStage::class);
    }
}

