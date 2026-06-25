<?php

use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

describe('Raw Material Store Validation', function (): void {
    beforeEach(function (): void {
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
        $this->category = RawMaterialCategory::create([
            'code' => 'TEST-CAT',
            'name' => 'Categoría Test',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->validData = [
            'code' => 'MP001',
            'category_id' => $this->category->id,
            'unit_of_measure_id' => $this->unit->id,
            'minimum_stock' => 10,
            'alert_days_before_expiry' => 30,
            'is_active' => true,
        ];
    });

    it('requires code', function (): void {
        $response = $this->actingAs($this->admin)
            ->post(route('raw-materials.store'), [
                ...$this->validData,
                'code' => '',
            ]);

        $response->assertSessionHasErrors('code');
    });

    it('requires code to be unique', function (): void {
        RawMaterial::create($this->validData);

        $response = $this->actingAs($this->admin)
            ->post(route('raw-materials.store'), [
                ...$this->validData,
                'code' => 'MP001',
            ]);

        $response->assertSessionHasErrors('code');
    });

    it('requires unit_of_measure_id to exist', function (): void {
        $response = $this->actingAs($this->admin)
            ->post(route('raw-materials.store'), [
                ...$this->validData,
                'unit_of_measure_id' => 9999,
            ]);

        $response->assertSessionHasErrors('unit_of_measure_id');
    });

    it('requires minimum_stock to be non-negative', function (): void {
        $response = $this->actingAs($this->admin)
            ->post(route('raw-materials.store'), [
                ...$this->validData,
                'minimum_stock' => -5,
            ]);

        $response->assertSessionHasErrors('minimum_stock');
    });

    it('allows zero alert days before expiry for materials without expiry tracking', function (): void {
        $response = $this->actingAs($this->admin)
            ->post(route('raw-materials.store'), [
                ...$this->validData,
                'alert_days_before_expiry' => 0,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('raw_materials', [
            'code' => 'MP001',
            'alert_days_before_expiry' => 0,
        ]);
    });

    it('requires category_id to exist', function (): void {
        $response = $this->actingAs($this->admin)
            ->post(route('raw-materials.store'), [
                ...$this->validData,
                'category_id' => 9999,
            ]);

        $response->assertSessionHasErrors('category_id');
    });
});
