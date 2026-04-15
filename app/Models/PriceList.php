<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PriceList extends Model
{
    use HasFactory, LogsActivity;

    protected static function booted(): void
    {
        static::saving(static function (PriceList $priceList): void {
            if (! $priceList->product_id && ! $priceList->product_variant_id) {
                throw ValidationException::withMessages([
                    'product_variant_id' => __('Debe seleccionar un producto o una variante de producto.'),
                ]);
            }

            if ($priceList->product_variant_id) {
                $variant = $priceList->loadMissing('productVariant')->productVariant;

                if (! $variant) {
                    throw ValidationException::withMessages([
                        'product_variant_id' => __('La variante de producto seleccionada no existe.'),
                    ]);
                }

                if (! $priceList->product_id) {
                    $priceList->product_id = $variant->product_id;
                }

                if ((int) $priceList->product_id !== (int) $variant->product_id) {
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
            ->logOnly(['product_id', 'product_variant_id', 'price', 'profit_margin', 'update_type'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'price',
        'cost_at_time',
        'profit_margin',
        'update_type',
        'variation_percentage',
        'valid_from',
        'valid_to',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'cost_at_time' => 'decimal:4',
            'profit_margin' => 'decimal:2',
            'variation_percentage' => 'decimal:4',
            'valid_from' => 'date',
            'valid_to' => 'date',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
