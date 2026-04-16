<?php

namespace App\Models;

use Database\Factories\RawMaterialCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection|RawMaterial[] $rawMaterials
 */
#[Fillable([
    'code',
    'name',
    'description',
    'is_active',
])]
class RawMaterialCategory extends Model
{
    /** @use HasFactory<RawMaterialCategoryFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope a query to only include active categories.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function rawMaterials(): HasMany
    {
        return $this->hasMany(RawMaterial::class, 'category_id');
    }
}
