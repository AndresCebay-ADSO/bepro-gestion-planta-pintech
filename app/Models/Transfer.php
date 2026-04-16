<?php

namespace App\Models;

use App\Enums\TransferStatus;
use App\Models\Concerns\ValidatesProductVariant;
use Database\Factories\TransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $source_warehouse_id
 * @property int $destination_warehouse_id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property float $quantity
 * @property TransferStatus $status
 * @property string|null $notes
 * @property int $created_by
 * @property Carbon|null $sent_at
 * @property Carbon|null $received_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Warehouse $sourceWarehouse
 * @property-read Warehouse $destinationWarehouse
 * @property-read Product $product
 * @property-read ProductVariant|null $productVariant
 * @property-read User $createdBy
 */
#[Fillable([
    'source_warehouse_id',
    'destination_warehouse_id',
    'product_id',
    'product_variant_id',
    'quantity',
    'status',
    'notes',
    'created_by',
    'sent_at',
    'received_at',
])]
class Transfer extends Model
{
    /** @use HasFactory<TransferFactory> */
    use HasFactory, LogsActivity, ValidatesProductVariant;

    protected static function booted(): void
    {
        static::saving(static function (Transfer $transfer): void {
            if ($transfer->source_warehouse_id === $transfer->destination_warehouse_id) {
                throw new \InvalidArgumentException('La bodega de origen y destino no pueden ser la misma.');
            }

            if ($transfer->quantity <= 0) {
                throw new \InvalidArgumentException('La cantidad debe ser mayor a cero.');
            }

            $source = $transfer->sourceWarehouse ?? Warehouse::find($transfer->source_warehouse_id);
            $dest = $transfer->destinationWarehouse ?? Warehouse::find($transfer->destination_warehouse_id);

            if ($source && ! $source->isFactory()) {
                throw new \InvalidArgumentException('Los traslados solo pueden originarse en una Fábrica.');
            }

            if ($dest && $dest->isFactory()) {
                throw new \InvalidArgumentException('El destino de un traslado no puede ser una Fábrica.');
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('traslados')
            ->setDescriptionForEvent(fn (string $eventName) => "Traslado {$eventName}")
            ->logOnly(['source_warehouse_id', 'destination_warehouse_id', 'product_id', 'product_variant_id', 'quantity', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'status' => TransferStatus::class,
            'quantity' => 'decimal:4',
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
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
