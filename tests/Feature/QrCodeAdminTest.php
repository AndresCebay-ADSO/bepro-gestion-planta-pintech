<?php

declare(strict_types=1);

use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\QrCode;
use App\Models\QrDocument;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::create(['name' => 'admin']);
    Role::create(['name' => 'produccion']);
    Role::create(['name' => 'comercial']);
    Role::create(['name' => 'operador']);
});

function adminUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

function produccionUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('produccion');

    return $user;
}

function comercialUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('comercial');

    return $user;
}

function createQrFixture(array $overrides = []): QrCode
{
    $unit = UnitOfMeasure::firstOrCreate(
        ['code' => 'gal-qr'],
        ['name' => 'Galón', 'symbol' => 'gal']
    );
    $category = ProductCategory::firstOrCreate(['name' => 'Pinturas QR']);
    $product = Product::create([
        'code' => 'PT-QR-'.fake()->unique()->randomNumber(3),
        'name' => fake()->words(3, true),
        'category_id' => $category->id,
        'unit_of_measure_id' => $unit->id,
        'cif_percentage' => 20,
        'price_threshold' => 5,
    ]);
    $warehouse = Warehouse::firstOrCreate(
        ['name' => 'Planta QR'],
        ['city' => 'Cali', 'type' => 'factory']
    );
    $createdBy = $overrides['created_by'] ?? adminUser()->id;

    $formula = Formula::create([
        'product_id' => $product->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => $createdBy,
    ]);
    $order = ProductionOrder::create([
        'order_number' => 'OP-QR-'.fake()->unique()->randomNumber(3),
        'product_id' => $product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'status' => 'completed',
        'planned_date' => now(),
        'completion_date' => now(),
        'created_by' => $createdBy,
        'spillage_quantity' => 0,
    ]);

    $token = fake()->unique()->regexify('[a-zA-Z0-9]{40}');

    return QrCode::create([
        'product_id' => $product->id,
        'production_order_id' => $order->id,
        'token' => $token,
        'url' => route('qr.public.show', ['token' => $token]),
        'is_active' => $overrides['is_active'] ?? true,
        'created_by' => $overrides['created_by'] ?? $order->created_by,
    ]);
}

test('index is accessible to admin and produccion', function () {
    $admin = adminUser();
    $produccion = produccionUser();
    $comercial = comercialUser();

    $this->actingAs($admin)
        ->get(route('qr-codes.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('QrCodes/Index')
            ->has('qrCodes.data')
            ->has('filters'));

    $this->actingAs($produccion)
        ->get(route('qr-codes.index'))
        ->assertOk();

    $this->actingAs($comercial)
        ->get(route('qr-codes.index'))
        ->assertForbidden();
});

test('index returns paginated qr codes with relations', function () {
    $user = adminUser();
    $qr1 = createQrFixture(['created_by' => $user->id]);
    createQrFixture(['created_by' => $user->id]);
    createQrFixture(['created_by' => $user->id]);

    $this->actingAs($user)
        ->get(route('qr-codes.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('QrCodes/Index')
            ->has('qrCodes.data', 3)
            ->where('qrCodes.data.0.is_active', true)
            ->has('qrCodes.data.0.product')
            ->has('qrCodes.data.0.production_order')
            ->has('qrCodes.links'));
});

test('index filters by status', function () {
    $user = adminUser();
    createQrFixture(['created_by' => $user->id, 'is_active' => true]);
    createQrFixture(['created_by' => $user->id, 'is_active' => true]);
    createQrFixture(['created_by' => $user->id, 'is_active' => false]);

    $this->actingAs($user)
        ->get(route('qr-codes.index', ['status' => 'active']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('qrCodes.data', 2)
            ->where('filters.status', 'active'));

    $this->actingAs($user)
        ->get(route('qr-codes.index', ['status' => 'inactive']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('qrCodes.data', 1)
            ->where('filters.status', 'inactive'));
});

test('index filters by search text', function () {
    $user = adminUser();
    $qr1 = createQrFixture(['created_by' => $user->id]);
    $qr1->product->update(['name' => 'Pintura Especial']);
    createQrFixture(['created_by' => $user->id]);

    $this->actingAs($user)
        ->get(route('qr-codes.index', ['search' => 'Especial']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('qrCodes.data', 1)
            ->where('qrCodes.data.0.id', $qr1->id)
            ->where('filters.search', 'Especial'));
});

test('show displays qr code detail with documents', function () {
    Storage::fake('local');
    $user = adminUser();
    $qrCode = createQrFixture(['created_by' => $user->id]);

    $this->actingAs($user)
        ->get(route('qr-codes.show', $qrCode))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('QrCodes/Show')
            ->where('qrCode.id', $qrCode->id)
            ->where('qrCode.token', $qrCode->token)
            ->where('qrCode.is_active', true)
            ->has('qrCode.product')
            ->has('qrCode.production_order')
            ->has('qrCode.documents')
            ->has('can'));
});

test('show is blocked for unauthorized roles', function () {
    $comercial = comercialUser();
    $qrCode = createQrFixture(['created_by' => $comercial->id]);

    $this->actingAs($comercial)
        ->get(route('qr-codes.show', $qrCode))
        ->assertForbidden();
});

test('update toggles is_active', function () {
    $user = adminUser();
    $qrCode = createQrFixture(['created_by' => $user->id, 'is_active' => true]);

    $this->actingAs($user)
        ->patch(route('qr-codes.update', $qrCode), ['is_active' => false])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($qrCode->fresh()->is_active)->toBeFalse();

    $this->actingAs($user)
        ->patch(route('qr-codes.update', $qrCode), ['is_active' => true])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($qrCode->fresh()->is_active)->toBeTrue();
});

test('update validates is_active as boolean', function () {
    $user = adminUser();
    $qrCode = createQrFixture(['created_by' => $user->id]);

    $this->actingAs($user)
        ->patch(route('qr-codes.update', $qrCode), ['is_active' => 'invalid'])
        ->assertSessionHasErrors(['is_active']);
});

test('unauthenticated users cannot access qr admin routes', function () {
    $qrCode = createQrFixture();

    $this->get(route('qr-codes.index'))->assertRedirect(route('login'));
    $this->get(route('qr-codes.show', $qrCode))->assertRedirect(route('login'));
    $this->patch(route('qr-codes.update', $qrCode), ['is_active' => false])
        ->assertRedirect(route('login'));
});

test('admin can download qr image for inactive qr code', function () {
    $user = adminUser();
    $qrCode = createQrFixture(['created_by' => $user->id, 'is_active' => false]);

    $this->actingAs($user)
        ->get(route('qr-codes.qr-image', $qrCode))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'image/png');
});

test('admin can download old document version', function () {
    Storage::fake('local');
    $user = adminUser();
    $qrCode = createQrFixture(['created_by' => $user->id]);

    $document = QrDocument::factory()->create([
        'qr_code_id' => $qrCode->id,
        'is_current' => false,
        'version' => 1,
        'file_path' => 'quality-certificates/test/old-version.pdf',
        'uploaded_by' => $user->id,
    ]);

    Storage::disk('local')->put($document->file_path, 'old version content');

    $this->actingAs($user)
        ->get(route('qr-codes.documents.download', ['qrCode' => $qrCode, 'document' => $document]))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/pdf');
});
