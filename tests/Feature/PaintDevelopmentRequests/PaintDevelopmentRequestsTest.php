<?php

declare(strict_types=1);

use App\Enums\AlertType;
use App\Enums\PaintDevelopmentRequestStatus;
use App\Enums\SystemRole;
use App\Models\PaintDevelopmentRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->comercialUser = User::factory()->create(['email_verified_at' => now()]);
    $this->comercialUser->assignRole(SystemRole::Commercial->value);

    $this->otherComercial = User::factory()->create(['email_verified_at' => now()]);
    $this->otherComercial->assignRole(SystemRole::Commercial->value);

    $this->adminUser = User::factory()->create(['email_verified_at' => now()]);
    $this->adminUser->assignRole('admin');

    $this->produccionUser = User::factory()->create(['email_verified_at' => now()]);
    $this->produccionUser->assignRole(SystemRole::Production->value);
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

it('prevents produccion from accessing paint development requests', function () {
    $request = PaintDevelopmentRequest::factory()->create([
        'created_by' => $this->comercialUser->id,
        'status' => PaintDevelopmentRequestStatus::Submitted,
    ]);

    $this->actingAs($this->produccionUser)
        ->get(route('paint-development-requests.index'))
        ->assertForbidden();

    $this->actingAs($this->produccionUser)
        ->get(route('paint-development-requests.show', $request))
        ->assertForbidden();

    $this->actingAs($this->produccionUser)
        ->post(route('paint-development-requests.store'), paintDevPayload())
        ->assertForbidden();
});

// Auditoría 3.6, PR 5: PDR-02. Rangos físicos revisados contra productos reales (plan del PR 5, §4.2): aceptan
// cualquier recubrimiento que exista y rechazan el error de digitación que saldría tal cual en el PDF de I+D.
it('validates the physical range of the technical numbers', function (string $field, string $value, bool $valid) {
    $payload = paintDevPayload();
    data_set($payload, $field, $value);

    $response = $this->actingAs($this->comercialUser)
        ->post(route('paint-development-requests.store'), $payload);

    $valid ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors($field);
})->with([
    'servicio criogénico (−196 °C)' => ['context_payload.temp_min', '-196', true],
    'servicio bajo −200 °C' => ['context_payload.temp_min', '-201', false],
    'siliconado de alta temperatura (650 °C)' => ['context_payload.temp_max', '650', true],
    'servicio de mil millones de grados' => ['context_payload.temp_max', '1000000000', false],
    'temperatura con 3 decimales' => ['context_payload.temp_max', '25.125', false],
    'aplicación sobre superficie caliente (250 °C)' => ['application_payload.temp_aplicacion', '250', true],
    'aplicación a 300 °C' => ['application_payload.temp_aplicacion', '300', false],
    'humedad de 101 %' => ['application_payload.humedad_aplicacion', '101', false],
    'intumescente de 790 mils por mano' => ['application_payload.dft_max', '790', true],
    'espesor de 0' => ['application_payload.dft_min', '0', false],
    'adherencia de 3.000 psi' => ['performance_payload.adherencia_obj', '3000', true],
    'adherencia de 10.001 psi' => ['performance_payload.adherencia_obj', '10001', false],
    'cámara salina de 5.000 h' => ['performance_payload.camara_obj', '5000', true],
    'Delta E negativo' => ['performance_payload.delta_e', '-5', false],
    'Delta E de 0' => ['performance_payload.delta_e', '0', false],
    'Delta E de 1,5' => ['performance_payload.delta_e', '1.5', true],
    'sin VOC' => ['application_payload.voc', '0', true],
    'VOC de 1.001 g/L' => ['application_payload.voc', '1001', false],
    '21 manos' => ['application_payload.manos', '21', false],
    'pot life de 25 horas' => ['application_payload.pot_life', '1500', false],
]);

// Revisión del PR 5: un mínimo que no es un número ya tiene su error; antes valía 0 y agregaba un «mínima mayor que
// máxima» falso en el máximo.
it('reports a non numeric minimum only once', function (string $group, string $minField, string $maxField, string $max) {
    $payload = paintDevPayload();
    data_set($payload, "{$group}.{$minField}", 'abc');
    data_set($payload, "{$group}.{$maxField}", $max);

    $this->actingAs($this->comercialUser)
        ->post(route('paint-development-requests.store'), $payload)
        ->assertSessionHasErrors("{$group}.{$minField}")
        ->assertSessionDoesntHaveErrors("{$group}.{$maxField}");
})->with([
    'temperatura' => ['context_payload', 'temp_min', 'temp_max', '-5'],
    'espesor' => ['application_payload', 'dft_min', 'dft_max', '0.5'],
]);
