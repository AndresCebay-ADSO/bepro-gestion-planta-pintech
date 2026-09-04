<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaintDevelopmentRequestStatus;
use App\Models\Concerns\HasAuditDescription;
use Database\Factories\PaintDevelopmentRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $request_number
 * @property PaintDevelopmentRequestStatus $status
 * @property string $client_name
 * @property string $project_name
 * @property string $responsible
 * @property string $city
 * @property Carbon $sample_due_date
 * @property string|null $current_product
 * @property array<string, mixed>|null $context_payload
 * @property array<string, mixed>|null $performance_payload
 * @property array<string, mixed>|null $application_payload
 * @property array<string, mixed>|null $specifications_payload
 * @property int $schema_version
 * @property string|null $review_notes
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $creator
 * @property-read User|null $reviewer
 */
#[Fillable([
    'status',
    'client_name',
    'project_name',
    'responsible',
    'city',
    'sample_due_date',
    'current_product',
    'context_payload',
    'performance_payload',
    'application_payload',
    'specifications_payload',
    'schema_version',
    'review_notes',
    'reviewed_by',
    'reviewed_at',
    'created_by',
])]
class PaintDevelopmentRequest extends Model
{
    /** @use HasFactory<PaintDevelopmentRequestFactory> */
    use HasAuditDescription, HasFactory, LogsActivity, SoftDeletes;

    protected string $auditLabel = 'Solicitud de Desarrollo';

    protected string $auditIdentifierAttribute = 'request_number';

    protected bool $auditFeminine = true;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('desarrollo_pinturas')
            ->setDescriptionForEvent(fn (string $eventName) => $this->getAuditDescription($eventName))
            ->logOnly([
                'request_number',
                'status',
                'client_name',
                'project_name',
                'responsible',
                'city',
                'sample_due_date',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'request_number' => 'integer',
            'status' => PaintDevelopmentRequestStatus::class,
            'sample_due_date' => 'date:Y-m-d',
            'context_payload' => 'array',
            'performance_payload' => 'array',
            'application_payload' => 'array',
            'specifications_payload' => 'array',
            'schema_version' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * Scope to restrict visibility based on user role.
     * Admin/produccion see all; comercial sees only their own.
     * Null user is treated as admin (no restriction).
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user === null || $user->hasAnyRole(['admin', 'produccion'])) {
            return $query;
        }

        return $query->where('created_by', $user->id);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
