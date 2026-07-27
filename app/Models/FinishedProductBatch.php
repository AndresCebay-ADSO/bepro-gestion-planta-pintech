<?php

namespace App\Models;

use App\Models\Concerns\HasAuditDescription;
use App\Models\Concerns\ValidatesProductVariant;
use Database\Factories\FinishedProductBatchFactory;
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
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property int|null $production_order_id
 * @property float $initial_quantity
 * @property Carbon $entry_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read ProductVariant|null $productVariant
 * @property-read ProductionOrder|null $productionOrder
 * @property-read Collection|FinishedProductBatchStock[] $stocks
 * @property-read Collection|FinishedInventoryMovement[] $movements
 */
#[Fillable([
    'product_id',
    'product_variant_id',
    'production_order_id',
    'initial_quantity',
    'entry_date',
])]
class FinishedProductBatch extends Model
{
    /** @use HasFactory<FinishedProductBatchFactory> */
    use HasAuditDescription, HasFactory, LogsActivity, ValidatesProductVariant;

    protected string $auditLabel = 'Lote de producto terminado';

    protected string $auditIdentifierAttribute = 'id';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('lotes_producto_terminado')
            ->setDescriptionForEvent(fn (string $eventName) => $this->getAuditDescription($eventName))
            ->logOnly(['product_id', 'product_variant_id', 'production_order_id', 'initial_quantity', 'entry_date'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'initial_quantity' => 'decimal:4',
            'entry_date' => 'date',
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

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(FinishedProductBatchStock::class, 'finished_product_batch_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(FinishedInventoryMovement::class, 'finished_product_batch_id');
    }

    /**
     * Scope a query to only include batches that have stock available.
     */
    public function scopeAvailable(Builder $query): void
    {
        $query->whereHas('stocks', function ($q): void {
            $q->where('quantity', '>', 0);
        });
    }

    /**
     * Scope to order by FIFO (oldest first).
     */
    public function scopeFifoOrder(Builder $query): void
    {
        $query->orderBy('entry_date')->orderBy('id');
    }
}