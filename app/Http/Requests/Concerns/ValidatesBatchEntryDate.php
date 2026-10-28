<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Services\TimezoneService;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Validation\Validator;

/**
 * Un movimiento de inventario no puede fecharse antes de que su lote entrara: el kardex lo mostraría antes de la entrada
 * (auditoría 3.6, INV-16). Una sola regla para materia prima (las salidas) y producto terminado (entradas, salidas y
 * traslados sobre un lote que ya existe). El `entry_date` de un lote no cambia, así que no hace falta bloquearlo.
 */
trait ValidatesBatchEntryDate
{
    /** Compara `movement_date` con la entrada del lote; solo una fecha ya válida (sin su propio error). */
    protected function validateMovementNotBeforeBatchEntry(Validator $validator, CarbonInterface $batchEntryDate): void
    {
        $movementDate = $this->input('movement_date');

        if ($validator->errors()->has('movement_date') || ! is_string($movementDate)) {
            return;
        }

        $entryDate = $batchEntryDate->format('Y-m-d');

        if (strcmp($movementDate, $entryDate) < 0) {
            $validator->errors()->add('movement_date', __('La fecha de movimiento no puede ser anterior a la entrada del lote (:date).', [
                'date' => app(TimezoneService::class)->formatPlantDate($entryDate),
            ]));
        }
    }
}
