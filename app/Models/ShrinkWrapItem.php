<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShrinkWrapItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lo que gastó un termoencogido de cada materia prima: copia de la receta del tipo al registrarlo, más lo descontado y
 * su costo. No se audita aparte: nace con el registro y sus salidas quedan como movimientos de inventario.
 *
 * @property int $id
 * @property int $shrink_wrap_id
 * @property int $raw_material_id
 * @property string $quantity_per_application
 * @property string $quantity
 * @property string $total_cost
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ShrinkWrap $shrinkWrap
 * @property-read RawMaterial $rawMaterial
 */
#[Fillable([
    'shrink_wrap_id',
    'raw_material_id',
    'quantity_per_application',
    'quantity',
    'total_cost',
])]
class ShrinkWrapItem extends Model
{
    /** @use HasFactory<ShrinkWrapItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity_per_application' => 'decimal:4',
            'quantity' => 'decimal:4',
            'total_cost' => 'decimal:4',
        ];
    }

    public function shrinkWrap(): BelongsTo
    {
        return $this->belongsTo(ShrinkWrap::class, 'shrink_wrap_id');
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class, 'raw_material_id');
    }
}
