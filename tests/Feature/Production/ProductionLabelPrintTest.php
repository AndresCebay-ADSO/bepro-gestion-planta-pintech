<?php

declare(strict_types=1);

use App\Actions\Production\CancelProductionOrderAction;
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
use Barryvdh\DomPDF\PDF;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Route;
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

function labelsUrl(ProductionOrder $order, ProductionOrderPackagingPlan $plan): string
{
    return route('production-orders.packaging-plans.labels', [
        'production_order' => $order,
        'plan' => $plan,
    ]);
}

test('producción abre el PDF de la estampita de una presentación para imprimirlo', function () {
    $this->get(labelsUrl($this->order, $this->plan))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        // En línea: se abre en el visor del navegador, donde se eligen las copias.
        ->assertHeader('content-disposition', 'inline; filename=estampita-lote-1692-12345678.pdf');
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
    $certificateQr = app(ProductionOrderQrCodeService::class)->ensureForCertificate($this->order, $this->user->id);

    expect($certificateQr->token)->toBe($printedToken)
        ->and(QrCode::query()->count())->toBe(1);
});

test('el certificado no reactiva un QR que un administrador desactivó con la orden abierta', function () {
    $qrCode = QrCode::factory()->inactive()->create(['production_order_id' => $this->order->id]);

    $certificateQr = app(ProductionOrderQrCodeService::class)->ensureForCertificate($this->order, $this->user->id);

    expect($certificateQr->id)->toBe($qrCode->id)
        ->and($qrCode->refresh()->is_active)->toBeFalse();
});

test('al cancelar la orden su QR deja de abrir el lote', function () {
    $this->order->update(['status' => ProductionOrderStatus::Pending]);
    $this->get(labelsUrl($this->order, $this->plan))->assertOk();

    app(CancelProductionOrderAction::class)->execute($this->order);

    expect(QrCode::query()->sole()->is_active)->toBeFalse();
    $this->get(route('qr.public.show', QrCode::query()->sole()->token))->assertNotFound();
});

test('registra en la auditoría de la orden quién abrió la estampita para imprimir', function () {
    $this->get(labelsUrl($this->order, $this->plan))->assertOk();

    $activity = Activity::query()->where('event', 'labels_printed')->sole();

    expect($activity->log_name)->toBe('ordenes_produccion')
        ->and($activity->subject_id)->toBe($this->order->id)
        ->and($activity->causer_id)->toBe($this->user->id)
        ->and($activity->properties->all())->toMatchArray([
            'packaging_plan_id' => $this->plan->id,
            'product_variant_id' => $this->variant->id,
            'format' => LabelFormat::Dymo57x32->value,
        ]);
});

test('si el PDF falla no queda una impresión en la auditoría', function () {
    $wrapper = Mockery::mock(PDF::class);
    $wrapper->shouldReceive('getDomPDF')->andReturn(new Dompdf);
    $wrapper->shouldReceive('loadView')->andThrow(new RuntimeException('Sin memoria'));
    app()->instance('dompdf.wrapper', $wrapper);

    expect(fn () => app(PrintProductionLabelsAction::class)
        ->execute($this->order, $this->plan, LabelFormat::Dymo57x32, $this->user->id))
        ->toThrow(RuntimeException::class);

    expect(Activity::query()->where('event', 'labels_printed')->count())->toBe(0);
});

test('la impresión tiene límite de peticiones', function () {
    expect(Route::getRoutes()->getByName('production-orders.packaging-plans.labels')->gatherMiddleware())
        ->toContain('throttle:production-labels');
});

test('una orden cancelada justo antes de crear el QR no se imprime ni deja un QR activo', function () {
    // La política revisó el estado antes; la Action lo vuelve a mirar con la orden bloqueada.
    $this->order->update(['status' => ProductionOrderStatus::Cancelled]);

    expect(fn () => app(PrintProductionLabelsAction::class)
        ->execute($this->order, $this->plan, LabelFormat::Dymo57x32, $this->user->id))
        ->toThrow(DomainException::class, 'Esta orden se canceló');

    expect(QrCode::query()->count())->toBe(0);
});

test('un código de presentación con barras, comillas o eñe no rompe el nombre del archivo', function () {
    $this->variant->update(['code' => 'AÑO/2026 "B"']);

    $disposition = $this->get(labelsUrl($this->order, $this->plan))
        ->assertOk()
        ->headers->get('content-disposition');

    expect($disposition)
        ->toStartWith('inline; filename="estampita-lote-1692-ANO-2026 \\"B\\".pdf"')
        ->toContain("filename*=utf-8''estampita-lote-1692-A%C3%91O-2026%20%22B%22.pdf");
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

test('una fila del plan de otra orden responde 404', function () {
    $otherOrder = ProductionOrder::factory()->inProgress()->create(['product_id' => $this->order->product_id]);
    $otherPlan = ProductionOrderPackagingPlan::createForVariant($otherOrder->id, $this->variant->id, 10);

    $this->get(labelsUrl($this->order, $otherPlan))->assertNotFound();
});

test('con el QR desactivado no imprime ni lo reactiva, y lo explica en la pestaña que se abrió', function () {
    $qrCode = QrCode::factory()->inactive()->create(['production_order_id' => $this->order->id]);

    // Una página corta en la pestaña nueva, no una segunda copia de la orden.
    $this->get(labelsUrl($this->order, $this->plan))
        ->assertStatus(409)
        ->assertSee('El QR de este lote está desactivado')
        ->assertSee(route('production-orders.show', $this->order), false);

    expect($qrCode->refresh()->is_active)->toBeFalse()
        ->and(Activity::query()->where('event', 'labels_printed')->count())->toBe(0);
});

test('la estampita lleva el nombre con color, la presentación y el lote', function () {
    $label = app(PrintProductionLabelsAction::class)
        ->buildLabel($this->order, $this->plan, LabelFormat::Dymo57x32, QrCode::factory()->create(['production_order_id' => $this->order->id]), app(Dompdf::class)->getFontMetrics());

    expect($label)->toMatchArray([
        'name' => 'ESMALTE SINTÉTICO RAL 3020',
        'name_size' => 8,
        'presentation' => 'Galón · 12345678',
        'lot' => 1692,
    ])
        ->and($label['qr'])->toStartWith('data:image/png;base64,');
});

test('las fechas salen en hora de planta, como el certificado', function () {
    // 8 p. m. del 9 en Bogotá = 1 a. m. del 10 en UTC.
    $this->order->forceFill(['created_at' => CarbonImmutable::parse('2026-10-10 01:00:00', 'UTC')])->save();

    $label = app(PrintProductionLabelsAction::class)
        ->buildLabel($this->order->refresh(), $this->plan, LabelFormat::Dymo57x32, QrCode::factory()->create(['production_order_id' => $this->order->id]), app(Dompdf::class)->getFontMetrics());

    expect($label['manufactured_on'])->toBe('09/10/2026')
        ->and($label['verify_on'])->toBe('08/10/2027');
});

test('sin etiqueta de presentación muestra solo el código', function () {
    $this->variant->update(['presentation_label' => null]);

    $label = app(PrintProductionLabelsAction::class)
        ->buildLabel($this->order, $this->plan->refresh(), LabelFormat::Dymo57x32, QrCode::factory()->create(['production_order_id' => $this->order->id]), app(Dompdf::class)->getFontMetrics());

    expect($label['presentation'])->toBe('12345678');
});

test('la plantilla dibuja los datos de la estampita', function () {
    $label = app(PrintProductionLabelsAction::class)
        ->buildLabel($this->order, $this->plan, LabelFormat::Dymo57x32, QrCode::factory()->create(['production_order_id' => $this->order->id]), app(Dompdf::class)->getFontMetrics());

    $html = view(LabelFormat::Dymo57x32->view(), ['format' => LabelFormat::Dymo57x32, 'labels' => [$label]])->render();

    expect($html)->toContain('ESMALTE SINTÉTICO RAL 3020')
        ->toContain('Galón · 12345678')
        ->toContain('1692')
        ->toContain('Verificación')
        // Medidas de LabelFormat: 57 − 2 × 2 mm de ancho útil y 2 mm de margen.
        ->toContain('width: 53mm')
        ->toContain('padding: 2mm');
});

test('la página de la orden dice si se pueden imprimir estampitas', function (ProductionOrderStatus $status, bool $expected) {
    $this->order->update(['status' => $status]);

    $this->get(route('production-orders.show', $this->order))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can.printLabels', $expected));
})->with([
    'en curso' => [ProductionOrderStatus::InProgress, true],
    'cancelada' => [ProductionOrderStatus::Cancelled, false],
]);
