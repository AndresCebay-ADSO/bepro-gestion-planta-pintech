<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Transfer extends Model
{
    use HasFactory, LogsActivity;

    protected static function booted(): void
    {
        static::saving(static function (Transfer $transfer): void {
            if (! $transfer->product_id && ! $transfer->product_variant_id) {
                throw ValidationException::withMessages([
                    'product_variant_id' => __('Debe seleccionar un producto o una variante de producto.'),
                ]);
            }

            if ($transfer->product_variant_id) {
                $variant = $transfer->productVariant ?? ProductVariant::find($transfer->product_variant_id);

                if (! $variant) {
                    throw ValidationException::withMessages([
                        'product_variant_id' => __('La variante de producto seleccionada no existe.'),
                    ]);
                }

                if (! $transfer->product_id) {
                    $transfer->product_id = $variant->product_id;
                }

                if ((int) $transfer->product_id !== (int) $variant->product_id) {
                    throw ValidationException::withMessages([
                        'product_id' => __('El producto no corresponde a la variante seleccionada.'),
                    ]);
                }
            }

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

    protected $fillable = [
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
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['source_warehouse_id', 'destination_warehouse_id', 'product_id', 'product_variant_id', 'quantity', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
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
