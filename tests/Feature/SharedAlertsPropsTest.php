<?php

declare(strict_types=1);

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Enums\SystemRole;
use App\Models\Alert;
use App\Models\RawMaterial;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * La campana del encabezado lee `unresolvedAlertsCount` y `recentAlerts` en cualquier pantalla, no solo en el dashboard.
 */
beforeEach(function (): void {
    $this->alert = Alert::factory()->create([
        'type' => AlertType::StockBajo,
        'raw_material_id' => RawMaterial::factory()->create(['code' => 'MP-BELL-01'])->id,
        'severity' => AlertSeverity::Alta,
        'message' => 'MP-BELL-01: stock bajo (1 / mínimo 5)',
    ]);
});

it('entrega las alertas pendientes a quien tiene alerts.view', function () {
    actingAsRole(SystemRole::Production);

    $this->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('unresolvedAlertsCount', 1)
            ->has('recentAlerts', 1, fn (Assert $alert) => $alert
                ->where('id', $this->alert->id)
                ->where('severity', AlertSeverity::Alta->value)
                ->where('message', 'MP-BELL-01: stock bajo (1 / mínimo 5)')
                ->etc()));
});

it('no entrega alertas a quien no tiene alerts.view', function () {
    actingAsRole(SystemRole::Operator);

    $this->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('unresolvedAlertsCount', 0)
            ->where('recentAlerts', []));
});
