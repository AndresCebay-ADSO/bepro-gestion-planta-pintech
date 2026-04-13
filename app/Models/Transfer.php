<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Transfer extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'source_warehouse_id',
        'destination_warehouse_id',
        'product_id',
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
            ->logOnly(['source_warehouse_id', 'destination_warehouse_id', 'product_id', 'quantity', 'status'])
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
