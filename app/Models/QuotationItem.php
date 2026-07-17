<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuotationItemType;
use Database\Factories\QuotationItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $quotation_id
 * @property int $product_id
 * @property int $product_variant_id
 * @property QuotationItemType|null $type
 * @property string|null $description
 * @property string|null $color
 * @property float $quantity
 * @property float $list_unit_price
 * @property float $price_adjustment_pct
 * @property float $unit_price
 * @property float $subtotal
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Quotation $quotation
 * @property-read Product $product
 * @property-read ProductVariant $productVariant
 */
#[Fillable([
    'quotation_id',
    'product_id',
    'product_variant_id',
    'type',
    'description',
    'color',
    'quantity',
    'list_unit_price',
    'price_adjustment_pct',
    'unit_price',
    'subtotal',
    'sort_order',
])]
class QuotationItem extends Model
{
    /** @use HasFactory<QuotationItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'list_unit_price' => 'decimal:4',
            'price_adjustment_pct' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'sort_order' => 'integer',
            'type' => QuotationItemType::class,
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
