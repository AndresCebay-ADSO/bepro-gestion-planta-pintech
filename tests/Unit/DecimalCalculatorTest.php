<?php

declare(strict_types=1);

use App\Services\DecimalCalculator;

// ─────────────────────────────────────────────────────────────────
// Setup
// ─────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->calc = new DecimalCalculator;
});

// ─────────────────────────────────────────────────────────────────
// add()
// ─────────────────────────────────────────────────────────────────

test('add() sums two positive decimals correctly', function () {
    expect($this->calc->add('1.2345', '2.3456'))->toBe('3.5801');
});

test('add() handles zero addend', function () {
    expect($this->calc->add('5.0000', '0'))->toBe('5.0000');
});

test('add() accumulates without growing beyond 4 decimal places', function () {
    // Adding two ≤4-decimal numbers never exceeds 4 decimals
    expect($this->calc->add('0.0001', '0.0001'))->toBe('0.0002');
});

// ─────────────────────────────────────────────────────────────────
// sub()
// ─────────────────────────────────────────────────────────────────

test('sub() subtracts two decimals correctly', function () {
    expect($this->calc->sub('5.0000', '1.2345'))->toBe('3.7655');
});

test('sub() returns negative result when b > a', function () {
    expect($this->calc->sub('1.0000', '2.0000'))->toBe('-1.0000');
});

// ─────────────────────────────────────────────────────────────────
// mul() — core rounding fix tests
// ─────────────────────────────────────────────────────────────────

test('mul() rounds half-up correctly (key regression test)', function () {
    // 2 × 0.33335 = 0.6667  (the 5th digit is 5, so it rounds up)
    // Before the fix this would truncate to 0.6666
    expect($this->calc->mul('2', '0.33335'))->toBe('0.6667');
});

test('mul() does not alter results that do not need rounding', function () {
    // 1.5 × 2 = 3.0000 — clean result, no rounding impact
    expect($this->calc->mul('1.5', '2'))->toBe('3.0000');
});

test('mul() handles multiplications with many significant decimals', function () {
    // 1.5 × 10.1234 = 15.1851 — truncation would also give 15.1851 here,
    // but this validates correctness without rounding ambiguity
    expect($this->calc->mul('1.5', '10.1234'))->toBe('15.1851');
});

test('mul() rounds up when 5th digit is >= 5', function () {
    // 1 × 0.000015 = 0.000015 → rounds to 0.0000 at scale 4 (rounds < 0.00005)
    // 1 × 0.00005 = 0.00005 → rounds up to 0.0001 at scale 4
    expect($this->calc->mul('1', '0.00005'))->toBe('0.0001');
});

test('mul() truncates (rounds down) when 5th digit is < 5', function () {
    expect($this->calc->mul('1', '0.00004'))->toBe('0.0000');
});

// ─────────────────────────────────────────────────────────────────
// div() — core rounding fix tests
// ─────────────────────────────────────────────────────────────────

test('div() rounds half-up correctly for 1/6 (key regression test)', function () {
    // 1 ÷ 6 = 0.16666... → should round to 0.1667, NOT truncate to 0.1666
    // This is the exact bug that the INTERNAL_EXTRA_SCALE fix addresses
    expect($this->calc->div('1', '6'))->toBe('0.1667');
});

test('div() does not alter result when 5th digit is < 5', function () {
    // 1 ÷ 3 = 0.33333... → 5th digit is 3, so result stays 0.3333
    expect($this->calc->div('1', '3'))->toBe('0.3333');
});

test('div() rounds negative divisions correctly (half-up for negative = half-down in absolute)', function () {
    // -1 ÷ 6 = -0.16666... → rounds away from zero to -0.1667
    expect($this->calc->div('-1', '6'))->toBe('-0.1667');
});

test('div() handles exact divisions without rounding error', function () {
    expect($this->calc->div('10', '4'))->toBe('2.5000');
});

test('div() throws RuntimeException on division by zero', function () {
    expect(fn () => $this->calc->div('10', '0'))
        ->toThrow(RuntimeException::class, 'Division by zero');
});

test('div() throws RuntimeException on division by string zero', function () {
    expect(fn () => $this->calc->div('10', '0.0000'))
        ->toThrow(RuntimeException::class, 'Division by zero');
});

// ─────────────────────────────────────────────────────────────────
// cmp()
// ─────────────────────────────────────────────────────────────────

test('cmp() returns 0 when values are equal', function () {
    expect($this->calc->cmp('1.2345', '1.2345'))->toBe(0);
});

test('cmp() returns 1 when a > b', function () {
    expect($this->calc->cmp('2.0000', '1.9999'))->toBe(1);
});

test('cmp() returns -1 when a < b', function () {
    expect($this->calc->cmp('1.9999', '2.0000'))->toBe(-1);
});

test('cmp() treats values differing only below scale as equal', function () {
    // At scale 4, 1.00001 and 1.00002 are both 1.0000 → equal
    expect($this->calc->cmp('1.00001', '1.00002', 4))->toBe(0);
});

// ─────────────────────────────────────────────────────────────────
// round()
// ─────────────────────────────────────────────────────────────────

test('round() applies half-up rounding for positive numbers', function () {
    expect($this->calc->round('1.23456'))->toBe('1.2346');
});

test('round() applies half-up rounding for negative numbers', function () {
    // Half-up for negatives: -1.23456 → rounds away from zero → -1.2346
    expect($this->calc->round('-1.23456'))->toBe('-1.2346');
});

test('round() does not change already-rounded values', function () {
    expect($this->calc->round('1.2345'))->toBe('1.2345');
});

// ─────────────────────────────────────────────────────────────────
// isZero() / isPositive() / isNegative()
// ─────────────────────────────────────────────────────────────────

test('isZero() returns true for zero string', function () {
    expect($this->calc->isZero('0'))->toBeTrue();
    expect($this->calc->isZero('0.0000'))->toBeTrue();
});

test('isZero() returns false for non-zero value', function () {
    expect($this->calc->isZero('0.0001'))->toBeFalse();
});

test('isPositive() returns true for positive value', function () {
    expect($this->calc->isPositive('0.0001'))->toBeTrue();
});

test('isPositive() returns false for zero and negative', function () {
    expect($this->calc->isPositive('0'))->toBeFalse();
    expect($this->calc->isPositive('-1.0000'))->toBeFalse();
});

test('isNegative() returns true for negative value', function () {
    expect($this->calc->isNegative('-0.0001'))->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────
// sum()
// ─────────────────────────────────────────────────────────────────

test('sum() returns zero for empty array', function () {
    expect($this->calc->sum([]))->toBe('0');
});

test('sum() correctly sums an array of decimals', function () {
    expect($this->calc->sum(['1.0000', '2.0000', '3.5000']))->toBe('6.5000');
});

// ─────────────────────────────────────────────────────────────────
// weightedAverage()
// ─────────────────────────────────────────────────────────────────

test('weightedAverage() calculates correctly with clean numbers', function () {
    $items = [
        ['quantity' => '10', 'price' => '5.0000'],
        ['quantity' => '10', 'price' => '3.0000'],
    ];

    // (10 × 5 + 10 × 3) / 20 = 80 / 20 = 4.0000
    expect($this->calc->weightedAverage($items))->toBe('4.0000');
});

test('weightedAverage() rounds repeating decimals correctly', function () {
    // 1 unit at price 1000/6 ≈ 166.6667
    $items = [
        ['quantity' => '1', 'price' => $this->calc->div('1000', '6')],
    ];

    expect($this->calc->weightedAverage($items))->toBe('166.6667');
});

test('weightedAverage() throws when total quantity is zero', function () {
    expect(fn () => $this->calc->weightedAverage([['quantity' => '0', 'price' => '100']]))
        ->toThrow(RuntimeException::class, 'total quantity is zero');
});

// ─────────────────────────────────────────────────────────────────
// Dataset: div() precision across multiple cases
// ─────────────────────────────────────────────────────────────────

it('correctly rounds div() for: %s', function (string $a, string $b, string $expected) {
    expect($this->calc->div($a, $b))->toBe($expected);
})->with([
    '1 ÷ 6  → 0.1667' => ['1',  '6', '0.1667'],
    '2 ÷ 3  → 0.6667' => ['2',  '3', '0.6667'],
    '1 ÷ 3  → 0.3333' => ['1',  '3', '0.3333'],
    '1 ÷ 7  → 0.1429' => ['1',  '7', '0.1429'],
    '1 ÷ 9  → 0.1111' => ['1',  '9', '0.1111'],
    '22 ÷ 7 → 3.1429' => ['22', '7', '3.1429'],
    '10 ÷ 3 → 3.3333' => ['10', '3', '3.3333'],
    '5 ÷ 6  → 0.8333' => ['5',  '6', '0.8333'],
]);
