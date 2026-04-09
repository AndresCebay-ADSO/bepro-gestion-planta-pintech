<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class RawMaterial extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'unit_of_measure_id', 'current_price', 'minimum_stock', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $table = 'raw_materials';

    protected $fillable = [
        'code',
        'unit_of_measure_id',
        'current_price',
        'previous_price',
        'minimum_stock',
        'alert_days_before_expiry',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'current_price' => 'decimal:4',
            'previous_price' => 'decimal:4',
            'minimum_stock' => 'decimal:4',
            'alert_days_before_expiry' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id');
    }

    public function inventoryBatches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class, 'raw_material_id');
    }

    public function formulaDetails(): HasMany
    {
        return $this->hasMany(FormulaDetail::class, 'raw_material_id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'raw_material_id');
    }

    public function productionOrderDetails(): HasMany
    {
        return $this->hasMany(ProductionOrderDetail::class, 'raw_material_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'raw_material_id');
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
