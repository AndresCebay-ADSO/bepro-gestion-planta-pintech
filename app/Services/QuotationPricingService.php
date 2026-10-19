<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NumberType;
use App\Models\ProductVariant;

/**
 * Precios de la cotización (PR 4b de la auditoría 3.6). Lo que ve el cliente es lo que se guarda: el precio unitario va
 * en pesos enteros, la línea es cantidad × precio, el IVA se calcula sobre el subtotal y se redondea al peso, y el total
 * es subtotal + IVA. Se redondea una sola vez y antes de calcular, mitad hacia arriba, así que la cotización siempre
 * cuadra con una calculadora. Es la única fuente: la vista previa del formulario se la pide al servidor.
 */
class QuotationPricingService
{
    /** La cotización trabaja en pesos enteros. */
    private const PESOS_SCALE = 0;

    /** Escala de los pasos intermedios, antes del único redondeo al peso. */
    private const EXACT_SCALE = 10;

    public function __construct(
        private readonly DecimalCalculator $calculator,
        private readonly VariantSalesPriceService $salesPriceService
    ) {}

    public function resolveListUnitPrice(ProductVariant $variant): ?string
    {
        return $this->salesPriceService->resolveForVariant($variant);
    }

    /**
     * @return array{list_unit_price: string, price_adjustment_pct: string, unit_price: string}
     *
     * Business Rule — Margin-based adjustment:
     * The price_adjustment_pct modifies the profit margin, not a simple
     * discount/surcharge. Positive values increase the margin (price goes up),
     * negative values decrease it (price goes down).
     *
     * Formula:  unit_price = list_unit_price / (1 - adjustmentPct / 100)
     *
     * Examples:
     *   adjustment = +15%  →  divisor = 0.85   →  price = list / 0.85
     *   adjustment = -15%  →  divisor = 1.15   →  price = list / 1.15
     *   adjustment =   0%  →  divisor = 1.00   →  price = list
     *
     * This is intentionally asymmetric: a +15% and -15% adjustment do NOT
     * cancel each other out, because they operate on the margin space.
     */
    public function resolveItemPricing(
        string $listUnitPrice,
        ?string $adjustmentPct = null,
        ?string $manualUnitPrice = null
    ): array {
        $givenAdjustment = $adjustmentPct !== null && $adjustmentPct !== ''
            ? $this->calculator->round($adjustmentPct, NumberType::Percentage->scale())
            : null;

        if ($manualUnitPrice !== null && $manualUnitPrice !== '') {
            // La regla del request ya exige pesos enteros; el redondeo solo normaliza «1200.0» a «1200».
            $unitPrice = $this->calculator->round($manualUnitPrice, self::PESOS_SCALE);

            // Si el ajuste digitado da exactamente este precio, se conserva tal cual (15 % y no 14,97 %): es el caso
            // del formulario, que manda el ajuste y el precio que la vista previa calculó con él.
            if ($givenAdjustment !== null
                && $this->calculator->cmp($this->priceFromAdjustment($listUnitPrice, $givenAdjustment), $unitPrice, self::PESOS_SCALE) === 0) {
                return [
                    'list_unit_price' => $listUnitPrice,
                    'price_adjustment_pct' => $givenAdjustment,
                    'unit_price' => $unitPrice,
                ];
            }

            // El precio digitado es el que vale; el ajuste solo informa, con los 2 decimales de su columna.
            $adjustmentPct = $this->calculator->round(
                $this->resolveAdjustmentFromPrices($listUnitPrice, $unitPrice),
                NumberType::Percentage->scale(),
            );

            // Clamp adjustment to valid bounds [-100, 99.99]
            if ($this->calculator->cmp($adjustmentPct, '-100', 4) < 0) {
                $adjustmentPct = '-100';
            } elseif ($this->calculator->cmp($adjustmentPct, '99.99', 4) > 0) {
                $adjustmentPct = '99.99';
            }

            return [
                'list_unit_price' => $listUnitPrice,
                'price_adjustment_pct' => $adjustmentPct,
                'unit_price' => $unitPrice,
            ];
        }

        $adjustmentPct = $givenAdjustment ?? '0';

        return [
            'list_unit_price' => $listUnitPrice,
            'price_adjustment_pct' => $adjustmentPct,
            'unit_price' => $this->priceFromAdjustment($listUnitPrice, $adjustmentPct),
        ];
    }

    /**
     * Precio unitario en pesos enteros desde el precio de lista y el ajuste en espacio de margen, con un solo redondeo.
     */
    private function priceFromAdjustment(string $listUnitPrice, string $adjustmentPct): string
    {
        $divisor = $this->calculator->sub('1', $this->calculator->div($adjustmentPct, '100', 4), 4);

        if ($this->calculator->cmp($divisor, '0', 4) <= 0) {
            return '0';
        }

        return $this->calculator->round(
            $this->calculator->div($listUnitPrice, $divisor, self::EXACT_SCALE),
            self::PESOS_SCALE,
        );
    }

    public function calculateLineSubtotal(string $quantity, string $unitPrice): string
    {
        return $this->calculator->mul($quantity, $unitPrice, 4);
    }

    /**
     * @param  array<int, array{subtotal: string}>  $items
     * @return array{subtotal: string, iva_amount: string, total: string}
     */
    public function calculateQuotationTotals(array $items, string $ivaPercentage): array
    {
        $subtotal = '0';

        foreach ($items as $item) {
            $subtotal = $this->calculator->add($subtotal, $item['subtotal'], 4);
        }

        // Sobre el subtotal del documento (una sola línea de IVA en el PDF), redondeado al peso (P4b-2).
        $ivaAmount = $this->calculator->round(
            $this->calculator->div($this->calculator->mul($subtotal, $ivaPercentage, self::EXACT_SCALE), '100', self::EXACT_SCALE),
            self::PESOS_SCALE,
        );

        $total = $this->calculator->add($subtotal, $ivaAmount, 4);

        return [
            'subtotal' => $subtotal,
            'iva_amount' => $ivaAmount,
            'total' => $total,
        ];
    }

    private function resolveAdjustmentFromPrices(string $listUnitPrice, string $unitPrice): string
    {
        if ($this->calculator->isZero($listUnitPrice, 4)) {
            return '0';
        }

        if ($this->calculator->isZero($unitPrice, 4)) {
            return '0';
        }

        // Con la escala exacta: el único redondeo, a los 2 decimales del ajuste, lo hace quien llama. Redondear antes
        // el cociente a 4 decimales corría los empates (21,875 % salía 21,87).
        $ratio = $this->calculator->div($listUnitPrice, $unitPrice, self::EXACT_SCALE);

        return $this->calculator->mul(
            $this->calculator->sub('1', $ratio, self::EXACT_SCALE),
            '100',
            self::EXACT_SCALE
        );
    }
}
