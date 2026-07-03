<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $remnant_id
 * @property int|null $target_order_id
 * @property float $consumed_cost
 * @property float $quantity_gallons
 * @property float $quantity_kg
 * @property int $consumed_by
 * @property Carbon $consumed_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProductionRemnant $remnant
 * @property-read ProductionOrder|null $targetOrder
 * @property-read User $consumedBy
 */
#[Fillable([
    'remnant_id',
    'target_order_id',
    'consumed_cost',
    'quantity_gallons',
    'quantity_kg',
    'consumed_by',
    'consumed_at',
    'notes',
])]
class RemnantConsumption extends Model
{
    protected function casts(): array
    {
        return [
            'consumed_cost' => 'decimal:4',
            'quantity_gallons' => 'decimal:4',
            'quantity_kg' => 'decimal:4',
            'consumed_at' => 'datetime',
        ];
    }

    public function remnant(): BelongsTo
    {
        return $this->belongsTo(ProductionRemnant::class, 'remnant_id');
    }

    public function targetOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'target_order_id');
    }

    public function consumedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consumed_by');
    }
}
