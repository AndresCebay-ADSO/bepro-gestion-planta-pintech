<?php

namespace App\Models;

use Database\Factories\FormulaFactory;
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
 * @property int $product_id
 * @property int $version
 * @property bool $is_active
 * @property string|null $notes
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Product $product
 * @property-read User $createdBy
 * @property-read Collection|FormulaDetail[] $details
 * @property-read Collection|ProductionOrder[] $productionOrders
 * @property-read Collection|ProductionCost[] $productionCosts
 */
#[Fillable([
    'product_id',
    'version',
    'is_active',
    'notes',
    'created_by',
])]
class Formula extends Model
{
    /** @use HasFactory<FormulaFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('formulas')
            ->setDescriptionForEvent(fn (string $eventName) => "Fórmula {$eventName}")
            ->logOnly(['product_id', 'version', 'is_active', 'notes'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope a query to only include active formulas.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(FormulaDetail::class, 'formula_id')
            ->orderBy('step_order')
            ->orderBy('id');
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class, 'formula_id');
    }

    public function productionCosts(): HasMany
    {
        return $this->hasMany(ProductionCost::class, 'formula_id');
    }
}
