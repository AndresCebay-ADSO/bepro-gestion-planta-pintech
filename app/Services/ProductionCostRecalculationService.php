<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductionCost;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductionCostRecalculationService
{
    /** Columnas que necesita el cálculo de cada presentación. */
    private const VARIANT_COLUMNS = ['id', 'presentation_value', 'package_raw_material_id', 'label_raw_material_id', 'current_cost', 'current_price'];

    public function __construct(
        private readonly VariantPricingService $variantPricingService,
        private readonly DecimalCalculator $calculator
    ) {}

    public function recalculateForProduct(int $productId, bool $forcePriceRefresh = false, ?int $formulaId = null): ?ProductionCost
    {
        $query = Formula::query()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->with(['details.rawMaterial:id,current_price']);

        if ($formulaId !== null) {
            $query->where('id', $formulaId);
        }

        $activeFormula = $query->first();

        if ($activeFormula === null) {
            return null;
        }

        return DB::transaction(function () use ($activeFormula, $productId, $forcePriceRefresh): ProductionCost {
            $calculatedCost = '0';
            foreach ($activeFormula->details as $detail) {
                $qty = (string) $detail->quantity;
                $price = (string) ($detail->rawMaterial->current_price ?? 0);
                $itemCost = $this->calculator->mul($qty, $price, 4);
                $calculatedCost = $this->calculator->add($calculatedCost, $itemCost, 4);
            }

            $previousCost = ProductionCost::query()
                ->where('product_id', $productId)
                ->whereNull('production_order_id')
                ->latest('calculated_at')
                ->latest('id')
                ->first();

            $variationPercentage = null;
            if ($previousCost !== null && ! $this->calculator->isZero($previousCost->cost)) {
                $difference = $this->calculator->sub($calculatedCost, (string) $previousCost->cost, 10);
                $ratio = $this->calculator->div($difference, (string) $previousCost->cost, 10);
                $variationPercentage = $this->calculator->round($this->calculator->mul($ratio, '100', 10), 4);
            }

            $product = Product::query()
                ->select('id', 'current_price', 'cif_percentage', 'price_threshold')
                ->find($productId);
            $productCifPercentage = $product?->cif_percentage !== null ? (string) $product->cif_percentage : null;
            $priceThreshold = (string) ($product?->price_threshold ?? '0');

            if ($product !== null) {
                $productUpdates = ['current_cost' => $calculatedCost];

                $shouldUpdatePrice = $forcePriceRefresh
                    || $product->current_price === null
                    || $this->calculator->cmp((string) $product->current_price, '0', 4) <= 0
                    || ($variationPercentage !== null && $this->calculator->cmp(
                        $this->calculator->abs($variationPercentage, 4),
                        $priceThreshold,
                        4
                    ) >= 0);

                if ($shouldUpdatePrice && $product->cif_percentage !== null) {
                    $cifPercentage = (string) $product->cif_percentage;
                    $cifRatio = $this->calculator->div($cifPercentage, '100', 4);
                    $cifFactor = $this->calculator->add('1', $cifRatio, 4);
                    $productUpdates['current_price'] = $this->calculator->mul($calculatedCost, $cifFactor, 4);
                }

                $product->update($productUpdates);
            }

            $this->repriceVariants(
                variants: ProductVariant::query()->where('product_id', $productId)->get(self::VARIANT_COLUMNS),
                bulkCost: $calculatedCost,
                cifPercentage: $productCifPercentage,
                priceThreshold: $priceThreshold,
                forcePriceRefresh: $forcePriceRefresh,
            );

            return ProductionCost::create([
                'product_id' => $productId,
                'formula_id' => (int) $activeFormula->id,
                'production_order_id' => null,
                'cost' => $calculatedCost,
                'unit_cost' => $calculatedCost,
                'variation_percentage' => $variationPercentage,
                'calculated_at' => now(),
            ]);
        });
    }

    public function recalculateForRawMaterial(int $rawMaterialId): int
    {
        $productIds = Formula::query()
            ->where('is_active', true)
            ->whereHas('details', fn ($query) => $query->where('raw_material_id', $rawMaterialId))
            ->pluck('product_id')
            ->unique()
            ->values()
            ->all();

        $recalculated = 0;
        foreach ($productIds as $productId) {
            if ($this->recalculateForProduct((int) $productId) !== null) {
                $recalculated++;
            }
        }

        // Si es el envase o la etiqueta de alguna presentación, esas también cambian (las de los productos recién
        // recalculados ya tomaron el precio nuevo).
        $this->repriceVariantsUsingPackagingMaterial($rawMaterialId, exceptProductIds: $productIds);

        return $recalculated;
    }

    /**
     * Recalcula solo las presentaciones que usan la materia prima como envase o etiqueta, con el costo de granel que ya
     * tiene su producto. No pasa por `recalculateForProduct`: el granel no cambió, y recalcularlo dejaría en el historial
     * de costos un registro sin variación por cada producto que usa ese envase. Cubre también los productos sin fórmula
     * activa, que `recalculateForProduct` salta.
     *
     * @param  list<int>  $exceptProductIds
     */
    public function repriceVariantsUsingPackagingMaterial(int $rawMaterialId, array $exceptProductIds = []): int
    {
        $variants = ProductVariant::query()
            ->where(fn ($query) => $query
                ->where('package_raw_material_id', $rawMaterialId)
                ->orWhere('label_raw_material_id', $rawMaterialId))
            ->whereNotIn('product_id', $exceptProductIds)
            ->get([...self::VARIANT_COLUMNS, 'product_id']);

        $products = Product::query()
            ->whereIn('id', $variants->pluck('product_id')->unique()->all())
            ->get(['id', 'current_cost', 'cif_percentage', 'price_threshold'])
            ->keyBy('id');

        // Una sola consulta de precios para todas: un envase puede estar en cientos de productos.
        $unitPrices = $this->packagingUnitPrices($variants);

        foreach ($variants->groupBy('product_id') as $productId => $productVariants) {
            $product = $products->get($productId);

            // Sin costo de granel no hay con qué costear: las presentaciones quedarían en envase + etiqueta y su precio
            // casi en cero. Se dejan como estaban hasta que el producto tenga fórmula o costo.
            if ($product === null || $product->current_cost === null) {
                continue;
            }

            DB::transaction(fn () => $this->repriceVariants(
                variants: $productVariants,
                bulkCost: (string) $product->current_cost,
                cifPercentage: $product->cif_percentage !== null ? (string) $product->cif_percentage : null,
                priceThreshold: (string) ($product->price_threshold ?? '0'),
                unitPrices: $unitPrices,
            ));
        }

        return $variants->count();
    }

    /**
     * Precio de un producto sin fórmula activa y de sus presentaciones, con el costo de granel que ya tiene (al cambiar su
     * CIF o su umbral). Misma regla que con fórmula: sin costo de granel no se toca nada (el precio quedaría en 0), y sin
     * CIF no se calcula precio, ni en el producto ni en sus presentaciones (VariantPricingService).
     */
    public function repriceProductWithoutFormula(Product $product): void
    {
        if ($product->current_cost === null) {
            return;
        }

        DB::transaction(function () use ($product): void {
            if ($product->cif_percentage !== null) {
                $cifFactor = $this->calculator->add('1', $this->calculator->div((string) $product->cif_percentage, '100', 4), 4);
                $product->update(['current_price' => $this->calculator->mul((string) $product->current_cost, $cifFactor, 4)]);
            }

            $this->repriceVariantsOfProduct($product, forcePriceRefresh: true);
        });
    }

    /**
     * Recalcula todas las presentaciones de un producto con su costo de granel actual, sin tocar el granel. Un producto
     * sin costo de granel (sin fórmula nunca costeada) no se toca.
     */
    public function repriceVariantsOfProduct(Product $product, bool $forcePriceRefresh = false): void
    {
        // Igual que en repriceVariantsUsingPackagingMaterial: sin costo de granel, nada que recalcular.
        if ($product->current_cost === null) {
            return;
        }

        $this->repriceVariants(
            variants: ProductVariant::query()->where('product_id', $product->id)->get(self::VARIANT_COLUMNS),
            bulkCost: (string) $product->current_cost,
            cifPercentage: $product->cif_percentage !== null ? (string) $product->cif_percentage : null,
            priceThreshold: (string) ($product->price_threshold ?? '0'),
            forcePriceRefresh: $forcePriceRefresh,
        );
    }

    /**
     * Costo y precio de cada presentación: granel × presentación + envase + etiqueta, a su precio de referencia actual.
     *
     * @param  Collection<int, ProductVariant>  $variants
     * @param  Collection<int, string>|null  $unitPrices  Precios ya cargados (`packagingUnitPrices`); si faltan, se consultan.
     */
    private function repriceVariants(
        Collection $variants,
        string $bulkCost,
        ?string $cifPercentage,
        string $priceThreshold,
        bool $forcePriceRefresh = false,
        ?Collection $unitPrices = null,
    ): void {
        $unitPrices ??= $this->packagingUnitPrices($variants);

        $unitPrice = fn (?int $materialId): string => $materialId !== null
            ? (string) ($unitPrices->get($materialId) ?? '0')
            : '0';

        $autoUpdatePrice = (bool) config('production.auto_update_variant_price', true);

        foreach ($variants as $variant) {
            $this->variantPricingService->updateVariantCostAndPrice(
                variant: $variant,
                bulkCost: $bulkCost,
                cifPercentage: $cifPercentage,
                priceThreshold: $priceThreshold,
                packageUnitCost: $unitPrice($variant->package_raw_material_id !== null ? (int) $variant->package_raw_material_id : null),
                labelUnitCost: $unitPrice($variant->label_raw_material_id !== null ? (int) $variant->label_raw_material_id : null),
                autoUpdatePrice: $autoUpdatePrice,
                forceRefresh: $forcePriceRefresh,
            );
        }
    }

    /**
     * Precio de referencia actual del envase y la etiqueta de cada presentación, por id de materia prima.
     *
     * @param  Collection<int, ProductVariant>  $variants
     * @return Collection<int, string>
     */
    private function packagingUnitPrices(Collection $variants): Collection
    {
        $materialIds = $variants
            ->flatMap(fn (ProductVariant $variant): array => [$variant->package_raw_material_id, $variant->label_raw_material_id])
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        return RawMaterial::query()
            ->whereIn('id', $materialIds)
            ->pluck('current_price', 'id')
            ->map(fn ($price): string => (string) ($price ?? '0'));
    }
}
