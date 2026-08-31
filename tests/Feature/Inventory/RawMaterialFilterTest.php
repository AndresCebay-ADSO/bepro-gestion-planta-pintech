<?php

declare(strict_types=1);

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

    $this->rmActive = RawMaterial::factory()->create(['code' => 'RM-ALFA-001']);
    $this->rmInactive = RawMaterial::factory()->inactive()->create(['code' => 'RM-BETA-002']);
});

it('filters by code', function (): void {
    actingAs($this->admin);

    $response = get(route('raw-materials.index', ['search' => 'ALFA']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Inventory/RawMaterials/Index')
            ->has('rawMaterials.data', 1)
            ->where('rawMaterials.data.0.id', $this->rmActive->id)
    );
});

it('shows all raw materials when no status filter is provided', function (): void {
    actingAs($this->admin);

    $response = get(route('raw-materials.index'));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Inventory/RawMaterials/Index')
            ->has('rawMaterials.data', 2)
    );
});

it('filters by status active', function (): void {
    actingAs($this->admin);

    $response = get(route('raw-materials.index', ['status' => 'active']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Inventory/RawMaterials/Index')
            ->has('rawMaterials.data', 1)
            ->where('rawMaterials.data.0.id', $this->rmActive->id)
    );
});

it('filters by status inactive', function (): void {
    actingAs($this->admin);

    $response = get(route('raw-materials.index', ['status' => 'inactive']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Inventory/RawMaterials/Index')
            ->has('rawMaterials.data', 1)
            ->where('rawMaterials.data.0.id', $this->rmInactive->id)
    );
});

it('filters by status all', function (): void {
    actingAs($this->admin);

    $response = get(route('raw-materials.index', ['status' => 'all']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Inventory/RawMaterials/Index')
            ->has('rawMaterials.data', 2)
    );
});

it('normalizes whitespace in search', function (): void {
    actingAs($this->admin);

    $response = get(route('raw-materials.index', ['search' => '   ALFA  ']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Inventory/RawMaterials/Index')
            ->has('rawMaterials.data', 1)
            ->where('rawMaterials.data.0.id', $this->rmActive->id)
    );
});

it('ignores invalid filter keys', function (): void {
    actingAs($this->admin);

    $response = get(route('raw-materials.index', ['invalid_key' => 'value']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Inventory/RawMaterials/Index')
            ->has('rawMaterials.data', 2)
            ->missing('filters.invalid_key')
    );
});

it('preserves query string in pagination', function (): void {
    actingAs($this->admin);

    for ($i = 0; $i < 16; $i++) {
        RawMaterial::factory()->create([
            'code' => sprintf('RM-TEST-%03d', $i),
        ]);
    }

    $response = get(route('raw-materials.index', ['search' => 'RM-TEST']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->has(
                'rawMaterials.links.1',
                fn ($link) => $link
                    ->where('active', true)
                    ->where('label', '1')
                    ->where('url', fn (?string $url) => $url === null || str_contains($url, 'search=RM-TEST'))
                    ->etc()
            )
            ->has(
                'rawMaterials.links.2',
                fn ($link) => $link
                    ->where('active', false)
                    ->where('label', '2')
                    ->where('url', fn (?string $url) => $url !== null && str_contains($url, 'search=RM-TEST'))
                    ->etc()
            )
    );
});

it('returns 403 for unauthorized user', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    actingAs($user);

    $response = get(route('raw-materials.index'));

    $response->assertForbidden();
});

it('rejects invalid status value', function (): void {
    actingAs($this->admin);

    $response = getJson(route('raw-materials.index', ['status' => 'xyz']));

    $response->assertJsonValidationErrors(['status']);
});
