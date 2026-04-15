<?php

namespace App\Models\Concerns;

use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

/**
 * Validates product/variant consistency on saving.
 *
 * Models using this trait MUST define:
 * - `product(): BelongsTo`
 * - `productVariant(): BelongsTo`
 * - `product_id` and `product_variant_id` columns
 */
trait ValidatesProductVariant
{
    protected static function bootValidatesProductVariant(): void
    {
        static::saving(static function (self $model): void {
            if (! $model->product_id && ! $model->product_variant_id) {
                throw ValidationException::withMessages([
                    'product_variant_id' => __('Debe seleccionar un producto o una variante de producto.'),
                ]);
            }

            if ($model->product_variant_id) {
                $variant = $model->productVariant ?? ProductVariant::find($model->product_variant_id);

                if (! $variant) {
                    throw ValidationException::withMessages([
                        'product_variant_id' => __('La variante de producto seleccionada no existe.'),
                    ]);
                }

                if (! $model->product_id) {
                    $model->product_id = $variant->product_id;
                }

                if ((int) $model->product_id !== (int) $variant->product_id) {
                    throw ValidationException::withMessages([
                        'product_id' => __('El producto no corresponde a la variante seleccionada.'),
                    ]);
                }
            }
        });
    }
}
