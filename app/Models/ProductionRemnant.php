<?php

namespace App\Models;

use App\Enums\RemnantStatus;
use App\Models\Concerns\HasAuditDescription;
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
 * @property int $source_order_id
 * @property int $product_id
 * @property int $warehouse_id
 * @property float $original_quantity_gallons
 * @property float $original_quantity_kg
 * @property float $available_quantity_gallons
 * @property float $available_quantity_kg
 * @property float $density_kg_per_gallon
 * @property float|null $cost_per_gallon
 * @property RemnantStatus $status
 * @property string|null $notes
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProductionOrder $sourceOrder
 * @property-read Product $product
 * @property-read Warehouse $warehouse
 * @property-read User $createdBy
 * @property-read Collection|RemnantConsumption[] $consumptions
 */
#[Fillable([
    'source_order_id',
    'product_id',
    'warehouse_id',
    'original_quantity_gallons',
    'original_quantity_kg',
    'available_quantity_gallons',
    'available_quantity_kg',
    'density_kg_per_gallon',
    'cost_per_gallon',
    'status',
    'notes',
    'created_by',
])]
class ProductionRemnant extends Model
{
    use HasAuditDescription, HasFactory, LogsActivity;

    protected string $auditLabel = 'Saldo de PT';

    protected string $auditIdentifierAttribute = 'id';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('saldos_produccion')
            ->setDescriptionForEvent(fn (string $eventName) => $this->getAuditDescription($eventName))
            ->logOnly([
                'source_order_id',
                'product_id',
                'available_quantity_gallons',
                'available_quantity_kg',
                'status',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'status' => RemnantStatus::class,
            'original_quantity_gallons' => 'decimal:4',
            'original_quantity_kg' => 'decimal:4',
            'available_quantity_gallons' => 'decimal:4',
            'available_quantity_kg' => 'decimal:4',
            'density_kg_per_gallon' => 'decimal:4',
            'cost_per_gallon' => 'decimal:4',
        ];
    }

    /**
     * Scope a query to only include remnants with stock available.
     */
    public function scopeAvailable(Builder $query): void
    {
        $query->whereIn('status', [RemnantStatus::Available, RemnantStatus::PartiallyConsumed]);
    }

    public function sourceOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'source_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(RemnantConsumption::class, 'remnant_id');
    }
}
