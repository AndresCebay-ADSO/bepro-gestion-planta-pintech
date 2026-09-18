<?php

declare(strict_types=1);

use App\Actions\Shared\DeleteUnusedRecordAction;
use App\Models\Product;
use App\Models\ProductionOrder;
use Spatie\Activitylog\Models\Activity;

it('elimina un registro que nunca se usó y deja copia en la auditoría', function () {
    $product = Product::factory()->create();

    expect(app(DeleteUnusedRecordAction::class)->execute($product))->toBeTrue();

    $this->assertDatabaseMissing('products', ['id' => $product->id]);
    expect(Activity::where('subject_type', Product::class)->where('subject_id', $product->id)->where('event', 'deleted')->exists())
        ->toBeTrue();
});

it('no elimina un registro con historial y revierte todo lo hecho en la transacción', function () {
    $order = ProductionOrder::factory()->create();
    $product = $order->product;
    $touched = false;

    $deleted = app(DeleteUnusedRecordAction::class)->execute($product, function (Product $locked) use (&$touched): void {
        $locked->update(['name' => 'Cambio que debe revertirse']);
        $touched = true;
    });

    expect($deleted)->toBeFalse()
        ->and($touched)->toBeTrue()
        ->and($product->fresh()->name)->not->toBe('Cambio que debe revertirse')
        ->and(Activity::where('subject_type', Product::class)->where('subject_id', $product->id)->where('event', 'deleted')->exists())
        ->toBeFalse();
});
