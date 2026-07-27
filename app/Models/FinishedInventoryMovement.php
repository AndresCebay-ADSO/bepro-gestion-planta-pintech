<?php

namespace App\Models;

use App\Enums\FinishedInventoryMovementReason;
use App\Enums\InventoryMovementType;
use App\Models\Concerns\HasAuditDescription;
use App\Models\Concerns\ValidatesProductVariant;
use Database\Factories\FinishedInventoryMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property int $warehouse_id
 * @property int|null $production_order_id
 * @property int|null $finished_product_batch_id
 * @property InventoryMovementType $type
 * @property FinishedInventoryMovementReason $reason
 * @property float $quantity
 * @property float|null $cost_price
 * @property Carbon $movement_date
 * @property string|null $notes
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read ProductVariant|null $productVariant
 * @property-read Warehouse $warehouse
 * @property-read ProductionOrder|null $productionOrder
 * @property-read FinishedProductBatch|null $finishedProductBatch
 * @property-read User $createdBy
 */
#[Fillable([
    'product_id',
    'product_variant_id',
    'warehouse_id',
    'production_order_id',
    'finished_product_batch_id',
    'type',
    'reason',
    'quantity',
    'cost_price',
    'movement_date',
    'notes',
    'created_by',
])]
class FinishedInventoryMovement extends Model
{
    /** @use HasFactory<FinishedInventoryMovementFactory> */
    use HasAuditDescription, HasFactory, LogsActivity, ValidatesProductVariant;

    protected string $auditLabel = 'Movimiento de inv. terminado';

    protected string $auditIdentifierAttribute = 'id';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('movimientos_inventario_terminado')
            ->setDescriptionForEvent(fn (string $eventName) => $this->getAuditDescription($eventName))
            ->logOnly(['product_id', 'product_variant_id', 'warehouse_id', 'type', 'quantity', 'production_order_id', 'reason'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'type' => InventoryMovementType::class,
            'reason' => FinishedInventoryMovementReason::class,
            'quantity' => 'decimal:4',
            'cost_price' => 'decimal:4',
            'movement_date' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(FinishedProductBatch::class, 'finished_product_batch_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
