<?php

namespace App\Models;

use App\Models\Concerns\HasAuditDescription;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $business_name
 * @property string|null $nit
 * @property string|null $contact_name
 * @property string|null $phone
 * @property string|null $shipping_address
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection|SalesOrder[] $salesOrders
 */
#[Fillable([
    'business_name',
    'nit',
    'contact_name',
    'phone',
    'shipping_address',
])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasAuditDescription, HasFactory, LogsActivity, SoftDeletes;

    protected string $auditLabel = 'Cliente';

    protected string $auditIdentifierAttribute = 'business_name';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('clientes')
            ->setDescriptionForEvent(fn (string $eventName) => $this->getAuditDescription($eventName))
            ->logOnly(['business_name', 'nit', 'contact_name', 'phone', 'shipping_address'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('deleted_at');
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class, 'client_id');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class, 'client_id');
    }
}
