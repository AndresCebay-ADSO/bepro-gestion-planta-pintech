<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RawMaterialType;
use App\Models\Concerns\HasAuditDescription;
use App\Models\Concerns\SelectableWhenActive;
use Database\Factories\RawMaterialCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property RawMaterialType $type
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection|RawMaterial[] $rawMaterials
 * @property-read int|null $raw_materials_count
 */
#[Fillable([
    'code',
    'name',
    'description',
    'type',
    'is_active',
])]
class RawMaterialCategory extends Model
{
    /** @use HasFactory<RawMaterialCategoryFactory> */
    use HasAuditDescription, HasFactory, LogsActivity, SelectableWhenActive;

    protected string $auditLabel = 'Categoría de materia prima';

    protected string $auditIdentifierAttribute = 'code';

    protected bool $auditFeminine = true;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('categorias_materia_prima')
            ->setDescriptionForEvent(fn (string $eventName) => $this->getAuditDescription($eventName))
            ->logOnly(['code', 'name', 'description', 'type', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'type' => RawMaterialType::class,
            'is_active' => 'boolean',
        ];
    }

    public function rawMaterials(): HasMany
    {
        return $this->hasMany(RawMaterial::class, 'category_id');
    }
}
