<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\QuotationStatus;
use App\Enums\QuotationValidity;
use App\Models\Concerns\HasAuditDescription;
use Database\Factories\QuotationFactory;
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
 * @property string|null $client_business_name
 * @property string|null $client_nit
 * @property string|null $client_contact_name
 * @property string|null $client_phone
 * @property int|null $quotation_number
 * @property string|null $technology
 * @property string|null $line
 * @property string|null $thickness_mils
 * @property string|null $application_method
 * @property Carbon|null $quotation_date
 * @property QuotationValidity|null $validity_days
 * @property PaymentMethod|null $payment_method
 * @property string|null $delivery_time
 * @property string|null $area
 * @property string|null $notes
 * @property float $subtotal
 * @property float $iva_percentage
 * @property float $iva_amount
 * @property float $total
 * @property QuotationStatus $status
 * @property int $created_by
 * @property int|null $convert_to_order_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Client $client
 * @property-read User $creator
 * @property-read SalesOrder|null $salesOrder
 * @property-read Collection|QuotationItem[] $items
 */
#[Fillable([
    'client_id',
    'client_business_name',
    'client_nit',
    'client_contact_name',
    'client_phone',
    'quotation_number',
    'technology',
    'line',
    'thickness_mils',
    'application_method',
    'quotation_date',
    'validity_days',
    'payment_method',
    'delivery_time',
    'area',
    'notes',
    'subtotal',
    'iva_percentage',
    'iva_amount',
    'total',
    'status',
    'convert_to_order_id',
    'created_by',
])]
class Quotation extends Model
{
    /** @use HasFactory<QuotationFactory> */
    use HasAuditDescription, HasFactory, LogsActivity, SoftDeletes;

    protected string $auditLabel = 'Cotización';

    protected string $auditIdentifierAttribute = 'quotation_number';

    protected bool $auditFeminine = true;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('cotizaciones')
            ->setDescriptionForEvent(fn (string $eventName) => $this->getAuditDescription($eventName))
            ->logOnly([
                'client_id',
                'quotation_number',
                'status',
                'subtotal',
                'total',
                'technology',
                'line',
                'payment_method',
                'delivery_time',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'quotation_number' => 'integer',
            'status' => QuotationStatus::class,
            'payment_method' => PaymentMethod::class,
            'validity_days' => QuotationValidity::class,
            'quotation_date' => 'date:Y-m-d',
            'subtotal' => 'decimal:4',
            'iva_percentage' => 'decimal:2',
            'iva_amount' => 'decimal:4',
            'total' => 'decimal:4',
        ];
    }

    public function scopeByStatus(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }

    /**
     * Scope to restrict visibility based on user role.
     * Admin sees all; others see only their own records.
     * Null user is treated as admin (no restriction).
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user === null || $user->hasRole('admin')) {
            return $query;
        }

        return $query->where('created_by', $user->id);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'convert_to_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order');
    }
}
