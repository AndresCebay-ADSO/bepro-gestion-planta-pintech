<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasAuditDescription;
use App\Models\Concerns\SelectableWhenActive;
use Database\Factories\UnitOfMeasureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
 * @property string $symbol
 * @property string|null $description
 * @property string|null $to_kg_conversion
 * @property string|null $to_liter_conversion
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $raw_materials_count
 * @property-read int|null $products_count
 * @property-read int|null $product_variants_count
 * @property-read int|null $formula_details_count
 * @property-read Collection|RawMaterial[] $rawMaterials
 * @property-read Collection|Product[] $products
 * @property-read Collection|FormulaDetail[] $formulaDetails
 * @property-read Collection|ProductVariant[] $productVariants
 */
#[Fillable([
    'code',
    'name',
    'symbol',
    'description',
    'to_kg_conversion',
    'to_liter_conversion',
    'is_active',
])]
class UnitOfMeasure extends Model
{
    /** @use HasFactory<UnitOfMeasureFactory> */
    use HasAuditDescription, HasFactory, LogsActivity, SelectableWhenActive;

    /**
     * Registros que pueden apuntar a una unidad (todas las claves foráneas son RESTRICT).
     */
    public const USAGE_RELATIONS = ['rawMaterials', 'products', 'productVariants', 'formulaDetails'];

    protected string $auditLabel = 'Unidad de medida';

    protected string $auditIdentifierAttribute = 'code';

    protected bool $auditFeminine = true;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('unidades_medida')
            ->setDescriptionForEvent(fn (string $eventName) => $this->getAuditDescription($eventName))
            // Los factores entran en la conversión de las fórmulas: su valor anterior y el nuevo quedan auditados.
            ->logOnly(['code', 'name', 'symbol', 'description', 'to_kg_conversion', 'to_liter_conversion', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'to_kg_conversion' => 'decimal:4',
            'to_liter_conversion' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Cuenta, por relación, cuántos registros usan la unidad (`raw_materials_count`, `products_count`...).
     */
    public function scopeWithUsageCounts(Builder $query): void
    {
        $query->withCount(self::USAGE_RELATIONS);
    }

    /**
     * Si la unidad entra en alguna conversión: la de una línea de fórmula o la de una materia prima. Cambiar su factor
     * altera lo que pedirán las OP que se creen después (las ya creadas guardan el suyo). La unidad de un producto o de
     * una presentación solo se muestra, así que no cuenta.
     */
    public function affectsConversions(): bool
    {
        return $this->formulaDetails()->exists() || $this->rawMaterials()->exists();
    }

    public function rawMaterials(): HasMany
    {
        return $this->hasMany(RawMaterial::class, 'unit_of_measure_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'unit_of_measure_id');
    }

    public function formulaDetails(): HasMany
    {
        return $this->hasMany(FormulaDetail::class, 'unit_of_measure_id');
    }

    public function productVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'unit_of_measure_id');
    }
}
