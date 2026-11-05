<?php

declare(strict_types=1);

namespace App\Services\Production;

use App\Models\ProductionOrder;
use App\Services\DecimalCalculator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Lo que entrega la planta al completar o al enviar a revisión: todas las líneas de la OP y algo envasado. La petición
 * trae la lista que cargó la pantalla, y otro usuario pudo agregar o quitar una presentación, un ajuste o un saldo
 * después (OPL-02); una petición armada a mano pudo omitir un ingrediente (OPL-01). D-OPL1, D-OPL3, P6-1 y P6-2.
 */
class ProductionOrderCompletionLines
{
    public const ORDER_CHANGED_MESSAGE = 'La orden cambió mientras la tenías abierta: se agregó o quitó una presentación, un ajuste de línea o un saldo. La pantalla ya muestra la lista actual: revisa los datos y vuelve a confirmar.';

    public const MISSING_INGREDIENTS_MESSAGE = 'Registra el consumo real de todos los ingredientes de la orden (0 si alguno no se usó).';

    public const NOTHING_PACKED_MESSAGE = 'Para cerrar la orden algo se tiene que envasar: registra las unidades de al menos una presentación o un saldo sobrante.';

    public function __construct(
        private readonly DecimalCalculator $calculator,
    ) {}

    /**
     * Con la OP ya bloqueada (nadie puede agregar ni quitar líneas mientras tanto), 422 si lo enviado no es exactamente
     * lo de la orden: ingredientes y presentaciones con sus datos reales, y los IDs de los ajustes y los saldos
     * consumidos que mostraba la pantalla (quien aprueba firma el costo que vio).
     *
     * @param  array<string, mixed>  $data  datos validados
     */
    public function ensureMatches(ProductionOrder $lockedOrder, array $data): void
    {
        $lists = [
            'ingredients' => [$lockedOrder->details()->pluck('id'), collect($data['ingredients'])->pluck('id'), self::MISSING_INGREDIENTS_MESSAGE],
            'packaging' => [$lockedOrder->packagingPlans()->pluck('id'), collect($data['packaging'] ?? [])->pluck('id'), self::ORDER_CHANGED_MESSAGE],
            'line_adjustment_ids' => [$lockedOrder->lineAdjustments()->pluck('id'), collect($data['line_adjustment_ids']), self::ORDER_CHANGED_MESSAGE],
            'remnant_consumption_ids' => [$lockedOrder->remnantConsumptions()->pluck('id'), collect($data['remnant_consumption_ids']), self::ORDER_CHANGED_MESSAGE],
        ];

        $errors = [];

        foreach ($lists as $key => [$expected, $sent, $message]) {
            if (! $this->sameIds($expected, $sent)) {
                $errors[$key] = __($message);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * D-OPL1: una presentación con unidades reales, un saldo, o las dos. Sin eso, la MP se consumiría y la producción no
     * entraría a ningún inventario. Recibe datos ya validados (números sin notación científica).
     *
     * @param  list<array<string, mixed>>  $packaging
     */
    public function packedSomething(array $packaging, mixed $remnantGallons): bool
    {
        return $this->hasRemnant($remnantGallons)
            || collect($packaging)->contains(fn (array $row) => $this->calculator->isPositive((string) ($row['actual_units'] ?? '0')));
    }

    /**
     * Si una cantidad del sobrante es mayor a 0. Una sola lectura para la regla de envasado, el rendimiento real, el
     * registro del saldo y la vista previa de costos. `ProductionCostCalculatorService` repite la comparación como defensa: no sabe quién lo
     * llama.
     */
    public function hasRemnant(mixed $remnantGallons): bool
    {
        return $this->isPositiveAmount($remnantGallons);
    }

    /**
     * El rendimiento real: lo envasado más los galones del sobrante. No se digita: es la única cuenta para lo que se
     * guarda, el tope de 9,99 veces lo proyectado y el reparto del costo. Una OP de la que salen menos galones que los
     * proyectados no es un error: es lo que salió.
     *
     * @param  array<int|string, array<string, mixed>>  $packaging  las presentaciones con sus unidades reales
     */
    public function realYield(ProductionOrder $order, array $packaging, ?string $remnantGallons): string
    {
        $packaged = $this->packagedEquivalent($order, $packaging);

        return $this->hasRemnant($remnantGallons)
            ? $this->calculator->add($packaged, (string) $remnantGallons, 4)
            : $packaged;
    }

    /**
     * Lo envasado, en la unidad del producto: unidades reales × contenido de cada presentación del plan. Una fila que no
     * es del plan de la orden no suma.
     *
     * @param  array<int|string, array<string, mixed>>  $packaging
     */
    public function packagedEquivalent(ProductionOrder $order, array $packaging): string
    {
        $order->loadMissing('packagingPlans.productVariant');
        $plans = $order->packagingPlans->keyBy('id');
        $total = '0';

        foreach ($packaging as $row) {
            $plan = $plans->get((int) ($row['id'] ?? 0));
            $units = $this->calculator->normalize($row['actual_units'] ?? '0');

            if ($plan === null || $plan->productVariant === null || ! $this->calculator->isPositive($units)) {
                continue;
            }

            $presentationValue = (string) ($plan->productVariant->presentation_value ?? '1');
            $total = $this->calculator->add($total, $this->calculator->mul($units, $presentationValue, 4), 4);
        }

        return $total;
    }

    /**
     * Los galones de un sobrante pesado: la planta digita kilos y los galones definen el reparto de costos. Solo el
     * servidor los calcula, y solo aquí. `null` si no hay kilos mayores a 0 o no hay peso por galón.
     */
    public function remnantGallons(mixed $kilos, mixed $density): ?string
    {
        if (! $this->isPositiveAmount($kilos) || ! $this->isPositiveAmount($density)) {
            return null;
        }

        return $this->calculator->div((string) $kilos, (string) $density, 4);
    }

    private function isPositiveAmount(mixed $amount): bool
    {
        return $amount !== null && $amount !== ''
            && $this->calculator->isPositive((string) $amount);
    }

    /**
     * @param  Collection<int, mixed>  $expected
     * @param  Collection<int, mixed>  $sent
     */
    private function sameIds(Collection $expected, Collection $sent): bool
    {
        $normalize = fn (Collection $ids): array => $ids->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();

        return $normalize($expected) === $normalize($sent);
    }
}
