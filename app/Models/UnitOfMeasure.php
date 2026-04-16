<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $symbol
 * @property string|null $description
 * @property float|null $to_kg_conversion
 * @property float|null $to_liter_conversion
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\RawMaterial[] $rawMaterials
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Product[] $products
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\FormulaDetail[] $formulaDetails
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\ProductVariant[] $productVariants
 */
#[Fillable([
    'code',
    'name',
    'symbol',
    'description',
    'to_kg_conversion',
    'to_liter_conversion',
    'is_active',
])]
class UnitOfMeasure extends Model
{
    /** @use HasFactory<\Database\Factories\UnitOfMeasureFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'to_kg_conversion' => 'decimal:4',
            'to_liter_conversion' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope a query to only include active units.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function rawMaterials(): HasMany
    {
        return $this->hasMany(RawMaterial::class, 'unit_of_measure_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'unit_of_measure_id');
    }

    public function formulaDetails(): HasMany
    {
        return $this->hasMany(FormulaDetail::class, 'unit_of_measure_id');
    }

    public function productVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'unit_of_measure_id');
    }
}
