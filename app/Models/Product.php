<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Product extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('productos')
            ->setDescriptionForEvent(fn(string $eventName) => "Producto {$eventName}")
            ->logOnly(['code', 'name', 'category_id', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $table = 'products';

    protected $fillable = [
        'code',
        'name',
        'category_id',
        'unit_of_measure_id',
        'current_cost',
        'profit_margin',
        'current_price',
        'price_threshold',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'current_cost' => 'decimal:4',
            'profit_margin' => 'decimal:2',
            'current_price' => 'decimal:4',
            'price_threshold' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id');
    }

    public function finishedInventories(): HasMany
    {
        return $this->hasMany(FinishedInventory::class, 'product_id');
    }

    public function finishedInventoryMovements(): HasMany
    {
        return $this->hasMany(FinishedInventoryMovement::class, 'product_id');
    }

    public function formulas(): HasMany
    {
        return $this->hasMany(Formula::class, 'product_id');
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class, 'product_id');
    }

    public function productionCosts(): HasMany
    {
        return $this->hasMany(ProductionCost::class, 'product_id');
    }

    public function priceLists(): HasMany
    {
        return $this->hasMany(PriceList::class, 'product_id');
    }

    public function qrCode(): HasOne
    {
        return $this->hasOne(QrCode::class, 'product_id');
    }
}
