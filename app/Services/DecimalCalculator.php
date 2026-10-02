<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * DecimalCalculator - String-based arbitrary precision arithmetic service
 *
 * Provides safe decimal operations for financial/inventory calculations.
 * All inputs/outputs are strings to avoid float precision loss.
 * Uses bcmath extension (required).
 *
 * Default scales:
 * - Quantities: 4 decimal places
 * - Costs/Prices: 4 decimal places
 */
class DecimalCalculator
{
    /** Mayor exponente que normalize() expande: muy por encima de cualquier cantidad del negocio. */
    public const MAX_EXPONENT = 64;

    private const DEFAULT_SCALE = 4;

    /**
     * Extra decimal places used internally in mul() and div() before rounding.
     * Prevents truncation errors inherent to BCMath (e.g. 1/6 with scale 4
     * truncates to 0.1666 instead of the correct rounded 0.1667).
     */
    private const INTERNAL_EXTRA_SCALE = 4;

    public function __construct()
    {
        if (! extension_loaded('bcmath')) {
            throw new RuntimeException(
                'bcmath extension is required but not installed. '
                .'Install it with: apt-get install php-bcmath (Linux) or brew install php@8.3-bcmath (macOS)'
            );
        }
    }

    /**
     * Add two decimal numbers
     *
     * @param  string|int|float  $a  First operand
     * @param  string|int|float  $b  Second operand
     * @param  int  $scale  Decimal places (default: 4)
     * @return string Result as string
     */
    public function add(string|int|float $a, string|int|float $b, int $scale = self::DEFAULT_SCALE): string
    {
        return bcadd($this->normalize($a), $this->normalize($b), $scale);
    }

    /**
     * Subtract two decimal numbers
     *
     * @param  string|int|float  $a  Minuend
     * @param  string|int|float  $b  Subtrahend
     * @param  int  $scale  Decimal places (default: 4)
     * @return string Result as string
     */
    public function sub(string|int|float $a, string|int|float $b, int $scale = self::DEFAULT_SCALE): string
    {
        return bcsub($this->normalize($a), $this->normalize($b), $scale);
    }

    /**
     * Multiply two decimal numbers
     *
     * @param  string|int|float  $a  First operand
     * @param  string|int|float  $b  Second operand
     * @param  int  $scale  Decimal places (default: 4)
     * @return string Result as string
     */
    public function mul(string|int|float $a, string|int|float $b, int $scale = self::DEFAULT_SCALE): string
    {
        // Calculate with extra precision to avoid truncation, then round half-up.
        // e.g. mul('1.5', '10.1234', 4) internally computes at scale 8, then rounds to 4.
        $internalScale = $scale + self::INTERNAL_EXTRA_SCALE;

        return $this->round(bcmul($this->normalize($a), $this->normalize($b), $internalScale), $scale);
    }

    /**
     * Divide two decimal numbers
     *
     * Throws RuntimeException if divisor is zero.
     *
     * @param  string|int|float  $a  Dividend
     * @param  string|int|float  $b  Divisor
     * @param  int  $scale  Decimal places (default: 4)
     * @return string Result as string
     *
     * @throws RuntimeException if divisor is zero
     */
    public function div(string|int|float $a, string|int|float $b, int $scale = self::DEFAULT_SCALE): string
    {
        $divisor = $this->normalize($b);
        if ($this->isZero($divisor)) {
            throw new RuntimeException('Division by zero');
        }

        // Calculate with extra precision to avoid truncation, then round half-up.
        // e.g. div('1', '6', 4) internally computes at scale 8 → '0.16666666' → rounds to '0.1667'.
        $internalScale = $scale + self::INTERNAL_EXTRA_SCALE;

        return $this->round(bcdiv($this->normalize($a), $divisor, $internalScale), $scale);
    }

    /**
     * Compare two decimal numbers
     *
     * Returns:
     * -1 if a < b
     *  0 if a == b
     *  1 if a > b
     *
     * @param  string|int|float  $a  First operand
     * @param  string|int|float  $b  Second operand
     * @param  int  $scale  Decimal places for comparison (default: 4)
     * @return int Comparison result
     */
    public function cmp(string|int|float $a, string|int|float $b, int $scale = self::DEFAULT_SCALE): int
    {
        return bccomp($this->normalize($a), $this->normalize($b), $scale);
    }

    /**
     * Round a decimal number to specified scale using half-up strategy.
     *
     * Adds 0.5 at (scale+1) position before truncating — standard
     * accounting rounding. Handles negative values correctly.
     * Maintains decimal(12,4) column compatibility.
     *
     * @param  string|int|float  $value  Value to round
     * @param  int  $scale  Decimal places to round to (default: 4)
     * @return string Rounded value as string
     */
    public function round(string|int|float $value, int $scale = self::DEFAULT_SCALE): string
    {
        $val = $this->normalize($value);

        // For positives: add 0.5 in place (scale+1) before truncating.
        // We pass $scale + 1 to isNegative to correctly identify negative numbers
        // that are smaller than the standard default scale.
        if ($this->isNegative($val, $scale + 1)) {
            return bcsub($val, '0.'.str_repeat('0', $scale).'5', $scale);
        }

        // bcadd with scale truncates to desired precision (stable behavior)
        return bcadd($val, '0.'.str_repeat('0', $scale).'5', $scale);
    }

    /**
     * Get minimum of two decimal numbers
     *
     * @param  string|int|float  $a  First operand
     * @param  string|int|float  $b  Second operand
     * @param  int  $scale  Decimal places (default: 4)
     * @return string Minimum value as string
     */
    public function min(string|int|float $a, string|int|float $b, int $scale = self::DEFAULT_SCALE): string
    {
        $cmp = $this->cmp($a, $b, $scale);

        return $cmp < 0 ? $this->normalize($a) : $this->normalize($b);
    }

    /**
     * Get maximum of two decimal numbers
     *
     * @param  string|int|float  $a  First operand
     * @param  string|int|float  $b  Second operand
     * @param  int  $scale  Decimal places (default: 4)
     * @return string Maximum value as string
     */
    public function max(string|int|float $a, string|int|float $b, int $scale = self::DEFAULT_SCALE): string
    {
        $cmp = $this->cmp($a, $b, $scale);

        return $cmp > 0 ? $this->normalize($a) : $this->normalize($b);
    }

    /**
     * Check if a decimal number is zero
     *
     * @param  string|int|float  $value  Value to check
     * @param  int  $scale  Decimal places (default: 4)
     * @return bool True if value is zero, false otherwise
     */
    public function isZero(string|int|float $value, int $scale = self::DEFAULT_SCALE): bool
    {
        return $this->cmp($value, '0', $scale) === 0;
    }

    /**
     * Número como texto decimal que bcmath acepta. bcmath rechaza la notación científica, y llega por dos caminos: un
     * float convertido con `(string)` (`(string) 0.00001` da «1.0E-5») y un texto que PHP considera numérico («1e1»,
     * que la validación `numeric` deja pasar). Las dos se escriben como decimal plano, sin redondear: «0.00001», «10».
     * Todas las operaciones pasan por aquí.
     *
     * El exponente tiene tope: «1e999999999» pediría mil millones de dígitos y agotaría la memoria. Uno positivo por
     * encima de MAX_EXPONENT es un error (ninguna cantidad real se acerca: las columnas son decimal(12,4), y `max` lo
     * frena antes con un mensaje); uno negativo por debajo es un número tan cerca de cero que vale 0 en cualquier escala
     * del proyecto, y se devuelve así en vez de fallar.
     *
     * @throws \InvalidArgumentException si el exponente positivo supera MAX_EXPONENT
     */
    public function normalize(string|int|float $value): string
    {
        $repr = is_string($value) ? trim($value) : (string) $value;

        if (is_int($value) || stripos($repr, 'e') === false || ! is_numeric($repr)) {
            return $repr === '-0' ? '0' : $repr;
        }

        [$mantissa, $exponent] = explode('E', strtoupper($repr));

        if ((int) $exponent > self::MAX_EXPONENT) {
            throw new \InvalidArgumentException("Número fuera de rango: {$repr}");
        }

        if ((int) $exponent < -self::MAX_EXPONENT) {
            return '0';
        }

        $negative = str_starts_with($mantissa, '-');
        [$integer, $fraction] = array_pad(explode('.', ltrim($mantissa, '+-')), 2, '');
        $digits = $integer.$fraction;
        $point = strlen($integer) + (int) $exponent;

        $plain = match (true) {
            $point <= 0 => '0.'.str_repeat('0', -$point).$digits,
            $point >= strlen($digits) => $digits.str_repeat('0', $point - strlen($digits)),
            default => substr($digits, 0, $point).'.'.substr($digits, $point),
        };

        if (str_contains($plain, '.')) {
            $plain = rtrim(rtrim($plain, '0'), '.');
        }

        $plain = ltrim($plain, '0');
        $plain = $plain === '' || str_starts_with($plain, '.') ? '0'.$plain : $plain;

        return $negative && $plain !== '0' ? '-'.$plain : $plain;
    }

    /**
     * Compara dos valores que pueden faltar (precio o parámetro sin definir): iguales si faltan los dos o si valen lo
     * mismo ("1.5000" = "1.5"). Una cadena vacía cuenta como falta, igual que en un formulario.
     */
    public function sameOrBothNull(string|int|float|null $a, string|int|float|null $b, int $scale = self::DEFAULT_SCALE): bool
    {
        $aMissing = $a === null || $a === '';
        $bMissing = $b === null || $b === '';

        if ($aMissing || $bMissing) {
            return $aMissing && $bMissing;
        }

        return $this->cmp($a, $b, $scale) === 0;
    }

    /**
     * Check if a decimal number is negative
     *
     * @param  string|int|float  $value  Value to check
     * @param  int  $scale  Decimal places (default: 4)
     * @return bool True if value is negative, false otherwise
     */
    public function isNegative(string|int|float $value, int $scale = self::DEFAULT_SCALE): bool
    {
        return $this->cmp($value, '0', $scale) < 0;
    }

    /**
     * Check if a decimal number is positive
     *
     * @param  string|int|float  $value  Value to check
     * @param  int  $scale  Decimal places (default: 4)
     * @return bool True if value is positive, false otherwise
     */
    public function isPositive(string|int|float $value, int $scale = self::DEFAULT_SCALE): bool
    {
        return $this->cmp($value, '0', $scale) > 0;
    }

    /**
     * Get the absolute value of a decimal number
     *
     * @param  string|int|float  $value  Value to process
     * @param  int  $scale  Decimal places (default: 4)
     * @return string Absolute value as string
     */
    public function abs(string|int|float $value, int $scale = self::DEFAULT_SCALE): string
    {
        $val = $this->normalize($value);

        return bcadd(ltrim($val, '-'), '0', $scale);
    }

    /**
     * Sum an array of decimal numbers
     *
     * @param  array<string|int|float>  $numbers  Numbers to sum
     * @param  int  $scale  Decimal places (default: 4)
     * @return string Sum as string
     */
    public function sum(array $numbers, int $scale = self::DEFAULT_SCALE): string
    {
        return array_reduce(
            $numbers,
            fn ($carry, $value) => $this->add($carry ?? '0', $value, $scale),
            '0'
        );
    }

    /**
     * Calculates weighted average price
     *
     * Formula: SUM(quantity * price) / SUM(quantity)
     *
     * @param  array<array{quantity: string|int|float, price: string|int|float}>  $items  Array of items with quantity and price
     * @param  int  $scale  Decimal places (default: 4)
     * @return string Weighted average as string
     *
     * @throws RuntimeException if total quantity is zero
     */
    public function weightedAverage(array $items, int $scale = self::DEFAULT_SCALE): string
    {
        $totalValue = '0';
        $totalQuantity = '0';
        // Usar una escala interna mayor para evitar errores de truncamiento en multiplicaciones acumuladas
        $calcScale = $scale + 4;

        foreach ($items as $item) {
            $qty = $this->normalize($item['quantity']);
            $price = $this->normalize($item['price']);
            $totalValue = $this->add($totalValue, $this->mul($qty, $price, $calcScale), $calcScale);
            $totalQuantity = $this->add($totalQuantity, $qty, $calcScale);
        }

        if ($this->isZero($totalQuantity, $calcScale)) {
            throw new RuntimeException('Cannot calculate weighted average: total quantity is zero');
        }

        // El resultado final se calcula a una precisión mayor y luego se redondea a la escala solicitada
        $result = $this->div($totalValue, $totalQuantity, $calcScale);

        return $this->round($result, $scale);
    }
}
