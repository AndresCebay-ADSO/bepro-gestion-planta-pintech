<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'produccion']);
    Role::firstOrCreate(['name' => 'comercial']);

    $this->unit = UnitOfMeasure::create([
        'code' => 'kg',
        'name' => 'Kilogramo',
        'symbol' => 'kg',
    ]);

    $this->category = ProductCategory::create([
        'name' => 'Categoría Test',
    ]);

    $this->adminUser = User::factory()->create(['email_verified_at' => now()]);
    $this->adminUser->assignRole('admin');

    $this->productionUser = User::factory()->create(['email_verified_at' => now()]);
    $this->productionUser->assignRole('produccion');

    $this->comercialUser = User::factory()->create(['email_verified_at' => now()]);
    $this->comercialUser->assignRole('comercial');

    $this->product = Product::create([
        'code' => 'PROD-001',
        'name' => 'Producto Test',
        'brand' => 'BEPRO',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'current_cost' => 80.0000,
        'cif_percentage' => 15.00,
        'current_price' => 92.0000,
        'is_active' => true,
    ]);
});

describe('index', function () {
    it('allows admin to view costs page', function () {
        $this->actingAs($this->adminUser)
            ->get(route('admin.costs.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Costs/Index')
                ->has('products.data', 1)
                ->where('products.data.0.id', $this->product->id)
                ->where('products.data.0.code', 'PROD-001')
                ->where('products.data.0.current_cost', '80.0000')
                ->where('products.data.0.cif_percentage', '15.00')
                ->where('products.data.0.current_price', '92.0000')
                ->where('can.update_margin', true)
            );
    });

    it('forbids produccion to view costs page', function () {
        $this->actingAs($this->productionUser)
            ->get(route('admin.costs.index'))
            ->assertForbidden();
    });

    it('forbids comercial to view costs page', function () {
        $this->actingAs($this->comercialUser)
            ->get(route('admin.costs.index'))
            ->assertForbidden();
    });

    it('requires authentication', function () {
        $this->get(route('admin.costs.index'))
            ->assertRedirect(route('login'));
    });

    it('filters products by search term', function () {
        Product::create([
            'code' => 'PROD-002',
            'name' => 'Otro Producto',
            'brand' => 'BEPRO',
            'category_id' => $this->category->id,
            'unit_of_measure_id' => $this->unit->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->adminUser)
            ->get(route('admin.costs.index', ['search' => 'Test']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.code', 'PROD-001')
            );
    });
});

describe('update', function () {
    it('allows admin to update sales_margin', function () {
        $this->actingAs($this->adminUser)
            ->patch(route('admin.costs.update', $this->product), [
                'sales_margin' => 25.00,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'sales_margin' => '25.00',
        ]);
    });

    it('allows admin to set sales_margin to null', function () {
        $this->product->update(['sales_margin' => 10.00]);

        $this->actingAs($this->adminUser)
            ->patch(route('admin.costs.update', $this->product), [
                'sales_margin' => null,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'sales_margin' => null,
        ]);
    });

    it('validates sales_margin is numeric', function () {
        $this->actingAs($this->adminUser)
            ->patch(route('admin.costs.update', $this->product), [
                'sales_margin' => 'not-a-number',
            ])
            ->assertSessionHasErrors('sales_margin');
    });

    it('validates sales_margin minimum is 0', function () {
        $this->actingAs($this->adminUser)
            ->patch(route('admin.costs.update', $this->product), [
                'sales_margin' => -1,
            ])
            ->assertSessionHasErrors('sales_margin');
    });

    it('validates sales_margin maximum is 99.99', function () {
        $this->actingAs($this->adminUser)
            ->patch(route('admin.costs.update', $this->product), [
                'sales_margin' => 100,
            ])
            ->assertSessionHasErrors('sales_margin');
    });

    it('allows admin to update via sales_price', function () {
        $this->actingAs($this->adminUser)
            ->patch(route('admin.costs.update', $this->product), [
                'sales_price' => 115.00,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'sales_margin' => '20.00',
        ]);
    });

    it('rejects sales_price when current_price is zero', function () {
        $this->product->update(['current_price' => 0]);

        $this->actingAs($this->adminUser)
            ->patch(route('admin.costs.update', $this->product), [
                'sales_price' => 100.00,
            ])
            ->assertSessionHasErrors('sales_price');
    });

    it('rejects sales_price when current_price is null', function () {
        $this->product->update(['current_price' => null]);

        $this->actingAs($this->adminUser)
            ->patch(route('admin.costs.update', $this->product), [
                'sales_price' => 100.00,
            ])
            ->assertSessionHasErrors('sales_price');
    });

    it('rejects sales_price of zero', function () {
        $this->actingAs($this->adminUser)
            ->patch(route('admin.costs.update', $this->product), [
                'sales_price' => 0,
            ])
            ->assertSessionHasErrors('sales_price');

        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'sales_margin' => null,
        ]);
    });

    it('allows sales_margin up to 99.99', function () {
        $this->actingAs($this->adminUser)
            ->patch(route('admin.costs.update', $this->product), [
                'sales_margin' => 99.99,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'sales_margin' => '99.99',
        ]);
    });

    it('rejects sales_price that generates negative margin', function () {
        $this->actingAs($this->adminUser)
            ->patch(route('admin.costs.update', $this->product), [
                'sales_price' => 50.00,
            ])
            ->assertSessionHasErrors('sales_price');

        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'sales_margin' => null,
        ]);
    });

    it('forbids produccion to update sales_margin', function () {
        $this->actingAs($this->productionUser)
            ->patch(route('admin.costs.update', $this->product), [
                'sales_margin' => 25.00,
            ])
            ->assertForbidden();
    });

    it('forbids comercial to update sales_margin', function () {
        $this->actingAs($this->comercialUser)
            ->patch(route('admin.costs.update', $this->product), [
                'sales_margin' => 25.00,
            ])
            ->assertForbidden();
    });
});
