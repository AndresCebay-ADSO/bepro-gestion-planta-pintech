<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasAuditDescription;
use Database\Factories\ShrinkWrapTypeItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Línea de la receta de un tipo de termoencogido: cuánto de una materia prima gasta cada aplicación. Se audita aparte
 * para que el historial muestre qué cambió de la receta, no solo que el tipo se editó.
 *
 * @property int $id
 * @property int $shrink_wrap_type_id
 * @property int $raw_material_id
 * @property string $quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ShrinkWrapType $shrinkWrapType
 * @property-read RawMaterial $rawMaterial
 * @property-read string $audit_identifier
 */
#[Fillable([
    'shrink_wrap_type_id',
    'raw_material_id',
    'quantity',
])]
class ShrinkWrapTypeItem extends Model
{
    /** @use HasFactory<ShrinkWrapTypeItemFactory> */
    use HasAuditDescription, HasFactory, LogsActivity;

    protected string $auditLabel = 'Línea de tipo de termoencogido';

    protected string $auditIdentifierAttribute = 'audit_identifier';

    protected bool $auditFeminine = true;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('tipos_termoencogido')
            ->setDescriptionForEvent(fn (string $eventName) => $this->getAuditDescription($eventName))
            ->logOnly(['shrink_wrap_type_id', 'raw_material_id', 'quantity'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
        ];
    }

    /**
     * Cómo se nombra la línea en la auditoría: «Galón · BOLSA-1». El id solo no dice qué cambió de la receta.
     */
    protected function auditIdentifier(): Attribute
    {
        return Attribute::get(fn (): string => ($this->shrinkWrapType?->name ?? '?').' · '.($this->rawMaterial?->code ?? '?'));
    }

    public function shrinkWrapType(): BelongsTo
    {
        return $this->belongsTo(ShrinkWrapType::class, 'shrink_wrap_type_id');
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(RawMaterial::class, 'raw_material_id');
    }
}
