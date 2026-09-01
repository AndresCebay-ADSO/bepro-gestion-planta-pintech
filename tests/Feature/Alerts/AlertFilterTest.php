<?php

declare(strict_types=1);

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Models\Alert;
use App\Models\RawMaterial;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('admin');

    $this->produccion = User::factory()->create(['email_verified_at' => now()]);
    $this->produccion->assignRole('produccion');

    $this->comercial = User::factory()->create(['email_verified_at' => now()]);
    $this->comercial->assignRole('comercial');

    $this->rmA = RawMaterial::factory()->create(['code' => 'RM-ALERTA-01']);
    $this->rmB = RawMaterial::factory()->create(['code' => 'RM-ALERTA-02']);

    $this->alertUnresolvedStockHigh = Alert::create([
        'type' => AlertType::StockBajo,
        'raw_material_id' => $this->rmA->id,
        'severity' => AlertSeverity::Alta,
        'message' => 'Stock crítico en materia prima A',
        'is_resolved' => false,
    ]);

    $this->alertUnresolvedExpiryMed = Alert::create([
        'type' => AlertType::VencimientoProximo,
        'raw_material_id' => $this->rmB->id,
        'severity' => AlertSeverity::Media,
        'message' => 'Vencimiento cercano en materia prima B',
        'is_resolved' => false,
    ]);

    $this->alertResolvedPriceLow = Alert::create([
        'type' => AlertType::VariacionPrecio,
        'raw_material_id' => $this->rmA->id,
        'severity' => AlertSeverity::Baja,
        'message' => 'Variación de precio registrada',
        'is_resolved' => true,
        'resolved_by' => $this->admin->id,
        'resolved_at' => now(),
    ]);
});

it('defaults to showing active unresolved alerts when no status filter is provided', function (): void {
    actingAs($this->admin);

    $response = get(route('alerts.index'));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Alerts/Index')
            ->has('alerts.data', 2)
            ->has('statusOptions', 2)
            ->has('typeOptions', 4)
            ->has('severityOptions', 3)
            ->where('filters.status', 'active')
    );
});

it('filters alerts by status resolved', function (): void {
    actingAs($this->admin);

    $response = get(route('alerts.index', ['status' => 'resolved']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Alerts/Index')
            ->has('alerts.data', 1)
            ->where('alerts.data.0.id', $this->alertResolvedPriceLow->id)
    );
});

it('filters alerts by status all', function (): void {
    actingAs($this->admin);

    $response = get(route('alerts.index', ['status' => 'all']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Alerts/Index')
            ->has('alerts.data', 3)
    );
});

it('filters alerts by type', function (): void {
    actingAs($this->admin);

    $response = get(route('alerts.index', ['status' => 'all', 'type' => AlertType::StockBajo->value]));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Alerts/Index')
            ->has('alerts.data', 1)
            ->where('alerts.data.0.id', $this->alertUnresolvedStockHigh->id)
    );
});

it('filters alerts by severity', function (): void {
    actingAs($this->admin);

    $response = get(route('alerts.index', ['status' => 'all', 'severity' => AlertSeverity::Media->value]));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Alerts/Index')
            ->has('alerts.data', 1)
            ->where('alerts.data.0.id', $this->alertUnresolvedExpiryMed->id)
    );
});

it('combines status, type, and severity filters', function (): void {
    actingAs($this->admin);

    $response = get(route('alerts.index', [
        'status' => 'active',
        'type' => AlertType::StockBajo->value,
        'severity' => AlertSeverity::Alta->value,
    ]));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Alerts/Index')
            ->has('alerts.data', 1)
            ->where('alerts.data.0.id', $this->alertUnresolvedStockHigh->id)
    );
});

it('ignores invalid filter keys', function (): void {
    actingAs($this->admin);

    $response = get(route('alerts.index', ['unknown_param' => 'value']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Alerts/Index')
            ->has('alerts.data', 2)
            ->missing('filters.unknown_param')
    );
});

it('preserves query string in pagination links', function (): void {
    actingAs($this->admin);

    for ($i = 0; $i < 25; $i++) {
        Alert::create([
            'type' => AlertType::StockBajo,
            'raw_material_id' => $this->rmA->id,
            'severity' => AlertSeverity::Alta,
            'message' => 'Alerta de prueba '.$i,
            'is_resolved' => false,
        ]);
    }

    $response = get(route('alerts.index', ['type' => AlertType::StockBajo->value]));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Alerts/Index')
            ->has('alerts.links')
            ->where('filters.type', AlertType::StockBajo->value)
    );
});

it('allows produccion users to access alerts index', function (): void {
    actingAs($this->produccion);

    $response = get(route('alerts.index'));

    $response->assertOk();
});

it('forbids unauthorized users from accessing alerts index', function (): void {
    actingAs($this->comercial);
    get(route('alerts.index'))->assertForbidden();

    $guest = User::factory()->create(['email_verified_at' => now()]);
    actingAs($guest);
    get(route('alerts.index'))->assertForbidden();
});

it('rejects invalid status value with 422', function (): void {
    actingAs($this->admin);

    $response = getJson(route('alerts.index', ['status' => 'inventado']));

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('rejects invalid type value with 422', function (): void {
    actingAs($this->admin);

    $response = getJson(route('alerts.index', ['type' => 'invalido']));

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

it('rejects invalid severity value with 422', function (): void {
    actingAs($this->admin);

    $response = getJson(route('alerts.index', ['severity' => 'urgente']));

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['severity']);
});
