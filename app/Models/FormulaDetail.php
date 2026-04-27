<?php

namespace App\Models;

use Database\Factories\FormulaDetailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $formula_id
 * @property int $raw_material_id
 * @property float $quantity
 * @property int $unit_of_measure_id
 * @property int $step_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Formula $formula
 * @property-read RawMaterial $rawMaterial
 * @property-read UnitOfMeasure $unitOfMeasure
 */
#[Fillable([
    'formula_id',
    'raw_material_id',
    'quantity',
    'unit_of_measure_id',
    'step_order',
])]
class FormulaDetail extends Model
{
    /** @use HasFactory<FormulaDetailFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'step_order' => 'integer',
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
