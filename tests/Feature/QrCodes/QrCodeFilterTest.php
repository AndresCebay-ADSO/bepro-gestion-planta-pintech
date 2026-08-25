<?php

declare(strict_types=1);

use App\Enums\WarehouseType;
use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\QrCode;
use App\Models\User;
use App\Models\Warehouse;
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

    $this->productA = Product::factory()->create(['name' => 'Pintura Roja', 'code' => 'PT-RED']);
    $this->productB = Product::factory()->create(['name' => 'Sellador', 'code' => 'PT-SEAL']);

    $formulaA = Formula::create([
        'product_id' => $this->productA->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => $this->admin->id,
    ]);

    $formulaB = Formula::create([
        'product_id' => $this->productB->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => $this->admin->id,
    ]);

    $warehouse = Warehouse::factory()->state(['type' => WarehouseType::Factory])->create();

    $this->order = ProductionOrder::create([
        'order_number' => 'PTO-2026-00100',
        'lot_number' => 999,
        'product_id' => $this->productA->id,
        'formula_id' => $formulaA->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 100,
        'status' => 'pending',
        'planned_date' => '2026-08-01',
        'created_by' => $this->admin->id,
    ]);

    $orderB = ProductionOrder::create([
        'order_number' => 'PTO-2026-00200',
        'lot_number' => 1000,
        'product_id' => $this->productB->id,
        'formula_id' => $formulaB->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 50,
        'status' => 'pending',
        'planned_date' => '2026-08-02',
        'created_by' => $this->admin->id,
    ]);

    $this->qrActive = QrCode::factory()->create([
        'product_id' => $this->productA->id,
        'production_order_id' => $this->order->id,
    ]);

    $this->qrInactive = QrCode::factory()->inactive()->create([
        'product_id' => $this->productB->id,
        'production_order_id' => $orderB->id,
    ]);
});

it('filters by token', function (): void {
    actingAs($this->admin);

    $response = get(route('qr-codes.index', ['search' => $this->qrActive->token]));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('QrCodes/Index')
            ->has('qrCodes.data', 1)
            ->where('qrCodes.data.0.id', $this->qrActive->id)
    );
});

it('filters by product name', function (): void {
    actingAs($this->admin);

    $response = get(route('qr-codes.index', ['search' => 'Pintura Roja']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('QrCodes/Index')
            ->has('qrCodes.data', 1)
            ->where('qrCodes.data.0.id', $this->qrActive->id)
    );
});

it('filters by product code', function (): void {
    actingAs($this->admin);

    $response = get(route('qr-codes.index', ['search' => 'PT-RED']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('QrCodes/Index')
            ->has('qrCodes.data', 1)
            ->where('qrCodes.data.0.id', $this->qrActive->id)
    );
});

it('filters by production order number', function (): void {
    actingAs($this->admin);

    $response = get(route('qr-codes.index', ['search' => 'PTO-2026-00100']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('QrCodes/Index')
            ->has('qrCodes.data', 1)
            ->where('qrCodes.data.0.id', $this->qrActive->id)
    );
});

it('filters by status active', function (): void {
    actingAs($this->admin);

    $response = get(route('qr-codes.index', ['status' => 'active']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('QrCodes/Index')
            ->has('qrCodes.data', 1)
            ->where('qrCodes.data.0.id', $this->qrActive->id)
    );
});

it('filters by status inactive', function (): void {
    actingAs($this->admin);

    $response = get(route('qr-codes.index', ['status' => 'inactive']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('QrCodes/Index')
            ->has('qrCodes.data', 1)
            ->where('qrCodes.data.0.id', $this->qrInactive->id)
    );
});

it('shows all qr codes when status is all or absent', function (): void {
    actingAs($this->admin);

    $response = get(route('qr-codes.index', ['status' => 'all']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('QrCodes/Index')
            ->has('qrCodes.data', 2)
    );

    // Without status param shows everything
    $response = get(route('qr-codes.index'));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('QrCodes/Index')
            ->has('qrCodes.data', 2)
    );
});

it('normalizes whitespace in search', function (): void {
    actingAs($this->admin);

    $response = get(route('qr-codes.index', ['search' => '   PT-RED  ']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('QrCodes/Index')
            ->has('qrCodes.data', 1)
            ->where('qrCodes.data.0.id', $this->qrActive->id)
    );
});

it('ignores invalid filter keys', function (): void {
    actingAs($this->admin);

    $response = get(route('qr-codes.index', ['invalid_key' => 'value']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('QrCodes/Index')
            ->has('qrCodes.data', 2)
            ->missing('filters.invalid_key')
    );
});

it('preserves query string in pagination', function (): void {
    actingAs($this->admin);

    $warehouse = Warehouse::factory()->state(['type' => WarehouseType::Factory])->create();
    $formula = Formula::create([
        'product_id' => $this->productA->id,
        'version' => 99,
        'is_active' => true,
        'created_by' => $this->admin->id,
    ]);

    for ($i = 0; $i < 15; $i++) {
        $order = ProductionOrder::create([
            'order_number' => sprintf('PTO-2026-%05d', $i + 10),
            'lot_number' => 2000 + $i,
            'product_id' => $this->productA->id,
            'formula_id' => $formula->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 10,
            'status' => 'pending',
            'planned_date' => '2026-08-01',
            'created_by' => $this->admin->id,
        ]);

        QrCode::factory()->create([
            'product_id' => $this->productA->id,
            'production_order_id' => $order->id,
        ]);
    }

    $response = get(route('qr-codes.index', ['search' => 'PT-RED']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->has(
                'qrCodes.links.1',
                fn ($link) => $link
                    ->where('active', true)
                    ->where('label', '1')
                    ->where('url', fn (?string $url) => $url === null || str_contains($url, 'search=PT-RED'))
                    ->etc()
            )
            ->has(
                'qrCodes.links.2',
                fn ($link) => $link
                    ->where('active', false)
                    ->where('label', '2')
                    ->where('url', fn (?string $url) => $url !== null && str_contains($url, 'search=PT-RED'))
                    ->etc()
            )
    );
});

it('returns 403 for unauthorized user', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    actingAs($user);

    $response = get(route('qr-codes.index'));

    $response->assertForbidden();
});

it('rejects invalid status value', function (): void {
    actingAs($this->admin);

    $response = getJson(route('qr-codes.index', ['status' => 'xyz']));

    $response->assertJsonValidationErrors(['status']);
});
