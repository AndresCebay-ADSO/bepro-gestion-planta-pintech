<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\DecimalCalculator;

final class NumberFormat
{
    public const DEFAULT_EMPTY = '—';

    /**
     * Los tipos de número de DESIGN.md §7, con las mismas reglas que `formatKind` en `lib/formatters.ts`: coma decimal,
     * punto de miles y sin floats (redondea con DecimalCalculator y agrupa los miles sobre el texto).
     * - quantity: hasta 4 decimales, sin ceros finales («12,5», «0,0375»)
     * - units: entero («48»)
     * - clientPrice: $, entero («$1.250.000»)
     * - cost: $, hasta 2 decimales, sin ceros finales («$12.345,5»)
     * - unitCost: $, hasta 4 decimales, sin ceros finales («$4.250,1234»)
     * - percent: hasta 2 decimales, sin ceros finales, pegado («19%», «19,5%»)
     * - measurement: los decimales de su columna (viscosidad y Hegman son numeric(4,1): «85,0»)
     */
    public static function quantity(string|int|float|null $value, string $emptyValue = self::DEFAULT_EMPTY): string
    {
        return self::formatRaw($value, scale: 4, trimTrailingZeros: true, emptyValue: $emptyValue);
    }

    public static function units(string|int|float|null $value, string $emptyValue = self::DEFAULT_EMPTY): string
    {
        return self::formatRaw($value, scale: 0, trimTrailingZeros: false, emptyValue: $emptyValue);
    }

    public static function clientPrice(string|int|float|null $value, string $emptyValue = self::DEFAULT_EMPTY): string
    {
        return self::formatRaw($value, scale: 0, trimTrailingZeros: false, currency: '$', emptyValue: $emptyValue);
    }

    public static function cost(string|int|float|null $value, string $emptyValue = self::DEFAULT_EMPTY): string
    {
        return self::formatRaw($value, scale: 2, trimTrailingZeros: true, currency: '$', emptyValue: $emptyValue);
    }

    public static function unitCost(string|int|float|null $value, string $emptyValue = self::DEFAULT_EMPTY): string
    {
        return self::formatRaw($value, scale: 4, trimTrailingZeros: true, currency: '$', emptyValue: $emptyValue);
    }

    public static function percent(string|int|float|null $value, string $emptyValue = self::DEFAULT_EMPTY): string
    {
        return self::formatRaw($value, scale: 2, trimTrailingZeros: true, isPercent: true, emptyValue: $emptyValue);
    }

    public static function measurement(
        string|int|float|null $value,
        int $decimals,
        string $emptyValue = self::DEFAULT_EMPTY
    ): string {
        return self::formatRaw($value, scale: $decimals, trimTrailingZeros: false, emptyValue: $emptyValue);
    }

    private static function formatRaw(
        string|int|float|null $value,
        int $scale,
        bool $trimTrailingZeros,
        ?string $currency = null,
        bool $isPercent = false,
        string $emptyValue = self::DEFAULT_EMPTY
    ): string {
        if ($value === null || $value === '') {
            return $emptyValue;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if (! is_numeric($trimmed)) {
                return $emptyValue;
            }
            $value = $trimmed;
        }

        $calc = new DecimalCalculator;

        $rounded = $calc->round($value, $scale);

        $isNegative = str_starts_with($rounded, '-');
        $digits = $isNegative ? substr($rounded, 1) : $rounded;

        if (preg_match('/[1-9]/', $digits) !== 1) {
            $isNegative = false;
        }

        [$intPart, $decPart] = str_contains($digits, '.') ? explode('.', $digits, 2) : [$digits, ''];

        $formattedInt = strrev(implode('.', str_split(strrev($intPart), 3)));

        if ($trimTrailingZeros) {
            $decPart = rtrim($decPart, '0');
        }

        $body = $decPart !== '' ? $formattedInt.','.$decPart : $formattedInt;
        $result = ($isNegative ? '-' : '').($currency ?? '').$body;

        if ($isPercent) {
            $result .= '%';
        }

        return $result;
    }
}
