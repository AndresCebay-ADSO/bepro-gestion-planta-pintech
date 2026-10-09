<?php

declare(strict_types=1);

use App\Actions\Production\PrintProductionLabelsAction;
use App\Enums\LabelFormat;
use App\Enums\ProductionOrderStatus;
use App\Enums\SystemRole;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderPackagingPlan;
use App\Models\ProductVariant;
use App\Models\QrCode;
use App\Services\ProductionOrderQrCodeService;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->user = actingAsRole(SystemRole::Production);

    $product = Product::factory()->create(['name' => 'ESMALTE SINTÉTICO']);
    $this->order = ProductionOrder::factory()->inProgress()->create([
        'product_id' => $product->id,
        'color' => 'RAL 3020',
        'lot_number' => 1692,
    ]);
    $this->variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'code' => '12345678',
        'presentation_label' => 'Galón',
    ]);
    $this->plan = ProductionOrderPackagingPlan::createForVariant($this->order->id, $this->variant->id, 40);
});

function labelsUrl(ProductionOrder $order, ProductionOrderPackagingPlan $plan, mixed $quantity = 38): string
{
    return route('production-orders.packaging-plans.labels', [
        'production_order' => $order,
        'plan' => $plan,
        'quantity' => $quantity,
    ]);
}

test('producción imprime las estampitas de una presentación en PDF', function () {
    $this->get(labelsUrl($this->order, $this->plan))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('la primera impresión crea el QR de la orden y las siguientes lo reutilizan', function () {
    expect(QrCode::query()->count())->toBe(0);

    $this->get(labelsUrl($this->order, $this->plan))->assertOk();
    $token = QrCode::query()->sole()->token;

    $this->get(labelsUrl($this->order, $this->plan))->assertOk();

    expect(QrCode::query()->sole())
        ->token->toBe($token)
        ->production_order_id->toBe($this->order->id)
        ->is_active->toBeTrue();
});

test('el certificado reutiliza el QR que ya está impreso en el envase', function () {
    $this->get(labelsUrl($this->order, $this->plan))->assertOk();
    $printedToken = QrCode::query()->sole()->token;

    // Lo que llama el certificado de calidad al completar la orden.
    $certificateQr = app(ProductionOrderQrCodeService::class)->ensureActive($this->order, $this->user->id);

    expect($certificateQr->token)->toBe($printedToken)
        ->and(QrCode::query()->count())->toBe(1);
});

test('registra en la auditoría de la orden quién imprimió cuántas', function () {
    $this->get(labelsUrl($this->order, $this->plan, 25))->assertOk();

    $activity = Activity::query()->where('event', 'labels_printed')->sole();

    expect($activity->log_name)->toBe('ordenes_produccion')
        ->and($activity->subject_id)->toBe($this->order->id)
        ->and($activity->causer_id)->toBe($this->user->id)
        ->and($activity->properties->all())->toMatchArray([
            'packaging_plan_id' => $this->plan->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 25,
            'format' => LabelFormat::Dymo57x32->value,
        ]);
});

test('se imprime en cualquier estado menos cancelada', function (ProductionOrderStatus $status, int $expected) {
    $this->order->update(['status' => $status]);

    $this->get(labelsUrl($this->order, $this->plan))->assertStatus($expected);
})->with([
    'pendiente' => [ProductionOrderStatus::Pending, 200],
    'en curso' => [ProductionOrderStatus::InProgress, 200],
    'en revisión' => [ProductionOrderStatus::PendingReview, 200],
    'completada (reimprimir)' => [ProductionOrderStatus::Completed, 200],
    'cancelada' => [ProductionOrderStatus::Cancelled, 403],
]);

test('sin el permiso no se imprime', function () {
    actingAsRole(SystemRole::Operator);

    $this->get(labelsUrl($this->order, $this->plan))->assertForbidden();

    expect(QrCode::query()->count())->toBe(0);
});

test('la cantidad es obligatoria, entera y de 1 a 200', function (mixed $quantity) {
    $this->from(route('production-orders.show', $this->order))
        ->get(labelsUrl($this->order, $this->plan, $quantity))
        ->assertSessionHasErrors('quantity');

    expect(QrCode::query()->count())->toBe(0);
})->with([
    'vacía' => [''],
    'cero' => [0],
    'más de 200' => [201],
    'decimal' => [2.5],
]);

test('200 estampitas sí se imprimen de una vez', function () {
    $this->get(labelsUrl($this->order, $this->plan, 200))->assertOk();
});

test('una fila del plan de otra orden responde 404', function () {
    $otherOrder = ProductionOrder::factory()->inProgress()->create(['product_id' => $this->order->product_id]);
    $otherPlan = ProductionOrderPackagingPlan::createForVariant($otherOrder->id, $this->variant->id, 10);

    $this->get(labelsUrl($this->order, $otherPlan))->assertNotFound();
});

test('con el QR desactivado no imprime ni lo reactiva', function () {
    $qrCode = QrCode::factory()->inactive()->create(['production_order_id' => $this->order->id]);

    $this->from(route('production-orders.show', $this->order))
        ->get(labelsUrl($this->order, $this->plan))
        ->assertRedirect(route('production-orders.show', $this->order))
        ->assertSessionHas('error');

    expect($qrCode->refresh()->is_active)->toBeFalse()
        ->and(Activity::query()->where('event', 'labels_printed')->count())->toBe(0);
});

test('la estampita lleva el nombre con color, la presentación, el lote y una copia por estampita', function () {
    $labels = app(PrintProductionLabelsAction::class)
        ->execute($this->order, $this->plan, 3, LabelFormat::Dymo57x32, $this->user->id);

    expect($labels)->toHaveCount(3)
        ->and($labels[0])->toMatchArray([
            'name' => 'ESMALTE SINTÉTICO RAL 3020',
            'name_size' => 8,
            'presentation' => 'Galón · 12345678',
            'lot' => 1692,
        ])
        ->and($labels[0]['qr'])->toStartWith('data:image/png;base64,')
        ->and($labels[2])->toBe($labels[0]);
});

test('las fechas salen en hora de planta, como el certificado', function () {
    // 8 p. m. del 9 en Bogotá = 1 a. m. del 10 en UTC.
    $this->order->forceFill(['created_at' => CarbonImmutable::parse('2026-10-10 01:00:00', 'UTC')])->save();

    $label = app(PrintProductionLabelsAction::class)
        ->execute($this->order->refresh(), $this->plan, 1, LabelFormat::Dymo57x32, $this->user->id)[0];

    expect($label['manufactured_on'])->toBe('09/10/2026')
        ->and($label['verify_on'])->toBe('08/10/2027');
});

test('sin etiqueta de presentación muestra solo el código', function () {
    $this->variant->update(['presentation_label' => null]);

    $label = app(PrintProductionLabelsAction::class)
        ->execute($this->order, $this->plan->refresh(), 1, LabelFormat::Dymo57x32, $this->user->id)[0];

    expect($label['presentation'])->toBe('12345678');
});

test('la plantilla dibuja los datos de la estampita', function () {
    $labels = app(PrintProductionLabelsAction::class)
        ->execute($this->order, $this->plan, 1, LabelFormat::Dymo57x32, $this->user->id);

    $html = view(LabelFormat::Dymo57x32->view(), ['labels' => $labels])->render();

    expect($html)->toContain('ESMALTE SINTÉTICO RAL 3020')
        ->toContain('Galón · 12345678')
        ->toContain('1692')
        ->toContain('Verificación');
});

test('la página de la orden dice si se pueden imprimir estampitas', function (ProductionOrderStatus $status, bool $expected) {
    $this->order->update(['status' => $status]);

    $this->get(route('production-orders.show', $this->order))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can.printLabels', $expected));
})->with([
    'en curso' => [ProductionOrderStatus::InProgress, true],
    'cancelada' => [ProductionOrderStatus::Cancelled, false],
]);
