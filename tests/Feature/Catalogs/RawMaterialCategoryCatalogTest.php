<?php

declare(strict_types=1);

use App\Enums\RawMaterialType;
use App\Enums\SystemRole;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;

// Catálogo de categorías de materia prima (3.2), en Configuración → Catálogos. El tipo de insumo decide dónde se
// ofrecen sus materias primas (decisión del 2026-09-29).

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function rawCategoryPayload(array $overrides = []): array
{
    return array_merge([
        'code' => 'RESINAS',
        'name' => 'Resinas',
        'description' => null,
        'type' => RawMaterialType::Chemical->value,
        'is_active' => true,
    ], $overrides);
}

it('lista las categorías con su tipo y cuántas materias primas tienen', function () {
    actingAsRole(SystemRole::SuperAdmin);
    $containers = RawMaterialCategory::factory()->container()->create(['name' => 'Envases metálicos']);
    RawMaterial::factory()->count(2)->create(['category_id' => $containers->id]);

    $this->get(route('catalogs.raw-material-categories.index'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Settings/Catalogs/RawMaterialCategories/Index')
            ->where('can.create', true)
            ->has('typeOptions', 4)
            ->where('categories.data', fn ($rows) => ($row = collect($rows)->firstWhere('id', $containers->id))
                && $row['type'] === 'container'
                && $row['type_label'] === 'Envase'
                && $row['raw_materials_count'] === 2));
});

it('filtra por tipo de insumo', function () {
    actingAsRole(SystemRole::SuperAdmin);
    RawMaterialCategory::factory()->create(['code' => 'RESINAS']);
    RawMaterialCategory::factory()->label()->create(['code' => 'ETIQUETAS']);

    $this->get(route('catalogs.raw-material-categories.index', ['type' => 'label']))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('categories.data', fn ($rows) => collect($rows)->pluck('code')->all() === ['ETIQUETAS']));
});

it('deja a Admin consultar las categorías pero no modificarlas', function () {
    actingAsRole(SystemRole::Admin);
    $category = RawMaterialCategory::factory()->create();

    $this->get(route('catalogs.raw-material-categories.index'))->assertSuccessful();
    $this->post(route('catalogs.raw-material-categories.store'), rawCategoryPayload())->assertForbidden();
    $this->put(route('catalogs.raw-material-categories.update', $category), rawCategoryPayload())->assertForbidden();
    $this->delete(route('catalogs.raw-material-categories.destroy', $category))->assertForbidden();
});

it('niega las categorías a los roles sin catalogs.view', function (SystemRole $role) {
    actingAsRole($role);

    $this->get(route('catalogs.raw-material-categories.index'))->assertForbidden();
    $this->get(route('catalogs.product-categories.index'))->assertForbidden();
})->with([SystemRole::Production, SystemRole::Operator, SystemRole::Commercial]);

it('crea una categoría con el código en mayúsculas y la audita', function () {
    actingAsRole(SystemRole::SuperAdmin);

    $this->post(route('catalogs.raw-material-categories.store'), rawCategoryPayload(['code' => ' bandejas ', 'name' => 'Bandejas', 'type' => 'secondary_packaging']))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('catalogs.raw-material-categories.index'));

    $category = RawMaterialCategory::query()->where('code', 'BANDEJAS')->sole();

    expect($category->type)->toBe(RawMaterialType::SecondaryPackaging)
        ->and(Activity::query()->where('subject_type', RawMaterialCategory::class)->where('subject_id', $category->id)->value('description'))
        ->toBe('Categoría de materia prima "BANDEJAS" creada');
});

it('valida los campos de la categoría', function (array $overrides, string $field) {
    actingAsRole(SystemRole::SuperAdmin);
    RawMaterialCategory::factory()->create(['code' => 'RESINAS', 'name' => 'Resinas']);

    $this->post(route('catalogs.raw-material-categories.store'), rawCategoryPayload($overrides))
        ->assertSessionHasErrors($field);
})->with([
    'código repetido, aunque cambien las mayúsculas' => [['code' => 'resinas', 'name' => 'Otra'], 'code'],
    // Dos categorías con el mismo nombre no se distinguirían en el selector de la materia prima.
    'nombre repetido, aunque cambien las mayúsculas' => [['code' => 'OTRA', 'name' => 'RESINAS'], 'name'],
    'sin nombre' => [['code' => 'OTRA', 'name' => ''], 'name'],
    'tipo que no existe' => [['code' => 'OTRA', 'type' => 'pintura'], 'type'],
]);

it('no deja cambiar el tipo de una categoría que tiene materias primas', function () {
    actingAsRole(SystemRole::SuperAdmin);
    $category = RawMaterialCategory::factory()->container()->create(['code' => 'ENV-METAL']);
    RawMaterial::factory()->create(['category_id' => $category->id]);

    $this->put(route('catalogs.raw-material-categories.update', $category), rawCategoryPayload(['code' => 'ENV-METAL', 'type' => 'chemical']))
        ->assertSessionHasErrors(['type' => 'No se puede cambiar el tipo: la categoría tiene 1 materia prima. Muévela a otra categoría primero.']);

    expect($category->fresh()->type)->toBe(RawMaterialType::Container);
});

it('deja cambiar el tipo de una categoría vacía y editar el resto de una con materias primas', function () {
    actingAsRole(SystemRole::SuperAdmin);
    $empty = RawMaterialCategory::factory()->create(['code' => 'VACIA']);
    $used = RawMaterialCategory::factory()->container()->create(['code' => 'ENV-METAL']);
    RawMaterial::factory()->create(['category_id' => $used->id]);

    $this->put(route('catalogs.raw-material-categories.update', $empty), rawCategoryPayload(['code' => 'VACIA', 'type' => 'label']))
        ->assertSessionHasNoErrors();
    $this->put(route('catalogs.raw-material-categories.update', $used), rawCategoryPayload(['code' => 'ENV-METAL', 'name' => 'Envases de lata', 'type' => 'container']))
        ->assertSessionHasNoErrors();

    expect($empty->fresh()->type)->toBe(RawMaterialType::Label)
        ->and($used->fresh()->name)->toBe('Envases de lata');
});

it('no deja poner el nombre de otra categoría al editar, pero sí cambiar las mayúsculas del propio', function () {
    actingAsRole(SystemRole::SuperAdmin);
    RawMaterialCategory::factory()->create(['code' => 'RESINAS', 'name' => 'Resinas']);
    $solvents = RawMaterialCategory::factory()->create(['code' => 'SOLVENTES', 'name' => 'Solventes']);

    $this->put(route('catalogs.raw-material-categories.update', $solvents), rawCategoryPayload(['code' => 'SOLVENTES', 'name' => 'resinas']))
        ->assertSessionHasErrors('name');
    $this->put(route('catalogs.raw-material-categories.update', $solvents), rawCategoryPayload(['code' => 'SOLVENTES', 'name' => 'SOLVENTES']))
        ->assertSessionHasNoErrors();

    expect($solvents->fresh()->name)->toBe('SOLVENTES');
});

it('conserva el estado de la categoría si la edición no lo envía', function () {
    actingAsRole(SystemRole::SuperAdmin);
    $category = RawMaterialCategory::factory()->create(['code' => 'RESINAS', 'is_active' => false]);
    $payload = rawCategoryPayload();
    unset($payload['is_active']);

    $this->put(route('catalogs.raw-material-categories.update', $category), $payload)->assertSessionHasNoErrors();

    expect($category->fresh()->is_active)->toBeFalse();
});

it('elimina una categoría sin materias primas y rechaza una con materias primas', function () {
    actingAsRole(SystemRole::SuperAdmin);
    $empty = RawMaterialCategory::factory()->create();
    $used = RawMaterialCategory::factory()->create();
    RawMaterial::factory()->create(['category_id' => $used->id]);

    $this->delete(route('catalogs.raw-material-categories.destroy', $empty))
        ->assertRedirect(route('catalogs.raw-material-categories.index'));
    $this->delete(route('catalogs.raw-material-categories.destroy', $used))
        ->assertSessionHas('error', 'La categoría tiene materias primas. Desactívala en su lugar.');

    expect(RawMaterialCategory::query()->whereKey($empty->id)->exists())->toBeFalse()
        ->and(RawMaterialCategory::query()->whereKey($used->id)->exists())->toBeTrue();
});
