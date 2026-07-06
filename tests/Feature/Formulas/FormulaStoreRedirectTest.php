<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
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
        'code' => 'RM-REDIR',
        'name' => 'Materia Prima Redirect',
        'is_active' => true,
    ]);

    $this->product = Product::create([
        'code' => 'P-REDIR',
        'name' => 'Pintura Redirect',
        'category_id' => $this->productCategory->id,
        'unit_of_measure_id' => $this->unit->id,
        'cif_percentage' => 25,
        'price_threshold' => 3,
        'is_active' => true,
    ]);

    $this->rawMaterial = RawMaterial::create([
        'code' => 'RM-REDIR-01',
        'category_id' => $this->rawMaterialCategory->id,
        'unit_of_measure_id' => $this->unit->id,
        'current_price' => 10,
        'minimum_stock' => 0,
        'alert_days_before_expiry' => 30,
        'is_active' => true,
    ]);
});

function validFormulaPayload(int $productId, int $rawMaterialId, int $unitId, array $extra = []): array
{
    return array_merge([
        'product_id' => $productId,
        'notes' => 'Notas de prueba',
        'details' => [
            [
                'raw_material_id' => $rawMaterialId,
                'quantity' => '1.5',
                'unit_of_measure_id' => $unitId,
            ],
        ],
    ], $extra);
}

test('stores formula and redirects to formulas index by default', function () {
    $response = $this->post(
        route('formulas.store'),
        validFormulaPayload($this->product->id, $this->rawMaterial->id, $this->unit->id),
    );

    $response
        ->assertRedirect(route('formulas.index'))
        ->assertSessionHas('success');
});

test('stores formula and redirects to return_to when provided', function () {
    $returnTo = route('products.show', $this->product);

    $response = $this->post(
        route('formulas.store'),
        validFormulaPayload($this->product->id, $this->rawMaterial->id, $this->unit->id, [
            'return_to' => $returnTo,
        ]),
    );

    $response
        ->assertRedirect('/products/'.$this->product->id)
        ->assertSessionHas('success');
});

test('stores formula and redirects to filtered formulas list via return_to', function () {
    $returnTo = route('formulas.index', ['search' => 'pintura']);

    $response = $this->post(
        route('formulas.store'),
        validFormulaPayload($this->product->id, $this->rawMaterial->id, $this->unit->id, [
            'return_to' => $returnTo,
        ]),
    );

    $response
        ->assertRedirect('/formulas?search=pintura')
        ->assertSessionHas('success');
});

test('rejects unsafe return_to and falls back to formulas index', function () {
    $response = $this->post(
        route('formulas.store'),
        validFormulaPayload($this->product->id, $this->rawMaterial->id, $this->unit->id, [
            'return_to' => 'https://evil.example.com/phish',
        ]),
    );

    $response->assertRedirect(route('formulas.index'));
});

test('rejects backslash protocol-relative return_to paths', function () {
    $response = $this->post(
        route('formulas.store'),
        validFormulaPayload($this->product->id, $this->rawMaterial->id, $this->unit->id, [
            'return_to' => '/\\evil.example.com/phish',
        ]),
    );

    $response->assertRedirect(route('formulas.index'));
});

test('allows return_to query values containing url-like substrings', function () {
    $returnTo = '/products?search=http://not-a-redirect';

    $response = $this->post(
        route('formulas.store'),
        validFormulaPayload($this->product->id, $this->rawMaterial->id, $this->unit->id, [
            'return_to' => $returnTo,
        ]),
    );

    $response->assertRedirect($returnTo);
});

test('create page receives return_to from query string', function () {
    $returnTo = route('products.show', $this->product);

    $this->get(route('formulas.create', [
        'product_id' => $this->product->id,
        'return_to' => $returnTo,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Formulas/Create')
            ->where('selectedProductId', (string) $this->product->id)
            ->where('returnTo', '/products/'.$this->product->id));
});
