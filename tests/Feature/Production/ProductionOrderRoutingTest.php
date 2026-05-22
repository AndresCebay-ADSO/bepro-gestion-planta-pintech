<?php

use Illuminate\Support\Facades\Route;

test('production order resource only exposes implemented actions', function () {
    expect(Route::has('production-orders.index'))->toBeTrue()
        ->and(Route::has('production-orders.create'))->toBeTrue()
        ->and(Route::has('production-orders.store'))->toBeTrue()
        ->and(Route::has('production-orders.show'))->toBeTrue()
        ->and(Route::has('production-orders.edit'))->toBeFalse()
        ->and(Route::has('production-orders.update'))->toBeFalse()
        ->and(Route::has('production-orders.destroy'))->toBeFalse();
});

test('production cost preview route is rate limited', function () {
    $route = Route::getRoutes()->getByName('production-orders.preview-costs');

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain('throttle:production-preview-costs');
});
