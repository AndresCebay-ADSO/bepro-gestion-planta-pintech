<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $raw_material_id
 * @property int $warehouse_id
 * @property float $initial_quantity
 * @property float $remaining_quantity
 * @property float $unit_price
 * @property \Illuminate\Support\Carbon $entry_date
 * @property \Illuminate\Support\Carbon|null $expiry_date
 * @property string|null $supplier
 * @property string|null $lot_number
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read \App\Models\RawMaterial $rawMaterial
 * @property-read \App\Models\Warehouse $warehouse
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\InventoryMovement[] $inventoryMovements
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\ProductionOrderDetail[] $productionOrderDetails
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Alert[] $alerts
 */
#[Fillable([
    'raw_material_id',
    'warehouse_id',
    'initial_quantity',
    'remaining_quantity',
    'unit_price',
    'entry_date',
    'expiry_date',
    'supplier',
    'lot_number',
])]
class InventoryBatch extends Model
{
    /** @use HasFactory<\Database\Factories\InventoryBatchFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('lotes_inventario')
            ->setDescriptionForEvent(fn (string $eventName) => "Lote de inventario {$eventName}")
            ->logOnly(['initial_quantity', 'remaining_quantity', 'unit_price', 'expiry_date', 'lot_number'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'initial_quantity' => 'decimal:4',
            'remaining_quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'entry_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class, 'raw_material_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'batch_id');
    }

    public function productionOrderDetails(): HasMany
    {
        return $this->hasMany(ProductionOrderDetail::class, 'batch_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'batch_id');
    }
}
