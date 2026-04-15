<?php

namespace App\Models;

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
 * @property int $warehouse_id
 * @property float $quantity
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read \App\Models\Product $product
 * @property-read \App\Models\ProductVariant|null $productVariant
 * @property-read \App\Models\Warehouse $warehouse
 */
#[Fillable([
    'product_id',
    'product_variant_id',
    'warehouse_id',
    'quantity',
])]
class FinishedInventory extends Model
{
    /** @use HasFactory<\Database\Factories\FinishedInventoryFactory> */
    use HasFactory, LogsActivity, ValidatesProductVariant;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('inventario_terminado')
            ->setDescriptionForEvent(fn (string $eventName) => "Inventario terminado {$eventName}")
            ->logOnly(['product_id', 'product_variant_id', 'warehouse_id', 'quantity'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
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

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
