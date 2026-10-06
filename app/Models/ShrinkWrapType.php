<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasAuditDescription;
use App\Models\Concerns\SelectableWhenActive;
use Database\Factories\ShrinkWrapTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Tipo de termoencogido (3.8): una receta de empaque secundario (bandejas, bolsas…) que se gasta en cada aplicación.
 * Lo define el Admin en Configuración → Catálogos; el operario lo elige al registrar un termoencogido.
 *
 * @property int $id
 * @property string $name
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection|ShrinkWrapTypeItem[] $items
 * @property-read int|null $items_count
 * @property-read Collection|ShrinkWrap[] $shrinkWraps
 * @property-read int|null $shrink_wraps_count
 */
#[Fillable([
    'name',
    'is_active',
])]
class ShrinkWrapType extends Model
{
    /** @use HasFactory<ShrinkWrapTypeFactory> */
    use HasAuditDescription, HasFactory, LogsActivity, SelectableWhenActive;

    protected string $auditLabel = 'Tipo de termoencogido';

    protected string $auditIdentifierAttribute = 'name';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('tipos_termoencogido')
            ->setDescriptionForEvent(fn (string $eventName) => $this->getAuditDescription($eventName))
            ->logOnly(['name', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShrinkWrapTypeItem::class, 'shrink_wrap_type_id');
    }

    /**
     * Registros hechos con este tipo (3.8). Con alguno, el tipo ya no se elimina: se desactiva.
     */
    public function shrinkWraps(): HasMany
    {
        return $this->hasMany(ShrinkWrap::class, 'shrink_wrap_type_id');
    }
}
