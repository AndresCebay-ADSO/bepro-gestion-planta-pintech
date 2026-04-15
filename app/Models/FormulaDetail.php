<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $formula_id
 * @property int $raw_material_id
 * @property float $quantity
 * @property int $unit_of_measure_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read \App\Models\Formula $formula
 * @property-read \App\Models\RawMaterial $rawMaterial
 * @property-read \App\Models\UnitOfMeasure $unitOfMeasure
 */
#[Fillable([
    'formula_id',
    'raw_material_id',
    'quantity',
    'unit_of_measure_id',
])]
class FormulaDetail extends Model
{
    /** @use HasFactory<\Database\Factories\FormulaDetailFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
        ];
    }

    public function formula(): BelongsTo
    {
        return $this->belongsTo(Formula::class, 'formula_id');
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class, 'raw_material_id');
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id');
    }
}
