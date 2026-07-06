<?php

declare(strict_types=1);

use App\Enums\WarehouseType;
use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    if (Role::count() === 0) {
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'produccion']);
        Role::create(['name' => 'comercial']);
    }

    $this->admin = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

    $this->unit = UnitOfMeasure::create([
        'code' => 'kg',
        'name' => 'Kilogramo',
        'symbol' => 'kg',
    ]);

    $this->productCategory = ProductCategory::create([
        'name' => 'Pinturas',
    ]);

    $this->rawMaterialCategory = RawMaterialCategory::create([
        'code' => 'RM-FORM-UPD',
        'name' => 'Materia Prima Fórmulas',
        'is_active' => true,
    ]);

    $this->product = Product::create([
        'code' => 'P-FORM-UPD',
        'name' => 'Pintura Editable',
        'category_id' => $this->productCategory->id,
        'unit_of_measure_id' => $this->unit->id,
        'cif_percentage' => 25,
        'price_threshold' => 3,
        'is_active' => true,
    ]);

    $this->rawMaterialOne = RawMaterial::create([
        'code' => 'RM-FORM-01',
        'category_id' => $this->rawMaterialCategory->id,
        'unit_of_measure_id' => $this->unit->id,
        'current_price' => 10,
        'minimum_stock' => 0,
        'alert_days_before_expiry' => 30,
        'is_active' => true,
    ]);

    $this->rawMaterialTwo = RawMaterial::create([
        'code' => 'RM-FORM-02',
        'category_id' => $this->rawMaterialCategory->id,
        'unit_of_measure_id' => $this->unit->id,
        'current_price' => 5,
        'minimum_stock' => 0,
        'alert_days_before_expiry' => 30,
        'is_active' => true,
    ]);

    $this->formula = Formula::create([
        'product_id' => $this->product->id,
        'version' => 1,
        'is_active' => true,
        'notes' => 'Versión original',
        'created_by' => $this->admin->id,
    ]);

    FormulaDetail::create([
        'formula_id' => $this->formula->id,
        'raw_material_id' => $this->rawMaterialOne->id,
        'quantity' => 2,
        'unit_of_measure_id' => $this->unit->id,
        'step_order' => 1,
    ]);
});

test('it updates an unused formula and replaces its details', function () {
    $response = $this->put(route('formulas.update', $this->formula), [
        'notes' => 'Versión corregida',
        'is_active' => true,
        'details' => [
            [
                'raw_material_id' => $this->rawMaterialTwo->id,
                'quantity' => 3.5,
                'unit_of_measure_id' => $this->unit->id,
            ],
        ],
    ]);

    $response
        ->assertRedirect(route('formulas.show', $this->formula))
        ->assertSessionHas('success', 'Fórmula actualizada exitosamente.');

    $this->formula->refresh();

    expect($this->formula->notes)->toBe('Versión corregida');
    expect($this->formula->details)->toHaveCount(1);
    expect((int) $this->formula->details->first()->raw_material_id)->toBe($this->rawMaterialTwo->id);
    expect((float) $this->formula->details->first()->quantity)->toBe(3.5);
});

test('it accepts comma decimal quantities when updating a formula', function () {
    $response = $this->put(route('formulas.update', $this->formula), [
        'notes' => 'Versión corregida con coma',
        'is_active' => true,
        'details' => [
            [
                'raw_material_id' => $this->rawMaterialTwo->id,
                'quantity' => '3,5',
                'unit_of_measure_id' => $this->unit->id,
            ],
        ],
    ]);

    $response
        ->assertRedirect(route('formulas.show', $this->formula))
        ->assertSessionHas('success', 'Fórmula actualizada exitosamente.');

    $this->formula->refresh();

    expect($this->formula->notes)->toBe('Versión corregida con coma');
    expect($this->formula->details)->toHaveCount(1);
    expect((float) $this->formula->details->first()->quantity)->toBe(3.5);
});

test('it does not allow updating a formula already used in production orders', function () {
    $warehouse = Warehouse::create([
        'name' => 'Planta Principal',
        'city' => 'Bogota',
        'type' => WarehouseType::Factory,
        'is_active' => true,
    ]);

    ProductionOrder::create([
        'order_number' => 'OP-FORM-0001',
        'product_id' => $this->product->id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'planned_date' => now()->toDateString(),
        'status' => 'pending',
        'created_by' => $this->admin->id,
    ]);

    $response = $this->put(route('formulas.update', $this->formula), [
        'notes' => 'No debería guardar',
        'is_active' => true,
        'details' => [
            [
                'raw_material_id' => $this->rawMaterialTwo->id,
                'quantity' => 7,
                'unit_of_measure_id' => $this->unit->id,
            ],
        ],
    ]);

    $response
        ->assertRedirect(route('formulas.show', $this->formula))
        ->assertSessionHas('error', 'Esta fórmula ya fue usada en órdenes de producción y no se puede editar.');

    $this->formula->refresh();

    expect($this->formula->notes)->toBe('Versión original');
    expect($this->formula->details)->toHaveCount(1);
    expect((int) $this->formula->details->first()->raw_material_id)->toBe($this->rawMaterialOne->id);
});
