<?php

namespace App\Models;

use Database\Factories\UnitOfMeasureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $symbol
 * @property string|null $description
 * @property float|null $to_kg_conversion
 * @property float|null $to_liter_conversion
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection|RawMaterial[] $rawMaterials
 * @property-read Collection|Product[] $products
 * @property-read Collection|FormulaDetail[] $formulaDetails
 * @property-read Collection|ProductVariant[] $productVariants
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
    /** @use HasFactory<UnitOfMeasureFactory> */
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
