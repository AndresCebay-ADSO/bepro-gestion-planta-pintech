<?php

declare(strict_types=1);

namespace App\Actions\RawMaterials;

use App\Jobs\RecalculateRawMaterialDependentCosts;
use App\Jobs\RecalculateRawMaterialReferencePrice;
use App\Models\InventoryBatch;
use App\Models\RawMaterial;
use App\Services\AlertService;
use App\Services\DecimalCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Edita una materia prima. Lo que no es un simple guardado es el precio escrito a mano de las que no controlan inventario
 * (docs/POLITICA_COSTOS_MATERIA_PRIMA.md): guarda el anterior, avisa si varía más que el umbral y recalcula en segundo
 * plano los costos que dependen de él.
 */
class UpdateRawMaterialAction
{
    public const STOCK_BLOCKS_UNTRACKING_MESSAGE = 'No se puede quitar el control de inventario: la materia prima tiene saldo en bodega. Consúmelo o ajústalo primero.';

    public function __construct(
        private readonly DecimalCalculator $calculator,
        private readonly AlertService $alertService,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(RawMaterial $rawMaterial, array $validated): RawMaterial
    {
        return DB::transaction(function () use ($rawMaterial, $validated): RawMaterial {
            $locked = RawMaterial::query()->lockForUpdate()->findOrFail($rawMaterial->id);

            $previousPrice = $locked->current_price;
            $priceChanged = array_key_exists('current_price', $validated)
                && ! $this->calculator->sameOrBothNull($previousPrice, $validated['current_price']);
            $startsTracking = ($validated['tracks_inventory'] ?? $locked->tracks_inventory) && ! $locked->tracks_inventory;
            $stopsTracking = array_key_exists('tracks_inventory', $validated) && ! $validated['tracks_inventory'] && $locked->tracks_inventory;

            // El request ya lo revisó, pero sin bloqueo: una compra pudo entrar entre la validación y este punto.
            if ($stopsTracking && InventoryBatch::query()->where('raw_material_id', $locked->id)->where('remaining_quantity', '>', 0)->exists()) {
                throw ValidationException::withMessages([
                    'tracks_inventory' => __(self::STOCK_BLOCKS_UNTRACKING_MESSAGE),
                ]);
            }

            if ($priceChanged) {
                $validated['previous_price'] = $previousPrice;
            }

            $locked->update($validated);

            if ($priceChanged) {
                // La misma alerta que cuando el precio cambia por compras: un error de dedo no debe pasar en silencio.
                $this->alertService->evaluatePriceVariation($locked, $previousPrice, $locked->current_price);
                RecalculateRawMaterialDependentCosts::dispatch((int) $locked->id)->afterCommit();
            }

            // Sin control no hay alerta de stock bajo (la abierta se resuelve); con control, se evalúa ya y no en el
            // próximo movimiento.
            if ($startsTracking || $stopsTracking) {
                $this->alertService->evaluateLowStock((int) $locked->id);
            }

            // Desde que controla inventario, el precio sale de sus compras: si ya tiene lotes, se recalcula ya y no en la
            // próxima compra.
            if ($startsTracking) {
                RecalculateRawMaterialReferencePrice::dispatch((int) $locked->id)->afterCommit();
            }

            return $locked;
        });
    }
}
