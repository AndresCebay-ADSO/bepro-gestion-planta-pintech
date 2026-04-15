<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class FinishedInventoryMovement extends Model
{
    use HasFactory, LogsActivity;

    protected static function booted(): void
    {
        static::saving(static function (FinishedInventoryMovement $movement): void {
            if (! $movement->product_id && ! $movement->product_variant_id) {
                throw ValidationException::withMessages([
                    'product_variant_id' => __('Debe seleccionar un producto o una variante de producto.'),
                ]);
            }

            if ($movement->product_variant_id) {
                $variant = $movement->productVariant ?? ProductVariant::find($movement->product_variant_id);

                if (! $variant) {
                    throw ValidationException::withMessages([
                        'product_variant_id' => __('La variante de producto seleccionada no existe.'),
                    ]);
                }

                if (! $movement->product_id) {
                    $movement->product_id = $variant->product_id;
                }

                if ((int) $movement->product_id !== (int) $variant->product_id) {
                    throw ValidationException::withMessages([
                        'product_id' => __('El producto no corresponde a la variante seleccionada.'),
                    ]);
                }
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['product_id', 'product_variant_id', 'warehouse_id', 'type', 'quantity', 'production_order_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'warehouse_id',
        'production_order_id',
        'type',
        'quantity',
        'movement_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'movement_date' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
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
