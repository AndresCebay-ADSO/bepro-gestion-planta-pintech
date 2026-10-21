<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TextFormat;
use App\Models\Concerns\HasAuditDescription;
use Database\Factories\FormulaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $product_id
 * @property int $version
 * @property string|null $name
 * @property bool $is_active
 * @property bool $is_price_reference
 * @property string|null $notes
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read User $createdBy
 * @property-read Collection|FormulaDetail[] $details
 * @property-read Collection|ProductionOrder[] $productionOrders
 * @property-read Collection|ProductionCost[] $productionCosts
 */
#[Fillable([
    'product_id',
    'version',
    'name',
    'is_active',
    'is_price_reference',
    'notes',
    'created_by',
])]
class Formula extends Model
{
    /** @use HasFactory<FormulaFactory> */
    use HasAuditDescription, HasFactory, LogsActivity;

    protected string $auditLabel = 'Fórmula';

    protected string $auditIdentifierAttribute = 'version';

    protected bool $auditFeminine = true;

    /** Los mismos defaults de la base: la regla de `booted()` necesita saber si una fórmula nueva queda activa. */
    protected $attributes = [
        'is_active' => true,
        'is_price_reference' => false,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('formulas')
            ->setDescriptionForEvent(fn (string $eventName) => $this->getAuditDescription($eventName))
            ->logOnly(['product_id', 'version', 'name', 'is_active', 'is_price_reference', 'notes'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * La primera fórmula activa de un producto queda de referencia de precio, venga de donde venga (pantalla, seeders,
     * carga de datos), y también la que se reactiva en un producto que se quedó sin referencia (D-FOR1). Las acciones de
     * fórmulas bloquean el producto antes de guardar, así que dos peticiones a la vez no la reclaman las dos; fuera de
     * ellas, el índice único parcial frena la segunda.
     */
    protected static function booted(): void
    {
        static::saving(function (Formula $formula): void {
            if ($formula->is_active && ! $formula->is_price_reference && ! $formula->productHasOtherPriceReference()) {
                $formula->is_price_reference = true;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'name' => TextFormat::Name->cast(),
            'is_active' => 'boolean',
            'is_price_reference' => 'boolean',
        ];
    }

    /**
     * Scope a query to only include active formulas.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * La fórmula con la que se costea el producto (una por producto).
     */
    public function scopePriceReference(Builder $query): void
    {
        $query->where('is_price_reference', true);
    }

    private function productHasOtherPriceReference(): bool
    {
        return static::query()
            ->where('product_id', $this->product_id)
            ->priceReference()
            ->when($this->exists, fn (Builder $query) => $query->whereKeyNot($this->getKey()))
            ->exists();
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
