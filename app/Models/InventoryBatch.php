<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class InventoryBatch extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['initial_quantity', 'remaining_quantity', 'unit_price', 'expiry_date', 'lot_number'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $table = 'inventory_batches';

    protected $fillable = [
        'raw_material_id',
        'initial_quantity',
        'remaining_quantity',
        'unit_price',
        'entry_date',
        'expiry_date',
        'supplier',
        'lot_number',
    ];

    protected function casts(): array
    {
        return [
            'initial_quantity' => 'decimal:4',
            'remaining_quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'entry_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class, 'raw_material_id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'batch_id');
    }

    public function productionOrderDetails(): HasMany
    {
        return $this->hasMany(ProductionOrderDetail::class, 'batch_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'batch_id');
    }
}
