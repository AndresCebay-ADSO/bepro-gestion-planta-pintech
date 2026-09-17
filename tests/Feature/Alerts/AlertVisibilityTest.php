<?php

declare(strict_types=1);

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\Alert;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Cada tipo de alerta pertenece a un módulo: solo la ve quien tiene su permiso, además de alerts.view.
 */
beforeEach(function (): void {
    $unit = UnitOfMeasure::create(['code' => 'KG-VIS', 'name' => 'Kilogramo', 'symbol' => 'kg']);
    $rawMaterial = RawMaterial::create([
        'code' => 'MP-VIS-01',
        'unit_of_measure_id' => $unit->id,
        'minimum_stock' => 5,
        'alert_days_before_expiry' => 30,
        'is_active' => true,
    ]);

    $this->stockAlert = Alert::create([
        'type' => AlertType::StockBajo,
        'raw_material_id' => $rawMaterial->id,
        'severity' => AlertSeverity::Media,
        'message' => 'MP-VIS-01: stock bajo (1 / mínimo 5)',
    ]);
    $this->priceAlert = Alert::create([
        'type' => AlertType::VariacionPrecio,
        'raw_material_id' => $rawMaterial->id,
        'severity' => AlertSeverity::Alta,
        'message' => 'MP-VIS-01: +20% ($10.00 → $12.00)',
    ]);
    $this->paintAlert = Alert::create([
        'type' => AlertType::PaintDevelopmentRequest,
        'severity' => AlertSeverity::Media,
        'message' => 'Nueva solicitud de desarrollo DP-2026-0001 — Cliente Reservado',
    ]);
});

it('asigna a cada tipo de alerta el permiso de su módulo', function (AlertType $type, Permission $permission) {
    expect($type->requiredPermission())->toBe($permission);
})->with([
    'stock bajo' => [AlertType::StockBajo, Permission::RawMaterialsView],
    'vencimiento' => [AlertType::VencimientoProximo, Permission::RawMaterialsView],
    'variación de precio' => [AlertType::VariacionPrecio, Permission::CostsView],
    'desarrollo de pinturas' => [AlertType::PaintDevelopmentRequest, Permission::PaintDevelopmentRequestsViewAll],
]);

it('no muestra a Producción las alertas de precios ni las de desarrollo de pinturas', function () {
    actingAsRole(SystemRole::Production);

    $this->get(route('alerts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('alerts.data', 1)
            ->where('alerts.data.0.id', $this->stockAlert->id)
            ->where('stats.unresolved_count', 1)
            ->where('typeOptions', fn ($options) => collect($options)->pluck('value')->sort()->values()->all()
                === [AlertType::StockBajo->value, AlertType::VencimientoProximo->value])
            ->where('unresolvedAlertsCount', 1)
            ->has('recentAlerts', 1)
            ->where('recentAlerts.0.id', $this->stockAlert->id));

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.unresolved_alerts', 1)
            ->has('recent_alerts', 1)
            ->where('alert_breakdown.stock_bajo', 1)
            ->where('alert_breakdown.variacion_precio', 0)
            ->where('alert_breakdown.paint_development_request', 0));
});

it('muestra todas las alertas, con sus montos, a Admin', function () {
    actingAsRole(SystemRole::Admin);

    $this->get(route('alerts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('alerts.data', 3)
            ->where('stats.unresolved_count', 3)
            ->where('unresolvedAlertsCount', 3)
            ->where('alerts.data', fn ($alerts) => collect($alerts)->firstWhere('id', $this->priceAlert->id)['message']
                === 'MP-VIS-01: +20% ($10.00 → $12.00)'));
});

it('autoriza ver y resolver una alerta solo si se ve su tipo', function () {
    $production = userWithRole(SystemRole::Production);
    $admin = userWithRole(SystemRole::Admin);

    expect(Gate::forUser($production)->allows('view', $this->stockAlert))->toBeTrue()
        ->and(Gate::forUser($production)->allows('view', $this->priceAlert))->toBeFalse()
        ->and(Gate::forUser($production)->allows('view', $this->paintAlert))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('resolve', $this->priceAlert))->toBeTrue();
});

it('no devuelve alertas sin usuario', function () {
    expect(Alert::query()->visibleTo(null)->count())->toBe(0);
});

it('entrega las alertas nuevas de la sesión solo si se ve su tipo', function (SystemRole $role, array $expectedTypes) {
    actingAsRole($role);

    $payload = fn (Alert $alert): array => [
        'id' => $alert->id,
        'message' => $alert->message,
        'severity' => $alert->severity->value,
        'type' => $alert->type->value,
        'type_label' => $alert->type->label(),
    ];

    $this->withSession(['new_alerts' => [$payload($this->stockAlert), $payload($this->priceAlert), $payload($this->paintAlert)]])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('flash.new_alerts', fn ($alerts) => collect($alerts)->pluck('type')->all() === $expectedTypes));
})->with([
    'operador (sin alerts.view)' => [SystemRole::Operator, []],
    'producción' => [SystemRole::Production, [AlertType::StockBajo->value]],
    'admin' => [SystemRole::Admin, [
        AlertType::StockBajo->value,
        AlertType::VariacionPrecio->value,
        AlertType::PaintDevelopmentRequest->value,
    ]],
]);
