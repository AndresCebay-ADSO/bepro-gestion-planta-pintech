<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\RawMaterial;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $role = Role::findOrCreate('admin');
    $this->admin = User::factory()->create();
    $this->admin->assignRole($role);
});

test('inventory movements edit includes unit_price in batches query', function (): void {
    $warehouse = Warehouse::factory()->create();
    $rawMaterial = RawMaterial::factory()->create(['current_price' => 25.50]);
    $batch = InventoryBatch::factory()->create([
        'raw_material_id' => $rawMaterial->id,
        'warehouse_id' => $warehouse->id,
        'unit_price' => 25.50,
        'remaining_quantity' => 100,
    ]);
    $movement = InventoryMovement::factory()->entry()->create([
        'raw_material_id' => $rawMaterial->id,
        'warehouse_id' => $warehouse->id,
        'batch_id' => $batch->id,
        'cost_price' => 25.50,
        'created_by' => $this->admin->id,
    ]);

    actingAs($this->admin)
        ->get(route('inventory-movements.edit', $movement))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Inventory/Movements/Edit')
            ->has('batches', 1)
            ->where('batches.0.unit_price', fn ($val) => (float) $val === 25.5)
        );
});
