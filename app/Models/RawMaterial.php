<?php

namespace App\Models;

use Database\Factories\RawMaterialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $code
 * @property int|null $category_id
 * @property int $unit_of_measure_id
 * @property float|null $current_price
 * @property float $previous_price
 * @property float $minimum_stock
 * @property int $alert_days_before_expiry
 * @property bool $tracks_inventory
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read RawMaterialCategory|null $category
 * @property-read UnitOfMeasure $unitOfMeasure
 * @property-read Collection|InventoryBatch[] $inventoryBatches
 * @property-read Collection|FormulaDetail[] $formulaDetails
 * @property-read Collection|InventoryMovement[] $inventoryMovements
 * @property-read Collection|ProductionOrderDetail[] $productionOrderDetails
 * @property-read Collection|Alert[] $alerts
 */
#[Fillable([
    'code',
    'category_id',
    'unit_of_measure_id',
    'current_price',
    'previous_price',
    'minimum_stock',
    'alert_days_before_expiry',
    'tracks_inventory',
    'is_active',
])]
class RawMaterial extends Model
{
    /** @use HasFactory<RawMaterialFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('materias_primas')
            ->setDescriptionForEvent(fn (string $eventName) => "Materia prima {$eventName}")
            ->logOnly(['code', 'unit_of_measure_id', 'current_price', 'minimum_stock', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'current_price' => 'decimal:4',
            'previous_price' => 'decimal:4',
            'minimum_stock' => 'decimal:4',
            'alert_days_before_expiry' => 'integer',
            'tracks_inventory' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope a query to only include active materials.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RawMaterialCategory::class, 'category_id');
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
