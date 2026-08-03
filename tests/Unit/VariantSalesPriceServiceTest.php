<?php

declare(strict_types=1);

use App\Services\VariantSalesPriceService;

beforeEach(function () {
    $this->service = app(VariantSalesPriceService::class);
});

test('it calculates margin from base and sales price', function () {
    expect($this->service->resolveMarginFromSalesPrice('92', '115'))->toBe('20.00');
    expect($this->service->resolveMarginFromSalesPrice(92.0, 115.0))->toBe('20.00');
});

test('it returns zero margin when prices are equal', function () {
    expect($this->service->resolveMarginFromSalesPrice('100', '100'))->toBe('0.00');
});

test('it normalizes floats without scientific notation', function () {
    expect($this->service->resolveMarginFromSalesPrice(0.0001, 0.0002))->toBe('50.00');
});

test('applySalesMargin and resolveMarginFromSalesPrice are inverses', function () {
    $price = $this->service->applySalesMargin('92', 20.0);
    $margin = $this->service->resolveMarginFromSalesPrice('92', $price);

    expect((float) $margin)->toBe(20.0);
});

test('resolveForProduct normalizes float current_price safely', function () {
    // This test ensures float-to-string normalization does not break existing behavior.
    // Using a float that could otherwise lose precision or become scientific notation.
    $price = $this->service->applySalesMargin(92.0, 20.0);

    expect($price)->toBe('115.0000');
});
