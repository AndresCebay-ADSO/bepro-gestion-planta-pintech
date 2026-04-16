<?php

namespace App\Models;

use App\Enums\ProductionOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property float|null $yield_percentage
 * @property ProductionOrderStatus $status
 * @property \Illuminate\Support\Carbon $planned_date
 * @property \Illuminate\Support\Carbon|null $completion_date
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $agitation_start_time
 * @property \Illuminate\Support\Carbon|null $agitation_end_time
 * @property float|null $viscosity_ku
 * @property float|null $grinding_hg
 * @property string|null $responsible_name
 * @property \Illuminate\Support\Carbon|null $packaging_start_time
 * @property \Illuminate\Support\Carbon|null $packaging_end_time
 * @property float $spillage_quantity
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read \App\Models\Product $product
 * @property-read \App\Models\Formula $formula
 * @property-read \App\Models\Warehouse $warehouse
 * @property-read \App\Models\User $createdBy
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\ProductionOrderDetail[] $details
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\InventoryMovement[] $inventoryMovements
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\FinishedInventoryMovement[] $finishedInventoryMovements
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\ProductionOrderPackagingPlan[] $packagingPlans
 */
#[Fillable([
    'order_number',
    'product_id',
    'formula_id',
    'warehouse_id',
    'quantity',
    'actual_quantity',
    'yield_percentage',
    'status',
    'planned_date',
    'completion_date',
    'notes',
    'agitation_start_time',
    'agitation_end_time',
    'viscosity_ku',
    'grinding_hg',
    'responsible_name',
    'packaging_start_time',
    'packaging_end_time',
    'spillage_quantity',
    'created_by',
])]
class ProductionOrder extends Model
{
    /** @use HasFactory<\Database\Factories\ProductionOrderFactory> */
    use HasFactory, LogsActivity;

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
            ->setDescriptionForEvent(fn (string $eventName) => "Orden de producción {$eventName}")
            ->logOnly(['order_number', 'actual_quantity', 'yield_percentage', 'status', 'completion_date'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'status' => ProductionOrderStatus::class,
            'quantity' => 'decimal:4',
            'actual_quantity' => 'decimal:4',
            'yield_percentage' => 'decimal:2',
            'planned_date' => 'date',
            'completion_date' => 'date',
            'agitation_start_time' => 'datetime',
            'agitation_end_time' => 'datetime',
            'viscosity_ku' => 'decimal:2',
            'grinding_hg' => 'decimal:2',
            'packaging_start_time' => 'datetime',
            'packaging_end_time' => 'datetime',
            'spillage_quantity' => 'decimal:4',
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

    public function details(): HasMany
    {
        return $this->hasMany(ProductionOrderDetail::class, 'production_order_id');
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
}
