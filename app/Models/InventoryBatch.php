<?php

// declare(strict_types=1); Estudiar si es necesario para los modelos.

namespace App\Models;

use App\Models\Concerns\HasAuditDescription;
use Database\Factories\InventoryBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
 * @property int $raw_material_id
 * @property int $warehouse_id
 * @property float $initial_quantity
 * @property float $remaining_quantity
 * @property float $unit_price
 * @property Carbon $entry_date
 * @property Carbon|null $expiry_date
 * @property string|null $supplier
 * @property string|null $lot_number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read RawMaterial $rawMaterial
 * @property-read Warehouse $warehouse
 * @property-read Collection|InventoryMovement[] $inventoryMovements
 * @property-read Collection|ProductionOrderDetail[] $productionOrderDetails
 * @property-read Collection|Alert[] $alerts
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
    /** @use HasFactory<InventoryBatchFactory> */
    use HasAuditDescription, HasFactory, LogsActivity;

    protected string $auditLabel = 'Lote de inventario';

    protected string $auditIdentifierAttribute = 'lot_number';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('lotes_inventario')
            ->setDescriptionForEvent(fn (string $eventName) => $this->getAuditDescription($eventName))
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
            'entry_date' => 'date:Y-m-d',
            'expiry_date' => 'date:Y-m-d',
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
