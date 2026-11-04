<?php

declare(strict_types=1);

use App\Models\Alert;
use App\Models\FormulaDetail;
use App\Models\ProductionCost;
use App\Models\ProductionOrderDetail;
use App\Models\ProductionOrderLineAdjustment;
use App\Models\ProductionOrderPackagingPlan;
use App\Models\QrCode;
use App\Models\QuotationItem;
use App\Models\RemnantConsumption;
use App\Models\SalesOrderItem;
use App\Models\ShrinkWrapItem;
use Spatie\Activitylog\Traits\LogsActivity;

/** Modelos que hoy no registran auditoría, con lo que guardan. Agregar uno exige justificarlo aquí. */
const AUDIT_EXEMPT_MODELS = [
    Alert::class => 'Alerta generada por el sistema',
    FormulaDetail::class => 'Línea de una fórmula',
    ProductionCost::class => 'Costo calculado de una OP',
    ProductionOrderDetail::class => 'Línea de una OP',
    ProductionOrderLineAdjustment::class => 'Ajuste de una línea de OP',
    ProductionOrderPackagingPlan::class => 'Plan de envasado de una OP',
    QrCode::class => 'Código QR de una OP',
    QuotationItem::class => 'Línea de una cotización',
    RemnantConsumption::class => 'Consumo de un saldo en una OP',
    SalesOrderItem::class => 'Línea de un pedido',
    ShrinkWrapItem::class => 'Gasto de un termoencogido',
];

test('todo modelo que no esté exento usa LogsActivity', function () {
    $models = appModelClasses();
    $errors = [];

    foreach ($models as $class) {
        if (! array_key_exists($class, AUDIT_EXEMPT_MODELS) && ! in_array(LogsActivity::class, class_uses_recursive($class), true)) {
            $errors[] = "{$class} no registra auditoría: agrégale LogsActivity o justifícalo en AUDIT_EXEMPT_MODELS.";
        }
    }

    expect($models)->not->toBeEmpty()
        ->and($errors)->toBe([], implode(PHP_EOL, $errors));
});

test('ningún modelo exento usa LogsActivity y todos existen', function () {
    $models = appModelClasses();
    $errors = [];

    foreach (array_keys(AUDIT_EXEMPT_MODELS) as $class) {
        if (! in_array($class, $models, true)) {
            $errors[] = "{$class} ya no existe en app/Models: quítalo de AUDIT_EXEMPT_MODELS.";
        } elseif (in_array(LogsActivity::class, class_uses_recursive($class), true)) {
            $errors[] = "{$class} ya registra auditoría: quítalo de AUDIT_EXEMPT_MODELS.";
        }
    }

    expect($errors)->toBe([], implode(PHP_EOL, $errors));
});
