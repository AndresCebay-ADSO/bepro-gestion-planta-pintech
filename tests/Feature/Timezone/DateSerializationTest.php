<?php

declare(strict_types=1);

use App\Actions\Production\BuildProductionOrderShowDataAction;
use App\Actions\Quotations\BuildQuotationPdfDataAction;
use App\Enums\PaintDevelopmentRequestStatus;
use App\Models\Client;
use App\Models\PaintDevelopmentRequest;
use App\Models\ProductionOrder;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\TimezoneService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('BuildProductionOrderShowDataAction serializa fechas de negocio como Y-m-d y timestamps como ISO-8601', function () {
    $order = ProductionOrder::factory()->create([
        'planned_date' => '2026-09-10',
        'completion_date' => '2026-09-15',
        'submitted_at' => now(),
        'agitation_start_time' => now(),
    ]);

    $action = app(BuildProductionOrderShowDataAction::class);
    $data = $action->execute($order, false);

    // Fechas de negocio deben ser YYYY-MM-DD sin tiempo ni offset
    expect($data['planned_date'])->toBe('2026-09-10');
    expect($data['completion_date'])->toBe('2026-09-15');

    // Timestamps deben ser cadenas ISO-8601 con 'T' y 'Z'
    expect($data['submitted_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
    expect($data['agitation_start_time'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
});

test('SalesOrders index serializa created_at en ISO 8601 y required_date en Y-m-d', function () {
    $user = User::factory()->create();
    $user->assignRole('comercial');

    $client = Client::factory()->create();

    SalesOrder::factory()->create([
        'client_id' => $client->id,
        'created_by' => $user->id,
        'required_date' => '2026-09-20',
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('sales-orders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SalesOrders/Index')
            ->has('orders.data.0', fn (Assert $item) => $item
                ->where('required_date', '2026-09-20')
                ->where('created_at', fn ($val) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', (string) $val))
                ->etc()
            )
        );
});

test('Quotations index serializa quotation_date en Y-m-d y created_at en ISO 8601', function () {
    $user = User::factory()->create();
    $user->assignRole('comercial');

    $client = Client::factory()->create();

    Quotation::factory()->create([
        'client_id' => $client->id,
        'created_by' => $user->id,
        'quotation_date' => '2026-09-25',
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('quotations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Quotations/Index')
            ->has('quotations.data.0', fn (Assert $item) => $item
                ->where('quotation_date', '2026-09-25')
                ->where('created_at', fn ($val) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', (string) $val))
                ->etc()
            )
        );
});

test('modelos con cast date:Y-m-d serializan a YYYY-MM-DD sin tiempo al llamar toArray o jsonSerialize', function () {
    $order = new SalesOrder([
        'required_date' => '2026-09-04',
        'estimated_delivery_date' => '2026-09-12',
    ]);

    $orderArray = $order->toArray();
    expect($orderArray['required_date'])->toBe('2026-09-04');
    expect($orderArray['estimated_delivery_date'])->toBe('2026-09-12');

    $prodOrder = new ProductionOrder([
        'planned_date' => '2026-10-01',
        'completion_date' => '2026-10-05',
    ]);

    $prodArray = $prodOrder->toArray();
    expect($prodArray['planned_date'])->toBe('2026-10-01');
    expect($prodArray['completion_date'])->toBe('2026-10-05');
});

test('PaintDevelopmentRequest edit serializa sample_due_date en Y-m-d para input date HTML5 y timestamps en ISO 8601', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $paintRequest = PaintDevelopmentRequest::factory()->create([
        'sample_due_date' => '2026-09-10',
        'created_at' => now(),
        'reviewed_at' => now(),
        'status' => PaintDevelopmentRequestStatus::Draft,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('paint-development-requests.edit', $paintRequest))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('PaintDevelopmentRequests/Edit')
            ->where('request.sample_due_date', '2026-09-10')
            ->where('request.created_at', fn ($val) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', (string) $val))
            ->where('request.reviewed_at', fn ($val) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', (string) $val))
        );
});

test('plantilla de exportacion PDF de SDR renderiza fechas formateadas para humanos en hora de planta', function () {
    $timezoneService = app(TimezoneService::class);
    $createdAtUtc = Carbon::parse('2026-09-03 20:30:00', 'UTC'); // 15:30 Colombia
    $reviewedAtUtc = Carbon::parse('2026-09-04 01:00:00', 'UTC'); // 20:00 (dia anterior) Colombia

    $pdfData = [
        'request_number' => 'SDR-001',
        'status' => 'draft',
        'status_label' => 'Borrador',
        'project_name' => 'Proyecto Test',
        'client_name' => 'Cliente Test',
        'responsible' => 'Juan',
        'city' => 'Medellin',
        'sample_due_date' => '10/09/2026',
        'current_product' => null,
        'context_payload' => [],
        'performance_payload' => [],
        'application_payload' => [],
        'specifications_payload' => [],
        'review_notes' => 'Notas de revision',
        'reviewer' => ['name' => 'Revisor'],
        'created_at' => $timezoneService->formatPlantDateTime($createdAtUtc),
        'reviewed_at' => $timezoneService->formatPlantDateTime($reviewedAtUtc),
    ];

    $rendered = view('pdf.paint-development-request', [
        'request' => $pdfData,
        'generatedAt' => '04/09/2026 15:00',
    ])->render();

    expect($rendered)->toContain('10/09/2026'); // sample_due_date en d/m/Y
    expect($rendered)->toContain('03/09/2026 15:30'); // created_at en zona planta
    expect($rendered)->toContain('03/09/2026 20:00'); // reviewed_at en zona planta (01:00 UTC = 20:00 dia previo)
});

test('BuildQuotationPdfDataAction preserva la fecha de cotizacion en d/m/Y sin desfase de zona horaria', function () {
    $quotation = Quotation::factory()->make([
        'quotation_number' => 'COT-001',
        'quotation_date' => '2026-09-04',
    ]);

    $action = app(BuildQuotationPdfDataAction::class);
    $data = $action->execute($quotation);

    expect($data['quotation_date'])->toBe('04/09/2026');
});

test('plantilla de exportacion Excel de orden de produccion muestra numero de lote y fecha planificada', function () {
    $orderData = [
        'order_number' => 'OP-2026-001',
        'lot_number' => 'LOTE-9988',
        'planned_date' => '2026-09-18',
        'product' => ['name' => 'Pintura Industrial Test'],
        'quantity' => 500.0,
        'packaging_plans' => [],
        'details' => [],
        'pdf_materials' => [
            'batches' => [],
            'totals' => ['total_planned_kg' => 500.0, 'total_real_kg' => 0.0],
        ],
    ];

    $rendered = view('excel.production-order', [
        'order' => $orderData,
    ])->render();

    // Lote debe contener el lot_number
    expect($rendered)->toContain('LOTE-9988');
    // FECHA debe contener la fecha planificada formateada en d/m/Y
    expect($rendered)->toContain('18/09/2026');
    // No debe mostrar la fecha del servidor en vez del lote
    expect($rendered)->not->toContain('<td colspan="6" style="border: 1px solid #000000;">2026-09-18</td>');
});
