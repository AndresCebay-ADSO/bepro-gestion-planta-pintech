<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductionOrderStatus;
use App\Enums\RawMaterialType;
use App\Models\FormulaDetail;
use App\Models\ProductionOrder;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\ShrinkWrapTypeItem;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Cómo se usa una materia prima, en un solo sitio. Lo consultan el cambio de su control de inventario (qué OP abiertas
 * la consumirían), el cambio de su tipo de insumo (qué usos dependen del tipo) y el borrado (si tiene actividad). Un uso
 * nuevo se suma aquí y no en cada pantalla que lo necesite.
 */
class RawMaterialUsageService
{
    /**
     * Relaciones cuya existencia cuenta como actividad: con alguna, la materia prima tiene historial o un uso vivo y solo
     * se puede desactivar, no eliminar (docs/POLITICA_ELIMINACION.md). Las claves foráneas `RESTRICT` lo impiden de todos
     * modos; esta lista sirve para que la pantalla no ofrezca un borrado que la base va a rechazar.
     *
     * @var list<string>
     */
    private const ACTIVITY_RELATIONS = [
        'inventoryBatches',
        'inventoryMovements',
        'formulaDetails',
        'productionOrderDetails',
        // Envase y etiqueta habitual de una presentación.
        'packagedVariants',
        'labeledVariants',
        // Envase consumido y etiqueta de un plan de envasado de OP (3.7).
        'packagingPlanUses',
        'packagingPlanLabels',
        'lineAdjustments',
        // Receta de un tipo de termoencogido (3.8).
        'shrinkWrapTypeItems',
    ];

    /**
     * Añade a un listado un indicador por cada relación de actividad, para preguntar después con hasActivity() sin una
     * consulta por fila.
     *
     * @param  Builder<RawMaterial>  $query
     * @return Builder<RawMaterial>
     */
    public function withActivityFlags(Builder $query): Builder
    {
        return $query->withExists($this->activityAliases());
    }

    /**
     * Si tiene actividad (ver ACTIVITY_RELATIONS). Usa los indicadores de withActivityFlags() si ya vienen todos cargados;
     * si falta alguno, los carga: uno ausente se leería como «sin actividad» y la pantalla ofrecería un borrado que la
     * base va a rechazar.
     */
    public function hasActivity(RawMaterial $material): bool
    {
        $flags = array_map(fn (string $relation): string => $this->activityFlag($relation), self::ACTIVITY_RELATIONS);

        if (array_diff($flags, array_keys($material->getAttributes())) !== []) {
            $material->loadExists($this->activityAliases());
        }

        foreach ($flags as $flag) {
            if ((bool) $material->getAttribute($flag)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Órdenes de producción abiertas (pendientes, en curso o en revisión) que la consumirán al completarse. Cuenta el uso,
     * no el tipo de insumo: una línea de su fórmula, un ajuste de línea, el envase de una presentación de su plan de
     * envasado (que se lee de la presentación al completar) o la etiqueta del plan (la del plan, no la habitual de la
     * presentación: en la OP se puede cambiar).
     */
    public function openProductionOrdersCount(RawMaterial $material): int
    {
        return $this->openOrders()
            ->where(fn (Builder $query) => $query
                ->whereHas('details', fn (Builder $details) => $details->where('raw_material_id', $material->id))
                ->orWhereHas('lineAdjustments', fn (Builder $adjustments) => $adjustments->where('raw_material_id', $material->id))
                ->orWhereHas('packagingPlans.productVariant', fn (Builder $variants) => $variants->where('package_raw_material_id', $material->id))
                ->orWhereHas('packagingPlans', fn (Builder $plans) => $plans->where('label_raw_material_id', $material->id)))
            ->count();
    }

    /**
     * Por qué no puede pasar de un tipo de insumo a otro, o null si puede. Si ya se usa en algo que depende de su tipo
     * (el envase de una presentación, una línea de fórmula, la etiqueta de un plan abierto…), pasarla a otro tipo dejaría
     * ese uso incoherente: la presentación, la fórmula o la OP ya no se podrían guardar.
     */
    public function typeChangeBlocker(RawMaterial $material, RawMaterialType $current, RawMaterialType $new): ?string
    {
        foreach ($this->typeDependentUses($material, $current) as [$count, $message]) {
            $total = $count();

            if ($total > 0) {
                return trans_choice($message, $total, ['type' => mb_strtolower($new->label()), 'count' => $total]);
            }
        }

        return null;
    }

    /**
     * Los usos que dependen del tipo, del más directo al de las OP abiertas. Cada conteo se hace solo si hace falta.
     *
     * @return list<array{0: Closure(): int, 1: string}>
     */
    private function typeDependentUses(RawMaterial $material, RawMaterialType $type): array
    {
        $inOpenOrders = 'No se puede pasar a una categoría de tipo :type: esta materia prima está en uso en :count orden de producción abierta.|No se puede pasar a una categoría de tipo :type: esta materia prima está en uso en :count órdenes de producción abiertas.';

        return match ($type) {
            RawMaterialType::Container => [
                [
                    fn (): int => ProductVariant::query()->where('package_raw_material_id', $material->id)->count(),
                    'No se puede pasar a una categoría de tipo :type: esta materia prima es el envase de :count presentación.|No se puede pasar a una categoría de tipo :type: esta materia prima es el envase de :count presentaciones.',
                ],
            ],
            RawMaterialType::Label => [
                [
                    fn (): int => ProductVariant::query()->where('label_raw_material_id', $material->id)->count(),
                    'No se puede pasar a una categoría de tipo :type: esta materia prima es la etiqueta de :count presentación.|No se puede pasar a una categoría de tipo :type: esta materia prima es la etiqueta de :count presentaciones.',
                ],
                [
                    fn (): int => $this->openOrders()
                        ->whereHas('packagingPlans', fn (Builder $plans) => $plans->where('label_raw_material_id', $material->id))
                        ->count(),
                    $inOpenOrders,
                ],
            ],
            // Las líneas de una OP son copias de la fórmula, y una fórmula usada por una OP no se edita ni se borra:
            // contar las líneas de fórmula ya cubre las OP. Los ajustes de línea no tienen fórmula detrás.
            RawMaterialType::Chemical => [
                [
                    fn (): int => FormulaDetail::query()->where('raw_material_id', $material->id)->count(),
                    'No se puede pasar a una categoría de tipo :type: esta materia prima está en :count línea de fórmula.|No se puede pasar a una categoría de tipo :type: esta materia prima está en :count líneas de fórmula.',
                ],
                [
                    fn (): int => $this->openOrders()
                        ->whereHas('lineAdjustments', fn (Builder $adjustments) => $adjustments->where('raw_material_id', $material->id))
                        ->count(),
                    $inOpenOrders,
                ],
            ],
            // Una receta de termoencogido solo acepta empaque secundario (3.8).
            RawMaterialType::SecondaryPackaging => [
                [
                    fn (): int => ShrinkWrapTypeItem::query()->where('raw_material_id', $material->id)->count(),
                    'No se puede pasar a una categoría de tipo :type: esta materia prima está en la receta de :count tipo de termoencogido.|No se puede pasar a una categoría de tipo :type: esta materia prima está en la receta de :count tipos de termoencogido.',
                ],
            ],
        };
    }

    /**
     * @return list<string> p. ej. `inventoryBatches as activity_inventory_batches`
     */
    private function activityAliases(): array
    {
        return array_map(
            fn (string $relation): string => "{$relation} as {$this->activityFlag($relation)}",
            self::ACTIVITY_RELATIONS,
        );
    }

    private function activityFlag(string $relation): string
    {
        return 'activity_'.Str::snake($relation);
    }

    /**
     * @return Builder<ProductionOrder>
     */
    private function openOrders(): Builder
    {
        return ProductionOrder::query()->whereIn('status', ProductionOrderStatus::open());
    }
}
