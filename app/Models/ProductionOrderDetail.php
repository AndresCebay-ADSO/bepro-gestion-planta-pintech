<?php

namespace App\Models;

use Database\Factories\ProductionOrderDetailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $production_order_id
 * @property int $batch_id
 * @property int $raw_material_id
 * @property float $planned_quantity
 * @property float|null $actual_quantity
 * @property float $unit_cost
 * @property float $total_cost
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProductionOrder $productionOrder
 * @property-read InventoryBatch $batch
 * @property-read RawMaterial $rawMaterial
 */
#[Fillable([
    'production_order_id',
    'batch_id',
    'raw_material_id',
    'planned_quantity',
    'actual_quantity',
    'unit_cost',
    'total_cost',
])]
class ProductionOrderDetail extends Model
{
    /** @use HasFactory<ProductionOrderDetailFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'planned_quantity' => 'decimal:4',
            'actual_quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:4',
        ];
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'batch_id');
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class, 'raw_material_id');
    }
}
