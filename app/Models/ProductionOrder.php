<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ProductionOrder extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['order_number', 'actual_quantity', 'yield_percentage', 'status', 'completion_date'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected $table = 'production_orders';

    protected $fillable = [
        'order_number',
        'product_id',
        'formula_id',
        'warehouse_id',
        'quantity',
        'actual_quantity',
        'yield_percentage',
        'status',
        'planned_date',
        'completion_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'actual_quantity' => 'decimal:4',
            'yield_percentage' => 'decimal:2',
            'planned_date' => 'date',
            'completion_date' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function formula(): BelongsTo
    {
        return $this->belongsTo(Formula::class, 'formula_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(ProductionOrderDetail::class, 'production_order_id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'production_order_id');
    }

    public function finishedInventoryMovements(): HasMany
    {
        return $this->hasMany(FinishedInventoryMovement::class, 'production_order_id');
    }
}
