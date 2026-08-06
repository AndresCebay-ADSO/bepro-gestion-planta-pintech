<?php

declare(strict_types=1);

use App\Enums\AlertType;
use App\Enums\PaintDevelopmentRequestStatus;
use App\Models\PaintDevelopmentRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->comercialUser = User::factory()->create(['email_verified_at' => now()]);
    $this->comercialUser->assignRole('comercial');

    $this->otherComercial = User::factory()->create(['email_verified_at' => now()]);
    $this->otherComercial->assignRole('comercial');

    $this->adminUser = User::factory()->create(['email_verified_at' => now()]);
    $this->adminUser->assignRole('admin');

    $this->produccionUser = User::factory()->create(['email_verified_at' => now()]);
    $this->produccionUser->assignRole('produccion');
});

function paintDevPayload(array $overrides = []): array
{
    return array_merge([
        'client_name' => 'Cliente Libre S.A.S.',
        'project_name' => 'Proyecto de prueba',
        'responsible' => 'Juan Pérez',
        'city' => 'Bogotá',
        'sample_due_date' => now()->addDays(30)->toDateString(),
        'current_product' => 'Pintura existente X',
        'context_payload' => [
            'sustrato' => 'Acero al carbono',
            'estado_superficie' => 'Nueva / sin pintar',
            'pintura_existente' => 'No',
            'preparacion' => ['Limpieza / desengrase'],
            'exposicion' => ['Interior'],
            'agua_humedad' => 'Humedad ambiental',
            'ciclos_termicos' => 'No',
        ],
        'performance_payload' => [
            'funcion' => ['Anticorrosiva'],
            'vida_util' => '5–10 años',
            'prioridad' => 'Máxima protección',
            'color' => 'RAL 7040',
            'brillo' => 'Semimate',
            'textura' => 'Lisa',
            'cubricion' => 'Dos manos',
            'retencion' => 'Alta',
            'indispensable' => 'Adherencia > 1000 psi',
        ],
        'application_payload' => [
            'metodo' => ['Airless'],
            'geometria' => 'Estructuras grandes',
            'manos' => '2',
            'repinte' => '24 horas',
            'servicio' => '72 horas',
            'antidescuelgue' => 'Normal',
            'vehiculo' => 'Base agua',
            'tecnologia' => 'Epóxica',
            'componentes' => '2K — Dos componentes',
            'ajustador' => 'No',
            'sistema_capas' => 'Imprimante + intermedia + acabado',
        ],
        'specifications_payload' => [
            'secados' => 'Tacto 2h, repinte 24h',
            'estabilidad' => '12 meses a 10–30 °C',
            'presentacion' => ['Galón', '5 galones'],
            'consumo' => '300 galones/mes',
            'frecuencia' => 'Mensual',
            'meta_competidor' => 'Igualar desempeño',
            'costo_objetivo' => '$28.000 COP/kg',
            'cantidad_prueba' => '5 galones',
            'aprobador' => 'Carlos Ruiz',
            'forma_aprobacion' => 'Prueba industrial en campo',
            'criterios_aprobacion' => 'Aprobación por el cliente',
        ],
    ], $overrides);
}

it('allows comercial to create a paint development request', function () {
    $response = $this->actingAs($this->comercialUser)
        ->post(route('paint-development-requests.store'), paintDevPayload());

    $response->assertRedirect();
    $this->assertDatabaseHas('paint_development_requests', [
        'project_name' => 'Proyecto de prueba',
        'status' => PaintDevelopmentRequestStatus::Draft->value,
        'created_by' => $this->comercialUser->id,
    ]);
});

it('allows comercial to view own requests in index', function () {
    PaintDevelopmentRequest::factory()->create([
        'created_by' => $this->comercialUser->id,
        'status' => PaintDevelopmentRequestStatus::Draft,
    ]);

    $response = $this->actingAs($this->comercialUser)
        ->get(route('paint-development-requests.index'));

    $response->assertOk();
});

it('prevents comercial from viewing others requests', function () {
    $request = PaintDevelopmentRequest::factory()->create([
        'created_by' => $this->otherComercial->id,
        'status' => PaintDevelopmentRequestStatus::Draft,
    ]);

    $response = $this->actingAs($this->comercialUser)
        ->get(route('paint-development-requests.show', $request));

    $response->assertForbidden();
});

it('allows admin to view any request', function () {
    $request = PaintDevelopmentRequest::factory()->create([
        'created_by' => $this->comercialUser->id,
    ]);

    $response = $this->actingAs($this->adminUser)
        ->get(route('paint-development-requests.show', $request));

    $response->assertOk();
});

it('allows comercial to update own draft', function () {
    $request = PaintDevelopmentRequest::factory()->create([
        'created_by' => $this->comercialUser->id,
        'status' => PaintDevelopmentRequestStatus::Draft,
    ]);

    $response = $this->actingAs($this->comercialUser)
        ->put(route('paint-development-requests.update', $request), paintDevPayload([
            'project_name' => 'Proyecto actualizado',
        ]));

    $response->assertRedirect();
    $this->assertDatabaseHas('paint_development_requests', [
        'id' => $request->id,
        'project_name' => 'Proyecto actualizado',
    ]);
});

it('prevents comercial from updating submitted request', function () {
    $request = PaintDevelopmentRequest::factory()->submitted()->create([
        'created_by' => $this->comercialUser->id,
    ]);

    $response = $this->actingAs($this->comercialUser)
        ->put(route('paint-development-requests.update', $request), paintDevPayload());

    $response->assertForbidden();
});

it('allows admin to update status with valid transition', function () {
    $request = PaintDevelopmentRequest::factory()->submitted()->create([
        'created_by' => $this->comercialUser->id,
    ]);

    $response = $this->actingAs($this->adminUser)
        ->patch(route('paint-development-requests.update-status', $request), [
            'status' => PaintDevelopmentRequestStatus::InReview->value,
            'review_notes' => 'En revisión técnica',
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('paint_development_requests', [
        'id' => $request->id,
        'status' => PaintDevelopmentRequestStatus::InReview->value,
        'review_notes' => 'En revisión técnica',
    ]);
});

it('blocks invalid status transition', function () {
    $request = PaintDevelopmentRequest::factory()->approved()->create([
        'created_by' => $this->comercialUser->id,
    ]);

    $response = $this->actingAs($this->adminUser)
        ->patch(route('paint-development-requests.update-status', $request), [
            'status' => PaintDevelopmentRequestStatus::Draft->value,
        ]);

    $response->assertSessionHasErrors('status');
});

it('allows comercial to submit draft', function () {
    $request = PaintDevelopmentRequest::factory()->create([
        'created_by' => $this->comercialUser->id,
        'status' => PaintDevelopmentRequestStatus::Draft,
    ]);

    $response = $this->actingAs($this->comercialUser)
        ->patch(route('paint-development-requests.submit', $request));

    $response->assertRedirect();
    $this->assertDatabaseHas('paint_development_requests', [
        'id' => $request->id,
        'status' => PaintDevelopmentRequestStatus::Submitted->value,
    ]);
});

it('creates an alert when a request is submitted', function () {
    $request = PaintDevelopmentRequest::factory()->create([
        'created_by' => $this->comercialUser->id,
        'status' => PaintDevelopmentRequestStatus::Draft,
    ]);

    $this->actingAs($this->comercialUser)
        ->patch(route('paint-development-requests.submit', $request));

    $this->assertDatabaseHas('alerts', [
        'type' => AlertType::PaintDevelopmentRequest->value,
        'is_resolved' => false,
    ]);
});

it('allows admin to export pdf', function () {
    $request = PaintDevelopmentRequest::factory()->create([
        'created_by' => $this->comercialUser->id,
    ]);

    $response = $this->actingAs($this->adminUser)
        ->get(route('paint-development-requests.export-pdf', $request));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
});

it('requires client_name on store', function () {
    $payload = paintDevPayload();
    unset($payload['client_name']);

    $response = $this->actingAs($this->comercialUser)
        ->post(route('paint-development-requests.store'), $payload);

    $response->assertSessionHasErrors(['client_name']);
});

it('validates conditional fields on store', function () {
    $payload = paintDevPayload([
        'context_payload' => array_merge(
            paintDevPayload()['context_payload'],
            ['sustrato' => 'Otro', 'otro_sustrato' => '']
        ),
    ]);

    $response = $this->actingAs($this->comercialUser)
        ->post(route('paint-development-requests.store'), $payload);

    $response->assertSessionHasErrors('context_payload.otro_sustrato');
});

it('validates temperature range on store', function () {
    $payload = paintDevPayload([
        'context_payload' => array_merge(
            paintDevPayload()['context_payload'],
            ['temp_min' => '50', 'temp_max' => '30']
        ),
    ]);

    $response = $this->actingAs($this->comercialUser)
        ->post(route('paint-development-requests.store'), $payload);

    $response->assertSessionHasErrors('context_payload.temp_max');
});
