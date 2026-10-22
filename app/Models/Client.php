<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TextFormat;
use App\Models\Concerns\HasAuditDescription;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection|SalesOrder[] $salesOrders
 */
#[Fillable([
    'business_name',
    'nit',
    'contact_name',
    'phone',
    'shipping_address',
    'is_active',
])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasAuditDescription, HasFactory, LogsActivity;

    protected string $auditLabel = 'Cliente';

    protected string $auditIdentifierAttribute = 'business_name';

    /**
     * Largo de la dirección de envío, en el cliente y en el pedido que la copia: `varchar(255)` en las dos tablas
     * (D-CLI2). Los requests de clientes, pedidos y convertir cotización leen este número.
     */
    public const SHIPPING_ADDRESS_MAX_LENGTH = 255;

    /**
     * NIT y teléfono del cliente, y los que copian la cotización y el pedido (D-PV2): 20 en todos, para que un pedido
     * convertido nunca quede con un dato que su propio formulario rechace (PV-02). El NIT es texto sin regla de
     * caracteres (P8-8). El teléfono admite dígitos, espacios y `+ ( ) - . / #`, de 7 a 20 caracteres.
     */
    public const NIT_MAX_LENGTH = 20;

    public const PHONE_PATTERN = '/^[0-9 +().\/#-]{7,20}$/';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('clientes')
            ->setDescriptionForEvent(fn (string $eventName) => $this->getAuditDescription($eventName))
            ->logOnly(['business_name', 'nit', 'contact_name', 'phone', 'shipping_address', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nit' => TextFormat::Name->cast(),
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
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
