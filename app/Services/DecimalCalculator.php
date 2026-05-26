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
    private const DEFAULT_SCALE = 4;

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
        return bcadd((string) $a, (string) $b, $scale);
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
        return bcsub((string) $a, (string) $b, $scale);
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
        return bcmul((string) $a, (string) $b, $scale);
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
        $divisor = (string) $b;
        if ($this->isZero($divisor)) {
            throw new RuntimeException('Division by zero');
        }

        return bcdiv((string) $a, $divisor, $scale);
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
        return bccomp((string) $a, (string) $b, $scale);
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
        $val = (string) $value;

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

        return $cmp < 0 ? (string) $a : (string) $b;
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

        return $cmp > 0 ? (string) $a : (string) $b;
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
        $val = (string) $value;

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
            $qty = (string) $item['quantity'];
            $price = (string) $item['price'];
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
