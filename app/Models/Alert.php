<?php

namespace App\Models;

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Enums\Permission;
use Database\Factories\AlertFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property AlertType $type
 * @property int|null $raw_material_id
 * @property int|null $batch_id
 * @property AlertSeverity $severity
 * @property string $message
 * @property bool $is_resolved
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read RawMaterial|null $rawMaterial
 * @property-read InventoryBatch|null $batch
 * @property-read User|null $resolvedBy
 * @property-read User|null $updatedBy
 */
#[Fillable([
    'type',
    'raw_material_id',
    'batch_id',
    'severity',
    'message',
    'is_resolved',
    'resolved_by',
    'resolved_at',
    'updated_by',
])]
class Alert extends Model
{
    /** @use HasFactory<AlertFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => AlertType::class,
            'severity' => AlertSeverity::class,
            'is_resolved' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Tipos de alerta que ve el usuario: exige alerts.view y el permiso del módulo de cada tipo.
     *
     * @return array<int, AlertType>
     */
    public static function visibleTypesFor(?User $user): array
    {
        if ($user === null || ! $user->can(Permission::AlertsView->value)) {
            return [];
        }

        return array_values(array_filter(
            AlertType::cases(),
            fn (AlertType $type): bool => $user->can($type->requiredPermission()->value),
        ));
    }

    /**
     * Solo las alertas de los tipos que ve el usuario; sin usuario, ninguna.
     *
     * @param  Builder<Alert>  $query
     */
    public function scopeVisibleTo(Builder $query, ?User $user): void
    {
        $types = self::visibleTypesFor($user);

        if ($types === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('type', array_map(fn (AlertType $type): string => $type->value, $types));
    }

    /**
     * @param  Builder<Alert>  $query
     */
    public function scopeUnresolved(Builder $query): void
    {
        $query->where('is_resolved', false);
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class, 'raw_material_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'batch_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
