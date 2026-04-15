<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitOfMeasure extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'description',
        'to_kg_conversion',
        'to_liter_conversion',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'to_kg_conversion' => 'decimal:4',
            'to_liter_conversion' => 'decimal:4',
            'is_active' => 'boolean',
        ];
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
