<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasAuditDescription;
use Database\Factories\ShrinkWrapFactory;
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
 * Registro de termoencogido (3.8): el operario termoencogió producto de una OP completada con un tipo, tantas veces.
 * Al guardarse descuenta el empaque secundario de la bodega de la OP. Inmutable: un error se corrige con un movimiento
 * opuesto y una nota. Su costo es gasto general: no entra al costo del lote ni al de la OP.
 *
 * @property int $id
 * @property int $production_order_id
 * @property int $shrink_wrap_type_id
 * @property int $warehouse_id
 * @property int $applications
 * @property Carbon $wrapped_at
 * @property string $total_cost
 * @property string|null $notes
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProductionOrder $productionOrder
 * @property-read ShrinkWrapType $shrinkWrapType
 * @property-read Warehouse $warehouse
 * @property-read User $createdBy
 * @property-read Collection|ShrinkWrapItem[] $items
 * @property-read Collection|InventoryMovement[] $inventoryMovements
 */
#[Fillable([
    'production_order_id',
    'shrink_wrap_type_id',
    'warehouse_id',
    'applications',
    'wrapped_at',
    'total_cost',
    'notes',
    'created_by',
])]
class ShrinkWrap extends Model
{
    /** @use HasFactory<ShrinkWrapFactory> */
    use HasAuditDescription, HasFactory, LogsActivity;

    /**
     * Una OP se ofrece para termoencogido hasta estos meses después de completarse: el registro puede llegar semanas
     * después, y la ventana evita cargar en el selector todo el historial de órdenes (decisión del 2026-10-06).
     */
    public const ORDER_WINDOW_MONTHS = 6;

    protected string $auditLabel = 'Termoencogido';

    protected string $auditIdentifierAttribute = 'id';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('termoencogidos')
            ->setDescriptionForEvent(fn (string $eventName) => $this->getAuditDescription($eventName))
            // Sin el costo: se calcula después de crear el registro (las salidas necesitan su id) y quedaría auditado como
            // un 0 seguido de una edición que nadie hizo. El costo de cada salida ya queda en su movimiento.
            ->logOnly(['production_order_id', 'shrink_wrap_type_id', 'warehouse_id', 'applications', 'wrapped_at', 'notes'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'applications' => 'integer',
            'wrapped_at' => 'date:Y-m-d',
            'total_cost' => 'decimal:4',
        ];
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function shrinkWrapType(): BelongsTo
    {
        return $this->belongsTo(ShrinkWrapType::class, 'shrink_wrap_type_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShrinkWrapItem::class, 'shrink_wrap_id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'shrink_wrap_id');
    }
}
