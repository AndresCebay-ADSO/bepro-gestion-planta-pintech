<?php

namespace App\Models;

use App\Enums\PriceUpdateType;
use App\Models\Concerns\ValidatesProductVariant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property float $price
 * @property float $cost_at_time
 * @property float $profit_margin
 * @property PriceUpdateType $update_type
 * @property float|null $variation_percentage
 * @property \Illuminate\Support\Carbon $valid_from
 * @property \Illuminate\Support\Carbon|null $valid_to
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read \App\Models\Product $product
 * @property-read \App\Models\ProductVariant|null $productVariant
 * @property-read \App\Models\User|null $createdBy
 */
#[Fillable([
    'product_id',
    'product_variant_id',
    'price',
    'cost_at_time',
    'profit_margin',
    'update_type',
    'variation_percentage',
    'valid_from',
    'valid_to',
    'created_by',
])]
class PriceList extends Model
{
    /** @use HasFactory<\Database\Factories\PriceListFactory> */
    use HasFactory, LogsActivity, ValidatesProductVariant;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('listas_precios')
            ->setDescriptionForEvent(fn (string $eventName) => "Lista de precios {$eventName}")
            ->logOnly(['product_id', 'product_variant_id', 'price', 'profit_margin', 'update_type'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'update_type' => PriceUpdateType::class,
            'price' => 'decimal:4',
            'cost_at_time' => 'decimal:4',
            'profit_margin' => 'decimal:2',
            'variation_percentage' => 'decimal:4',
            'valid_from' => 'date',
            'valid_to' => 'date',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
