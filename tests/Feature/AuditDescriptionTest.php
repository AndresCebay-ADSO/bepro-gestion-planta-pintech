<?php

declare(strict_types=1);

use App\Models\Formula;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Activity::query()->delete();

    $this->uom = UnitOfMeasure::create(['code' => 'L', 'name' => 'Litro', 'symbol' => 'L']);
    $this->category = ProductCategory::create(['name' => 'General']);
});

// --- Masculine models ---

test('product audit description uses Spanish with identifier on create', function () {
    $product = Product::create([
        'code' => 'P-001',
        'name' => 'Pintura Blanca',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->uom->id,
        'current_cost' => 10,
        'cif_percentage' => 0,
        'current_price' => 10,
        'price_threshold' => 0,
    ]);

    $log = Activity::where('subject_type', Product::class)
        ->where('subject_id', $product->id)
        ->where('event', 'created')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->description)->toBe('Producto "Pintura Blanca" creado')
        ->and($log->log_name)->toBe('productos');
});

test('user audit description uses Spanish with identifier on update', function () {
    $user = User::factory()->create(['name' => 'Carlos Admin']);

    Activity::query()->delete();

    $user->update(['name' => 'Carlos Editado']);

    $log = Activity::where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->where('event', 'updated')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->description)->toBe('Usuario "Carlos Editado" actualizado');
});

test('inventory movement has log name and Spanish description after fix', function () {
    $warehouse = Warehouse::factory()->create();
    $rawMaterial = RawMaterial::create([
        'code' => 'RM-MOV',
        'unit_of_measure_id' => $this->uom->id,
        'current_price' => 5000,
    ]);

    Activity::query()->delete();

    $movement = InventoryMovement::factory()->create([
        'raw_material_id' => $rawMaterial->id,
        'warehouse_id' => $warehouse->id,
    ]);

    $log = Activity::where('subject_type', InventoryMovement::class)
        ->where('subject_id', $movement->id)
        ->where('event', 'created')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->log_name)->toBe('movimientos_inventario')
        ->and($log->description)->toContain('Movimiento de inventario')
        ->and($log->description)->toContain('creado');
});

// --- Feminine models ---

test('warehouse audit description uses feminine Spanish on create', function () {
    $warehouse = Warehouse::create([
        'name' => 'Fábrica Principal',
        'city' => 'Cali',
        'type' => 'factory',
        'is_active' => true,
    ]);

    $log = Activity::where('subject_type', Warehouse::class)
        ->where('subject_id', $warehouse->id)
        ->where('event', 'created')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->description)->toBe('Bodega "Fábrica Principal" creada');
});

test('formula audit description uses feminine Spanish on update', function () {
    $user = User::factory()->create();
    $product = Product::create([
        'code' => 'P-FORM',
        'name' => 'Pintura Test',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->uom->id,
        'current_cost' => 10,
        'cif_percentage' => 0,
        'current_price' => 10,
        'price_threshold' => 0,
    ]);

    $formula = Formula::create([
        'product_id' => $product->id,
        'version' => 3,
        'is_active' => true,
        'created_by' => $user->id,
    ]);

    Activity::query()->delete();

    $formula->update(['is_active' => false]);

    $log = Activity::where('subject_type', Formula::class)
        ->where('subject_id', $formula->id)
        ->where('event', 'updated')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->description)->toBe('Fórmula "3" actualizada');
});

test('raw material audit description uses feminine Spanish on create', function () {
    $rawMaterial = RawMaterial::create([
        'code' => 'MP-001',
        'unit_of_measure_id' => $this->uom->id,
        'current_price' => 5000,
    ]);

    $log = Activity::where('subject_type', RawMaterial::class)
        ->where('subject_id', $rawMaterial->id)
        ->where('event', 'created')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->description)->toBe('Materia prima "MP-001" creada');
});

test('production order audit description uses feminine Spanish on create', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::create([
        'name' => 'Planta Cali',
        'city' => 'Cali',
        'type' => 'factory',
        'is_active' => true,
    ]);
    $product = Product::create([
        'code' => 'P-OP',
        'name' => 'Pintura OP',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->uom->id,
        'current_cost' => 10,
        'cif_percentage' => 0,
        'current_price' => 10,
        'price_threshold' => 0,
    ]);
    $formula = Formula::create([
        'product_id' => $product->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => $user->id,
    ]);

    Activity::query()->delete();

    $order = ProductionOrder::create([
        'order_number' => 'OP-00042',
        'product_id' => $product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 100,
        'status' => 'pending',
        'planned_date' => now(),
        'created_by' => $user->id,
    ]);

    $log = Activity::where('subject_type', ProductionOrder::class)
        ->where('subject_id', $order->id)
        ->where('event', 'created')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->description)->toBe('Orden de producción "OP-00042" creada');
});

// --- Delete event ---

test('product audit description uses Spanish on delete', function () {
    $product = Product::create([
        'code' => 'P-DEL',
        'name' => 'Producto Test',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->uom->id,
        'current_cost' => 10,
        'cif_percentage' => 0,
        'current_price' => 10,
        'price_threshold' => 0,
    ]);

    Activity::query()->delete();

    $product->delete();

    $log = Activity::where('subject_type', Product::class)
        ->where('subject_id', $product->id)
        ->where('event', 'deleted')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->description)->toBe('Producto "Producto Test" eliminado');
});

test('warehouse audit description uses feminine Spanish on delete', function () {
    $warehouse = Warehouse::create([
        'name' => 'Bodega Test',
        'city' => 'Bogotá',
        'type' => 'storage',
        'is_active' => true,
    ]);

    Activity::query()->delete();

    $warehouse->delete();

    $log = Activity::where('subject_type', Warehouse::class)
        ->where('subject_id', $warehouse->id)
        ->where('event', 'deleted')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->description)->toBe('Bodega "Bodega Test" eliminada');
});

// --- Inventory batch with lot_number ---

test('inventory batch audit description uses lot_number as identifier', function () {
    $rawMaterial = RawMaterial::create([
        'code' => 'RM-BATCH',
        'unit_of_measure_id' => $this->uom->id,
        'current_price' => 5000,
    ]);
    $warehouse = Warehouse::factory()->create();

    Activity::query()->delete();

    $batch = InventoryBatch::create([
        'raw_material_id' => $rawMaterial->id,
        'warehouse_id' => $warehouse->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 5,
        'entry_date' => now(),
        'lot_number' => 'LOT-2026-001',
    ]);

    $log = Activity::where('subject_type', InventoryBatch::class)
        ->where('subject_id', $batch->id)
        ->where('event', 'created')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->description)->toBe('Lote de inventario "LOT-2026-001" creado');
});

// --- Unknown events pass through ---

test('unknown event names pass through untranslated', function () {
    $product = Product::create([
        'code' => 'P-UNK',
        'name' => 'Test',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->uom->id,
        'current_cost' => 10,
        'cif_percentage' => 0,
        'current_price' => 10,
        'price_threshold' => 0,
    ]);

    expect($product->getAuditDescription('restored'))->toBe('Producto "Test" restored');
});

// --- Failed login listener ---

test('failed login listener logs description in Spanish', function () {
    $this->post(route('login'), [
        'email' => 'nobody@test.com',
        'password' => 'wrong-password',
    ]);

    $log = Activity::where('log_name', 'auth')
        ->where('event', 'failed_login')
        ->latest()
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->description)->toBe('Intento de inicio de sesión fallido: nobody@test.com');
});

// --- Role change manual log stays in Spanish ---

test('role change manual log remains in Spanish', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::create(['name' => 'admin']));
    $this->actingAs($admin);

    $target = User::factory()->create(['name' => 'Target User']);
    $target->assignRole(Role::create(['name' => 'operador']));

    Activity::query()->delete();

    $this->put(route('users.update', $target), [
        'name' => 'Target User',
        'email' => $target->email,
        'is_active' => true,
        'role' => 'admin',
    ]);

    $log = Activity::where('log_name', 'security')
        ->where('event', 'role_changed')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->description)->toContain('Rol de usuario modificado de operador a admin');
});
