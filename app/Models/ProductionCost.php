<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_id
 * @property int $formula_id
 * @property float $cost
 * @property float|null $variation_percentage
 * @property \Illuminate\Support\Carbon $calculated_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read \App\Models\Product $product
 * @property-read \App\Models\Formula $formula
 */
#[Fillable([
    'product_id',
    'formula_id',
    'cost',
    'variation_percentage',
    'calculated_at',
])]
class ProductionCost extends Model
{
    /** @use HasFactory<\Database\Factories\ProductionCostFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:4',
            'variation_percentage' => 'decimal:4',
            'calculated_at' => 'datetime',
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
}
