<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $production_order_id
 * @property int $batch_id
 * @property int $raw_material_id
 * @property float $planned_quantity
 * @property float|null $actual_quantity
 * @property float $unit_cost
 * @property float $total_cost
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read \App\Models\ProductionOrder $productionOrder
 * @property-read \App\Models\InventoryBatch $batch
 * @property-read \App\Models\RawMaterial $rawMaterial
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
    /** @use HasFactory<\Database\Factories\ProductionOrderDetailFactory> */
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
