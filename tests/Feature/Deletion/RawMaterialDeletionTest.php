<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Materias primas: el borrado físico lo deciden las claves foráneas; con historial, se desactiva
 * (docs/POLITICA_ELIMINACION.md §3.1 y §4).
 */
it('desactiva en lugar de fallar una materia prima que solo se usa como envase de una presentación', function () {
    actingAsRole(SystemRole::SuperAdmin);
    $package = RawMaterial::factory()->create();
    ProductVariant::factory()->create(['package_raw_material_id' => $package->id]);

    $this->get(route('raw-materials.show', $package))
        ->assertInertia(fn (Assert $page) => $page->where('hasActivity', true));

    $this->delete(route('raw-materials.destroy', $package))->assertRedirect(route('raw-materials.index'));

    $this->assertDatabaseHas('raw_materials', ['id' => $package->id, 'is_active' => false]);
});

it('elimina físicamente una materia prima que nunca se usó', function () {
    actingAsRole(SystemRole::SuperAdmin);
    $rawMaterial = RawMaterial::factory()->create();

    $this->delete(route('raw-materials.destroy', $rawMaterial))->assertRedirect(route('raw-materials.index'));

    $this->assertDatabaseMissing('raw_materials', ['id' => $rawMaterial->id]);
});
