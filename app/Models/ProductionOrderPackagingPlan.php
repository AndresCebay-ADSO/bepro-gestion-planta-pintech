<?php

namespace App\Models;

use Database\Factories\ProductionOrderPackagingPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $production_order_id
 * @property int $product_variant_id
 * @property float $planned_units
 * @property float|null $actual_units
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProductionOrder $productionOrder
 * @property-read ProductVariant $productVariant
 */
#[Fillable([
    'production_order_id',
    'product_variant_id',
    'planned_units',
    'actual_units',
    'notes',
])]
class ProductionOrderPackagingPlan extends Model
{
    /** @use HasFactory<ProductionOrderPackagingPlanFactory> */
    use HasFactory;

    protected $table = 'production_order_packaging_plan';

    protected function casts(): array
    {
        return [
            'planned_units' => 'decimal:4',
            'actual_units' => 'decimal:4',
        ];
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
