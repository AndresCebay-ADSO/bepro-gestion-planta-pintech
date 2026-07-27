<?php

namespace App\Models;

use App\Models\Concerns\HasAuditDescription;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string|null $code
 * @property string $name
 * @property string $brand
 * @property string|null $description
 * @property int $category_id
 * @property int $unit_of_measure_id
 * @property float|null $current_cost
 * @property float|null $cif_percentage
 * @property float|null $sales_margin
 * @property float|null $current_price
 * @property float $price_threshold
 * @property float|null $quality_viscosity_lower
 * @property float|null $quality_viscosity_upper
 * @property float|null $quality_fineness_lower
 * @property float|null $quality_fineness_upper
 * @property float|null $quality_solids_lower
 * @property float|null $quality_solids_upper
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read ProductCategory $category
 * @property-read UnitOfMeasure $unitOfMeasure
 * @property-read Collection|FinishedInventory[] $finishedInventories
 * @property-read Collection|FinishedInventoryMovement[] $finishedInventoryMovements
 * @property-read Collection|Formula[] $formulas
 * @property-read Collection|ProductionOrder[] $productionOrders
 * @property-read Collection|ProductionCost[] $productionCosts
 * @property-read Collection|PriceList[] $priceLists
 * @property-read Collection|QrCode[] $qrCodes
 * @property-read Collection|ProductDocument[] $productDocuments
 * @property-read Collection|ProductVariant[] $variants
 */
#[Fillable([
    'code',
    'name',
    'brand',
    'description',
    'category_id',
    'unit_of_measure_id',
    'current_cost',
    'cif_percentage',
    'sales_margin',
    'current_price',
    'price_threshold',
    'quality_viscosity_lower',
    'quality_viscosity_upper',
    'quality_fineness_lower',
    'quality_fineness_upper',
    'quality_solids_lower',
    'quality_solids_upper',
    'is_active',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasAuditDescription, HasFactory, LogsActivity, SoftDeletes;

    protected string $auditLabel = 'Producto';

    protected string $auditIdentifierAttribute = 'name';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('productos')
            ->setDescriptionForEvent(fn (string $eventName) => $this->getAuditDescription($eventName))
            ->logOnly(['code', 'name', 'category_id', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function casts(): array
    {
        return [
            'current_cost' => 'decimal:4',
            'cif_percentage' => 'decimal:2',
            'sales_margin' => 'decimal:2',
            'current_price' => 'decimal:4',
            'price_threshold' => 'decimal:2',
            'quality_viscosity_lower' => 'decimal:1',
            'quality_viscosity_upper' => 'decimal:1',
            'quality_fineness_lower' => 'decimal:1',
            'quality_fineness_upper' => 'decimal:1',
            'quality_solids_lower' => 'decimal:1',
            'quality_solids_upper' => 'decimal:1',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope a query to only include active products.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id');
    }

    public function finishedInventories(): HasMany
    {
        return $this->hasMany(FinishedInventory::class, 'product_id');
    }

    public function finishedInventoryMovements(): HasMany
    {
        return $this->hasMany(FinishedInventoryMovement::class, 'product_id');
    }

    public function finishedProductBatches(): HasMany
    {
        return $this->hasMany(FinishedProductBatch::class, 'product_id');
    }

    public function formulas(): HasMany
    {
        return $this->hasMany(Formula::class, 'product_id');
    }

    public function activeFormula(): HasOne
    {
        return $this->hasOne(Formula::class, 'product_id')
            ->where('is_active', true);
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class, 'product_id');
    }

    public function productionCosts(): HasMany
    {
        return $this->hasMany(ProductionCost::class, 'product_id');
    }

    public function priceLists(): HasMany
    {
        return $this->hasMany(PriceList::class, 'product_id');
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(QrCode::class, 'product_id');
    }

    public function productDocuments(): HasMany
    {
        return $this->hasMany(ProductDocument::class, 'product_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id')->chaperone();
    }
}
