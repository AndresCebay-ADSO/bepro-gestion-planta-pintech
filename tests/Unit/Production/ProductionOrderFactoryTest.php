<?php

declare(strict_types=1);

use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductionOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates a production order where product matches linked formula product', function (): void {
    $order = ProductionOrder::factory()->create();

    expect($order->product_id)->toBe($order->formula->product_id);
    expect($order->warehouse->isFactory())->toBeTrue();
});

it('allows overriding product and formula explicitly', function (): void {
    $product = Product::factory()->create();
    $formula = Formula::factory()->create(['product_id' => $product->id]);

    $order = ProductionOrder::factory()->create([
        'product_id' => $product->id,
        'formula_id' => $formula->id,
    ]);

    expect($order->product_id)->toBe($product->id);
    expect($order->formula_id)->toBe($formula->id);
});
