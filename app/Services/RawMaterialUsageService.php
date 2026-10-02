<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductionOrderStatus;
use App\Enums\RawMaterialType;
use App\Models\FormulaDetail;
use App\Models\ProductionOrder;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cómo se usa una materia prima, en un solo sitio. Lo consultan el cambio de su control de inventario (qué OP abiertas
 * la consumirían) y el cambio de su tipo de insumo (qué usos dependen del tipo). Un uso nuevo (las bandejas y bolsas del
 * termoencogido, 3.8) se suma aquí y no en cada pantalla que lo necesite.
 */
class RawMaterialUsageService
{
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
            // El empaque secundario (3.8) se sumará aquí cuando tenga usos que dependan del tipo.
            RawMaterialType::SecondaryPackaging => [],
        };
    }

    /**
     * @return Builder<ProductionOrder>
     */
    private function openOrders(): Builder
    {
        return ProductionOrder::query()->whereIn('status', ProductionOrderStatus::open());
    }
}
