<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

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
    $this->productionUser->assignRole(SystemRole::Production->value);

    $this->comercialUser = User::factory()->create(['email_verified_at' => now()]);
    $this->comercialUser->assignRole(SystemRole::Commercial->value);

    $this->product = Product::create([
        'name' => 'Producto Test',
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
                ->where('products.data.0.name', 'Producto Test')
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
            'name' => 'Otro Producto',
            'category_id' => $this->category->id,
            'unit_of_measure_id' => $this->unit->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->adminUser)
            ->get(route('admin.costs.index', ['search' => 'Test']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.name', 'Producto Test')
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

    // Auditoría 3.6, PR 5: CST-01. El margen se guardaba redondeado a 2 decimales sin avisar.
    it('validates sales_margin has at most two decimals', function () {
        $this->actingAs($this->adminUser)
            ->patch(route('admin.costs.update', $this->product), ['sales_margin' => '12.345'])
            ->assertSessionHasErrors('sales_margin');
    });

    // P5-4: el precio que se digita es dinero, con 2 decimales y el tope de su tipo.
    it('validates sales_price as money', function (string $price) {
        $this->actingAs($this->adminUser)
            ->patch(route('admin.costs.update', $this->product), ['sales_price' => $price])
            ->assertSessionHasErrors('sales_price');
    })->with(['tres decimales' => ['115.005'], 'mayor que el tipo Dinero' => ['1000000000000'], 'notación científica' => ['1e20']]);

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
