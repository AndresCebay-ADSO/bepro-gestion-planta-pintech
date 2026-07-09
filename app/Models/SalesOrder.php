<?php

namespace App\Models;

use App\Enums\SalesOrderPriority;
use App\Enums\SalesOrderStatus;
use App\Models\Concerns\HasAuditDescription;
use Database\Factories\SalesOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $client_id
 * @property SalesOrderPriority $priority
 * @property SalesOrderStatus $status
 * @property Carbon|null $required_date
 * @property Carbon|null $estimated_delivery_date
 * @property string|null $notes
 * @property string|null $shipping_address
 * @property string|null $client_business_name
 * @property string|null $client_nit
 * @property string|null $client_contact_name
 * @property string|null $client_phone
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Client $client
 * @property-read User $creator
 * @property-read Collection|SalesOrderItem[] $items
 */
#[Fillable([
    'client_id',
    'status',
    'required_date',
    'estimated_delivery_date',
    'notes',
    'shipping_address',
    'client_business_name',
    'client_nit',
    'client_contact_name',
    'client_phone',
    'priority',
    'created_by',
])]
class SalesOrder extends Model
{
    /** @use HasFactory<SalesOrderFactory> */
    use HasAuditDescription, HasFactory, LogsActivity, SoftDeletes;

    protected string $auditLabel = 'Pedido';

    protected string $auditIdentifierAttribute = 'id';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('pedidos')
            ->setDescriptionForEvent(fn (string $eventName) => $this->getAuditDescription($eventName))
            ->logOnly(['client_id', 'status', 'required_date', 'estimated_delivery_date', 'notes', 'shipping_address', 'client_business_name', 'client_nit', 'client_contact_name', 'client_phone', 'priority'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'status' => SalesOrderStatus::class,
            'priority' => SalesOrderPriority::class,
            'required_date' => 'date',
            'estimated_delivery_date' => 'date',
        ];
    }

    public function scopeByStatus(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }

    public function scopePending(Builder $query): void
    {
        $query->whereIn('status', [SalesOrderStatus::Pending->value, SalesOrderStatus::InProgress->value]);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class, 'sales_order_id');
    }
}
