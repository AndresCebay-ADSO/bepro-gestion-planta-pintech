<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class FinishedInventory extends Model
{
    use HasFactory, LogsActivity;

    protected static function booted(): void
    {
        static::saving(static function (FinishedInventory $inventory): void {
            if (! $inventory->product_id && ! $inventory->product_variant_id) {
                throw ValidationException::withMessages([
                    'product_variant_id' => __('Debe seleccionar un producto o una variante de producto.'),
                ]);
            }

            if ($inventory->product_variant_id) {
                $variant = $inventory->productVariant ?? ProductVariant::find($inventory->product_variant_id);

                if (! $variant) {
                    throw ValidationException::withMessages([
                        'product_variant_id' => __('La variante de producto seleccionada no existe.'),
                    ]);
                }

                if (! $inventory->product_id) {
                    $inventory->product_id = $variant->product_id;
                }

                if ((int) $inventory->product_id !== (int) $variant->product_id) {
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
            ->logOnly(['product_id', 'product_variant_id', 'warehouse_id', 'quantity'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'warehouse_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
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
}
