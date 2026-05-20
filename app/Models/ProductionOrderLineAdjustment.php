<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $production_order_id
 * @property int $raw_material_id
 * @property float $quantity
 * @property string $reason
 * @property string|null $notes
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProductionOrder $productionOrder
 * @property-read RawMaterial $rawMaterial
 * @property-read User $createdBy
 */
#[Fillable([
    'production_order_id',
    'raw_material_id',
    'quantity',
    'reason',
    'notes',
    'created_by',
])]
class ProductionOrderLineAdjustment extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
        ];
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class, 'raw_material_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
