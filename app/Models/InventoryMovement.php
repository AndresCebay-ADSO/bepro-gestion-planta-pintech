<?php

namespace App\Models;

use App\Enums\InventoryMovementType;
use App\Models\Concerns\HasAuditDescription;
use Database\Factories\InventoryMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $raw_material_id
 * @property int $warehouse_id
 * @property int|null $batch_id
 * @property int|null $production_order_id
 * @property InventoryMovementType $type
 * @property float $quantity
 * @property float $cost_price
 * @property Carbon $movement_date
 * @property string|null $notes
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read RawMaterial $rawMaterial
 * @property-read Warehouse $warehouse
 * @property-read InventoryBatch|null $batch
 * @property-read ProductionOrder|null $productionOrder
 * @property-read User $createdBy
 */
#[Fillable([
    'raw_material_id',
    'warehouse_id',
    'batch_id',
    'production_order_id',
    'type',
    'quantity',
    'cost_price',
    'movement_date',
    'notes',
    'created_by',
])]
class InventoryMovement extends Model
{
    /** @use HasFactory<InventoryMovementFactory> */
    use HasAuditDescription, HasFactory, LogsActivity;

    protected string $auditLabel = 'Movimiento de inventario';

    protected string $auditIdentifierAttribute = 'id';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('movimientos_inventario')
            ->setDescriptionForEvent(fn (string $eventName) => $this->getAuditDescription($eventName))
            ->logOnly(['raw_material_id', 'type', 'quantity', 'cost_price', 'production_order_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'type' => InventoryMovementType::class,
            'quantity' => 'decimal:4',
            'cost_price' => 'decimal:4',
            'movement_date' => 'date',
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

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'batch_id');
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
