<?php

use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

describe('Raw Material Authorization', function () {
    beforeEach(function () {
        // Crear roles si no existen
        if (Role::count() === 0) {
            Role::create(['name' => 'admin']);
            Role::create(['name' => 'produccion']);
            Role::create(['name' => 'comercial']);
        }

        $this->unit = UnitOfMeasure::create([
            'code' => 'kg',
            'name' => 'Kilogramo',
            'symbol' => 'kg',
        ]);
    });

    describe('index', function () {
        it('allows admin to view raw materials list', function () {
            $admin = User::factory()->create();
            $admin->assignRole('admin');

            $response = $this->actingAs($admin)
                ->get(route('raw-materials.index'));

            $response->assertOk();
        });

        it('allows produccion to view raw materials list', function () {
            $user = User::factory()->create();
            $user->assignRole('produccion');

            $response = $this->actingAs($user)
                ->get(route('raw-materials.index'));

            $response->assertOk();
        });

        it('allows comercial to view raw materials list', function () {
            $user = User::factory()->create();
            $user->assignRole('comercial');

            $response = $this->actingAs($user)
                ->get(route('raw-materials.index'));

            $response->assertOk();
        });

        it('requires authentication', function () {
            $response = $this->get(route('raw-materials.index'));

            $response->assertRedirect(route('login'));
        });
    });

    describe('show', function () {
        beforeEach(function () {
            $this->rawMaterial = RawMaterial::create([
                'code' => 'MP001',
                'unit_of_measure_id' => $this->unit->id,
                'current_price' => 100.00,
                'minimum_stock' => 10,
                'alert_days_before_expiry' => 30,
                'is_active' => true,
            ]);
        });

        it('allows admin to view raw material detail', function () {
            $admin = User::factory()->create();
            $admin->assignRole('admin');

            $response = $this->actingAs($admin)
                ->get(route('raw-materials.show', $this->rawMaterial));

            $response->assertOk();
        });

        it('allows produccion to view raw material detail', function () {
            $user = User::factory()->create();
            $user->assignRole('produccion');

            $response = $this->actingAs($user)
                ->get(route('raw-materials.show', $this->rawMaterial));

            $response->assertOk();
        });

        it('forbids comercial to view raw material detail', function () {
            $user = User::factory()->create();
            $user->assignRole('comercial');

            $response = $this->actingAs($user)
                ->get(route('raw-materials.show', $this->rawMaterial));

            $response->assertForbidden();
        });
    });

    describe('create', function () {
        it('allows admin to access create form', function () {
            $admin = User::factory()->create();
            $admin->assignRole('admin');

            $response = $this->actingAs($admin)
                ->get(route('raw-materials.create'));

            $response->assertOk();
        });

        it('forbids produccion to access create form', function () {
            $user = User::factory()->create();
            $user->assignRole('produccion');

            $response = $this->actingAs($user)
                ->get(route('raw-materials.create'));

            $response->assertForbidden();
        });

        it('forbids comercial to access create form', function () {
            $user = User::factory()->create();
            $user->assignRole('comercial');

            $response = $this->actingAs($user)
                ->get(route('raw-materials.create'));

            $response->assertForbidden();
        });
    });

    describe('store', function () {
        it('allows admin to create raw material', function () {
            $admin = User::factory()->create();
            $admin->assignRole('admin');

            $response = $this->actingAs($admin)
                ->post(route('raw-materials.store'), [
                    'code' => 'MP002',
                    'unit_of_measure_id' => $this->unit->id,
                    'current_price' => 150.00,
                    'minimum_stock' => 20,
                    'alert_days_before_expiry' => 30,
                    'is_active' => true,
                ]);

            $response->assertRedirect(route('raw-materials.index'));
            $this->assertDatabaseHas('raw_materials', ['code' => 'MP002']);
        });

        it('forbids produccion to create raw material', function () {
            $user = User::factory()->create();
            $user->assignRole('produccion');

            $response = $this->actingAs($user)
                ->post(route('raw-materials.store'), [
                    'code' => 'MP003',
                    'unit_of_measure_id' => $this->unit->id,
                    'current_price' => 150.00,
                ]);

            $response->assertForbidden();
        });
    });
});
