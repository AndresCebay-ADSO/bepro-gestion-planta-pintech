<?php

namespace App\Models;

use App\Enums\ProductionOrderStatus;
use App\Models\Concerns\HasAuditDescription;
use Carbon\CarbonInterface;
use Database\Factories\ProductionOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $order_number
 * @property int $product_id
 * @property int $formula_id
 * @property int $warehouse_id
 * @property float $quantity
 * @property float|null $actual_quantity
 * @property float|null $yield_real_quantity
 * @property float|null $yield_theoretical_quantity
 * @property float|null $yield_variance_quantity
 * @property float|null $yield_percentage
 * @property ProductionOrderStatus $status
 * @property Carbon $planned_date
 * @property Carbon|null $completion_date
 * @property string|null $notes
 * @property Carbon|null $agitation_start_time
 * @property Carbon|null $agitation_end_time
 * @property float|null $viscosity_ku
 * @property float|null $grinding_hg
 * @property float|null $quality_solids
 * @property string|null $responsible_name
 * @property Carbon|null $packaging_start_time
 * @property Carbon|null $packaging_end_time
 * @property float $spillage_quantity
 * @property int|null $lot_number
 * @property int $created_by
 * @property int|null $submitted_by
 * @property CarbonInterface|null $submitted_at
 * @property int|null $reviewed_by
 * @property CarbonInterface|null $reviewed_at
 * @property string|null $rejection_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read Formula $formula
 * @property-read Warehouse $warehouse
 * @property-read User $createdBy
 * @property-read Collection|ProductionOrderDetail[] $details
 * @property-read Collection|InventoryMovement[] $inventoryMovements
 * @property-read Collection|FinishedInventoryMovement[] $finishedInventoryMovements
 * @property-read Collection|ProductionOrderPackagingPlan[] $packagingPlans
 * @property-read Collection|ProductionOrderLineAdjustment[] $lineAdjustments
 * @property-read QrCode|null $qrCode
 */
#[Fillable([
    'order_number',
    'lot_number',
    'product_id',
    'formula_id',
    'warehouse_id',
    'quantity',
    'actual_quantity',
    'yield_real_quantity',
    'yield_theoretical_quantity',
    'yield_variance_quantity',
    'yield_percentage',
    'status',
    'planned_date',
    'completion_date',
    'notes',
    'agitation_start_time',
    'agitation_end_time',
    'viscosity_ku',
    'grinding_hg',
    'quality_solids',
    'responsible_name',
    'packaging_start_time',
    'packaging_end_time',
    'spillage_quantity',
    'created_by',
    'submitted_by',
    'submitted_at',
    'reviewed_by',
    'reviewed_at',
    'rejection_reason',
])]
class ProductionOrder extends Model
{
    /** @use HasFactory<ProductionOrderFactory> */
    use HasAuditDescription, HasFactory, LogsActivity;

    protected string $auditLabel = 'Orden de producción';

    protected string $auditIdentifierAttribute = 'order_number';

    protected bool $auditFeminine = true;

    protected static function booted(): void
    {
        static::saving(static function (ProductionOrder $order): void {
            $warehouse = $order->loadMissing('warehouse')->warehouse;

            if ($warehouse && ! $warehouse->isFactory()) {
                throw new \InvalidArgumentException('Solo se pueden asociar órdenes de producción a bodegas tipo Fábrica.');
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('ordenes_produccion')
            ->setDescriptionForEvent(fn (string $eventName) => $this->getAuditDescription($eventName))
            ->logOnly(['order_number', 'lot_number', 'actual_quantity', 'yield_percentage', 'status', 'completion_date', 'rejection_reason'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'status' => ProductionOrderStatus::class,
            'lot_number' => 'integer',
            'quantity' => 'decimal:4',
            'actual_quantity' => 'decimal:4',
            'yield_real_quantity' => 'decimal:4',
            'yield_theoretical_quantity' => 'decimal:4',
            'yield_variance_quantity' => 'decimal:4',
            'yield_percentage' => 'decimal:2',
            'planned_date' => 'date',
            'completion_date' => 'date',
            'agitation_start_time' => 'datetime',
            'agitation_end_time' => 'datetime',
            'viscosity_ku' => 'decimal:2',
            'grinding_hg' => 'decimal:2',
            'quality_solids' => 'decimal:2',
            'packaging_start_time' => 'datetime',
            'packaging_end_time' => 'datetime',
            'spillage_quantity' => 'decimal:4',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function formula(): BelongsTo
    {
        return $this->belongsTo(Formula::class, 'formula_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(ProductionOrderDetail::class, 'production_order_id')
            ->orderBy('step_order')
            ->orderBy('id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'production_order_id');
    }

    public function finishedInventoryMovements(): HasMany
    {
        return $this->hasMany(FinishedInventoryMovement::class, 'production_order_id');
    }

    public function packagingPlans(): HasMany
    {
        return $this->hasMany(ProductionOrderPackagingPlan::class, 'production_order_id');
    }

    public function lineAdjustments(): HasMany
    {
        return $this->hasMany(ProductionOrderLineAdjustment::class, 'production_order_id');
    }

    public function qrCode(): HasOne
    {
        return $this->hasOne(QrCode::class, 'production_order_id');
    }

    public function getManufacturingDate(): ?CarbonInterface
    {
        return $this->created_at;
    }

    public function getVerificationDate(): ?CarbonInterface
    {
        return $this->created_at?->copy()->addYear()->subDay();
    }
}
