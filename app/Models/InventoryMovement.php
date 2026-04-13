<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class InventoryMovement extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['raw_material_id', 'type', 'quantity', 'cost_price', 'production_order_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $table = 'inventory_movements';

    protected $fillable = [
        'raw_material_id',
        'batch_id',
        'production_order_id',
        'type',
        'quantity',
        'cost_price',
        'movement_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'cost_price' => 'decimal:4',
            'movement_date' => 'date',
        ];
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class, 'raw_material_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'batch_id');
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
