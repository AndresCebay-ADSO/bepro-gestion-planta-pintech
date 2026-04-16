<?php

namespace App\Models;

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use Database\Factories\AlertFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
