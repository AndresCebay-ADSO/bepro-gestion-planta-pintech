<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string|null $code
 * @property string $name
 * @property string $brand
 * @property string|null $description
 * @property int $category_id
 * @property int $unit_of_measure_id
 * @property float|null $current_cost
 * @property float|null $profit_margin
 * @property float|null $current_price
 * @property float $price_threshold
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @property-read \App\Models\ProductCategory $category
 * @property-read \App\Models\UnitOfMeasure $unitOfMeasure
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\FinishedInventory[] $finishedInventories
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\FinishedInventoryMovement[] $finishedInventoryMovements
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Formula[] $formulas
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\ProductionOrder[] $productionOrders
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\ProductionCost[] $productionCosts
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\PriceList[] $priceLists
 * @property-read \App\Models\QrCode|null $qrCode
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\ProductVariant[] $variants
 */
#[Fillable([
    'code',
    'name',
    'brand',
    'description',
    'category_id',
    'unit_of_measure_id',
    'current_cost',
    'profit_margin',
    'current_price',
    'price_threshold',
    'is_active',
])]
class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('productos')
            ->setDescriptionForEvent(fn (string $eventName) => "Producto {$eventName}")
            ->logOnly(['code', 'name', 'category_id', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'current_cost' => 'decimal:4',
            'profit_margin' => 'decimal:2',
            'current_price' => 'decimal:4',
            'price_threshold' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope a query to only include active products.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id');
    }

    public function finishedInventories(): HasMany
    {
        return $this->hasMany(FinishedInventory::class, 'product_id');
    }

    public function finishedInventoryMovements(): HasMany
    {
        return $this->hasMany(FinishedInventoryMovement::class, 'product_id');
    }

    public function formulas(): HasMany
    {
        return $this->hasMany(Formula::class, 'product_id');
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class, 'product_id');
    }

    public function productionCosts(): HasMany
    {
        return $this->hasMany(ProductionCost::class, 'product_id');
    }

    public function priceLists(): HasMany
    {
        return $this->hasMany(PriceList::class, 'product_id');
    }

    public function qrCode(): HasOne
    {
        return $this->hasOne(QrCode::class, 'product_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id');
    }
}
