<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $finished_product_batch_id
 * @property int $warehouse_id
 * @property float $quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read FinishedProductBatch $batch
 * @property-read Warehouse $warehouse
 */
#[Fillable([
    'finished_product_batch_id',
    'warehouse_id',
    'quantity',
])]
class FinishedProductBatchStock extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(FinishedProductBatch::class, 'finished_product_batch_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /**
     * Scope a query to only include stocks with available quantity.
     */
    public function scopeAvailable(Builder $query): void
    {
        $query->where('quantity', '>', 0);
    }

    /**
     * Scope stocks for a specific warehouse.
     */
    public function scopeForWarehouse(Builder $query, int $warehouseId): void
    {
        $query->where('warehouse_id', $warehouseId);
    }
}
