<?php

declare(strict_types=1);

namespace App\Services\Production;

use App\Enums\NumberType;
use App\Models\FormulaDetail;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Services\DecimalCalculator;
use Illuminate\Validation\ValidationException;

/**
 * La unidad en que se digita y se muestra cada línea de una OP, y su paso a la unidad de la materia prima, que es la que
 * se guarda, descuenta inventario y da el costo. Es la única regla de la pantalla, de completar, de enviar a revisión y
 * de la vista previa: si una decidiera distinto, una cantidad mostrada en kg se convertiría como si estuviera en gramos.
 */
class ProductionOrderLineUnits
{
    public function __construct(
        private readonly DecimalCalculator $calculator,
    ) {}

    /**
     * El factor de cada línea de la orden (id de la línea => factor, o null si se digita en la unidad de la materia
     * prima). Se usa la equivalencia guardada al crear la OP, no la actual del catálogo: lo que registra el operario no
     * cambia si alguien edita la unidad. Hay factor solo si la línea lo guardó y su línea de la fórmula tiene unidad.
     *
     * Un factor en 0 cuenta como que no hay: es una equivalencia menor que los 6 decimales de la columna, y con ella no
     * se puede convertir.
     *
     * @return array<int, ?string>
     */
    public function conversionFactors(ProductionOrder $order): array
    {
        $order->loadMissing(['details', 'formula.details.unitOfMeasure']);

        $formulaLines = $order->formula?->details
            ->keyBy(fn (FormulaDetail $line): string => $line->step_order.'-'.$line->raw_material_id);

        $factors = [];

        foreach ($order->details as $detail) {
            /** @var ProductionOrderDetail $detail */
            $formulaLine = $formulaLines?->get($detail->step_order.'-'.$detail->raw_material_id);
            $factor = $detail->conversion_factor;

            $factors[(int) $detail->id] = $formulaLine?->unitOfMeasure !== null
                && $factor !== null
                && ! $this->calculator->isZero($factor, NumberType::Factor->scale())
                    ? $factor
                    : null;
        }

        return $factors;
    }

    /**
     * Lo digitado, en la unidad de la materia prima y con los decimales de la columna (12,35 g → 0,0124 kg). Con un factor
     * mayor a 1 el resultado puede no caber aunque lo digitado sí.
     *
     * @throws ValidationException si el resultado no cabe en la columna
     */
    public function toMaterialUnit(string|int|float $typed, ?string $factor): string
    {
        $scale = NumberType::Quantity->scale();

        $quantity = $factor !== null
            ? $this->calculator->mul($typed, $factor, $scale)
            : $this->calculator->round($typed, $scale);

        NumberType::Quantity->ensureFits(
            $quantity,
            'ingredients',
            __('La cantidad real, convertida a la unidad de la materia prima, no cabe en el registro. Revisa lo digitado.'),
        );

        return $quantity;
    }

    /** Lo guardado (en la unidad de la materia prima), en la unidad en que la línea se digita. */
    public function toDisplayUnit(string $stored, ?string $factor): string
    {
        return $factor !== null
            ? $this->calculator->div($stored, $factor, NumberType::Quantity->scale())
            : $stored;
    }

    /**
     * Los ingredientes enviados, con `actual_quantity` en la unidad de la materia prima. Se llama una sola vez, al entrar
     * a la acción. Una línea que no pertenece a la orden queda igual: la rechaza quien ya lo hace hoy.
     *
     * @param  array<int|string, array<string, mixed>>  $ingredients
     * @return array<int|string, array<string, mixed>>
     */
    public function ingredientsInMaterialUnit(ProductionOrder $order, array $ingredients): array
    {
        $factors = $this->conversionFactors($order);

        return array_map(function (array $row) use ($factors): array {
            $lineId = (int) ($row['id'] ?? 0);

            if (! array_key_exists($lineId, $factors)) {
                return $row;
            }

            $row['actual_quantity'] = $this->toMaterialUnit($row['actual_quantity'] ?? '0', $factors[$lineId]);

            return $row;
        }, $ingredients);
    }
}
