<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ProductVariant extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'product_id',
        'sku',
        'unit_of_measure_id',
        'presentation_value',
        'presentation_label',
        'color',
        'finish',
        'base_type',
        'component_system',
        'current_cost',
        'current_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'presentation_value' => 'decimal:4',
            'current_cost' => 'decimal:4',
            'current_price' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'product_id',
                'sku',
                'presentation_value',
                'presentation_label',
                'color',
                'finish',
                'base_type',
                'component_system',
                'current_cost',
                'current_price',
                'is_active',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id');
    }

    public function finishedInventories(): HasMany
    {
        return $this->hasMany(FinishedInventory::class, 'product_variant_id');
    }

    public function finishedInventoryMovements(): HasMany
    {
        return $this->hasMany(FinishedInventoryMovement::class, 'product_variant_id');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'product_variant_id');
    }

    public function priceLists(): HasMany
    {
        return $this->hasMany(PriceList::class, 'product_variant_id');
    }
}
