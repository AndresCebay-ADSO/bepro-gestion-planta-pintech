<?php

namespace App\Models;

use App\Enums\ComponentSystem;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $product_id
 * @property string $sku
 * @property int $unit_of_measure_id
 * @property float|null $presentation_value
 * @property string|null $presentation_label
 * @property string|null $color
 * @property string|null $finish
 * @property string|null $base_type
 * @property ComponentSystem $component_system
 * @property float|null $current_cost
 * @property float|null $current_price
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @property-read \App\Models\Product $product
 * @property-read \App\Models\UnitOfMeasure $unitOfMeasure
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\FinishedInventory[] $finishedInventories
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\FinishedInventoryMovement[] $finishedInventoryMovements
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Transfer[] $transfers
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\PriceList[] $priceLists
 */
#[Fillable([
    'product_id',
    'sku',
    'unit_of_measure_id',
    'presentation_value',
    'presentation_label',
    'color',
    'finish',
    'base_type',
    'component_system',
    'current_cost',
    'current_price',
    'is_active',
])]
class ProductVariant extends Model
{
    /** @use HasFactory<\Database\Factories\ProductVariantFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('variantes_producto')
            ->setDescriptionForEvent(fn (string $eventName) => "Variante de producto {$eventName}")
            ->logOnly([
                'product_id',
                'sku',
                'presentation_value',
                'presentation_label',
                'color',
                'finish',
                'base_type',
                'component_system',
                'current_cost',
                'current_price',
                'is_active',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'component_system' => ComponentSystem::class,
            'presentation_value' => 'decimal:4',
            'current_cost' => 'decimal:4',
            'current_price' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope a query to only include active variants.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id');
    }

    public function finishedInventories(): HasMany
    {
        return $this->hasMany(FinishedInventory::class, 'product_variant_id');
    }

    public function finishedInventoryMovements(): HasMany
    {
        return $this->hasMany(FinishedInventoryMovement::class, 'product_variant_id');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'product_variant_id');
    }

    public function priceLists(): HasMany
    {
        return $this->hasMany(PriceList::class, 'product_variant_id');
    }
}
