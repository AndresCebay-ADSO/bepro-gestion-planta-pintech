<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use App\Models\ShrinkWrapType;
use App\Models\ShrinkWrapTypeItem;
use App\Services\RawMaterialUsageService;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;

// Tipos de termoencogido (3.8), en Configuración → Catálogos: una receta de empaque secundario por aplicación. Los
// gestionan Admin y SuperAdmin; Producción y Operario solo registrarán (decisión del 2026-10-02).

beforeEach(function () {
    $this->bag = RawMaterial::factory()->secondaryPackaging()->create(['code' => 'BOLSA-1']);
    $this->tray = RawMaterial::factory()->secondaryPackaging()->create(['code' => 'BANDEJA-1']);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function shrinkWrapTypePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Galón',
        'is_active' => true,
        'items' => [
            ['raw_material_id' => test()->bag->id, 'quantity' => '1'],
            ['raw_material_id' => test()->tray->id, 'quantity' => '1'],
        ],
    ], $overrides);
}

/**
 * @return array<string, mixed>
 */
function shrinkWrapMaterialPayload(RawMaterial $material, RawMaterialCategory $category): array
{
    return [
        'code' => $material->code,
        'category_id' => $category->id,
        'unit_of_measure_id' => $material->unit_of_measure_id,
        'minimum_stock' => 10,
        'alert_days_before_expiry' => 30,
        'is_active' => true,
    ];
}

it('lista los tipos con su receta por aplicación', function () {
    actingAsRole(SystemRole::Admin);
    $type = ShrinkWrapType::factory()->create(['name' => 'Cuarto']);
    ShrinkWrapTypeItem::factory()->create(['shrink_wrap_type_id' => $type->id, 'raw_material_id' => $this->tray->id, 'quantity' => '2']);

    $this->get(route('catalogs.shrink-wrap-types.index'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Settings/Catalogs/ShrinkWrapTypes/Index')
            ->where('can.create', true)
            ->where('types.data.0.name', 'Cuarto')
            ->where('types.data.0.items.0.code', 'BANDEJA-1')
            ->where('types.data.0.items.0.quantity', '2.0000')
            ->where('types.data.0.can.update', true));
});

it('deja gestionar los tipos a Admin y SuperAdmin', function (SystemRole $role) {
    actingAsRole($role);

    $this->get(route('catalogs.shrink-wrap-types.create'))->assertSuccessful();
    $this->post(route('catalogs.shrink-wrap-types.store'), shrinkWrapTypePayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('catalogs.shrink-wrap-types.index'));

    expect(ShrinkWrapType::query()->where('name', 'Galón')->sole()->items)->toHaveCount(2);
})->with([SystemRole::SuperAdmin, SystemRole::Admin]);

it('niega los tipos a quien no tiene catalogs.view ni el permiso de gestionarlos', function (SystemRole $role) {
    actingAsRole($role);
    $type = ShrinkWrapType::factory()->create();

    $this->get(route('catalogs.shrink-wrap-types.index'))->assertForbidden();
    $this->post(route('catalogs.shrink-wrap-types.store'), shrinkWrapTypePayload())->assertForbidden();
    $this->put(route('catalogs.shrink-wrap-types.update', $type), shrinkWrapTypePayload())->assertForbidden();
    $this->delete(route('catalogs.shrink-wrap-types.destroy', $type))->assertForbidden();
})->with([SystemRole::Production, SystemRole::Operator, SystemRole::Commercial]);

it('audita el tipo y cada línea de su receta', function () {
    actingAsRole(SystemRole::Admin);

    $this->post(route('catalogs.shrink-wrap-types.store'), shrinkWrapTypePayload());

    $type = ShrinkWrapType::query()->where('name', 'Galón')->sole();

    expect(Activity::query()->where('subject_type', ShrinkWrapType::class)->where('subject_id', $type->id)->value('description'))
        ->toBe('Tipo de termoencogido "Galón" creado')
        ->and(Activity::query()->where('subject_type', ShrinkWrapTypeItem::class)->where('event', 'created')->count())
        ->toBe(2);
});

it('acepta la coma decimal en la cantidad', function () {
    actingAsRole(SystemRole::Admin);

    $this->post(route('catalogs.shrink-wrap-types.store'), shrinkWrapTypePayload([
        'items' => [['raw_material_id' => $this->bag->id, 'quantity' => '1,5']],
    ]))->assertSessionHasNoErrors();

    expect(ShrinkWrapTypeItem::query()->sole()->quantity)->toBe('1.5000');
});

it('acepta una bolsa sin control de inventario en la receta', function () {
    actingAsRole(SystemRole::Admin);
    $untracked = RawMaterial::factory()->secondaryPackaging()->withoutInventoryTracking()->create();

    $this->post(route('catalogs.shrink-wrap-types.store'), shrinkWrapTypePayload([
        'items' => [['raw_material_id' => $untracked->id, 'quantity' => '1']],
    ]))->assertSessionHasNoErrors();
});

it('valida el tipo y su receta', function (Closure $overrides, string $field) {
    actingAsRole(SystemRole::Admin);
    ShrinkWrapType::factory()->create(['name' => 'Cuarto']);

    $this->post(route('catalogs.shrink-wrap-types.store'), shrinkWrapTypePayload($overrides()))
        ->assertSessionHasErrors($field);

    expect(ShrinkWrapType::query()->where('name', 'Galón')->exists())->toBeFalse();
})->with([
    'sin nombre' => [fn () => ['name' => ''], 'name'],
    'nombre repetido sin distinguir mayúsculas' => [fn () => ['name' => 'CUARTO'], 'name'],
    'sin receta' => [fn () => ['items' => []], 'items'],
    'materia prima repetida' => [fn () => ['items' => [
        ['raw_material_id' => test()->bag->id, 'quantity' => '1'],
        ['raw_material_id' => test()->bag->id, 'quantity' => '2'],
    ]], 'items.0.raw_material_id'],
    'materia prima química' => [fn () => ['items' => [
        ['raw_material_id' => RawMaterial::factory()->create()->id, 'quantity' => '1'],
    ]], 'items.0.raw_material_id'],
    'materia prima inactiva' => [fn () => ['items' => [
        ['raw_material_id' => RawMaterial::factory()->secondaryPackaging()->inactive()->create()->id, 'quantity' => '1'],
    ]], 'items.0.raw_material_id'],
    'cantidad cero' => [fn () => ['items' => [['raw_material_id' => test()->bag->id, 'quantity' => '0']]], 'items.0.quantity'],
    'notación científica' => [fn () => ['items' => [['raw_material_id' => test()->bag->id, 'quantity' => '1e20']]], 'items.0.quantity'],
    'cinco decimales' => [fn () => ['items' => [['raw_material_id' => test()->bag->id, 'quantity' => '1.00001']]], 'items.0.quantity'],
    'sobre el tope de la columna' => [fn () => ['items' => [['raw_material_id' => test()->bag->id, 'quantity' => '100000000']]], 'items.0.quantity'],
]);

it('sincroniza la receta al editar sin rehacer las líneas que siguen', function () {
    actingAsRole(SystemRole::Admin);
    $type = ShrinkWrapType::factory()->create(['name' => 'Galón']);
    $kept = ShrinkWrapTypeItem::factory()->create(['shrink_wrap_type_id' => $type->id, 'raw_material_id' => $this->bag->id, 'quantity' => '1']);
    $removed = ShrinkWrapTypeItem::factory()->create(['shrink_wrap_type_id' => $type->id, 'raw_material_id' => $this->tray->id, 'quantity' => '1']);
    $newTray = RawMaterial::factory()->secondaryPackaging()->create(['code' => 'BANDEJA-2']);

    $this->put(route('catalogs.shrink-wrap-types.update', $type), shrinkWrapTypePayload([
        'items' => [
            ['raw_material_id' => $this->bag->id, 'quantity' => '2'],
            ['raw_material_id' => $newTray->id, 'quantity' => '2'],
        ],
    ]))->assertSessionHasNoErrors();

    $items = $type->items()->get()->keyBy('raw_material_id');

    expect($items)->toHaveCount(2)
        ->and($items[$this->bag->id]->id)->toBe($kept->id)
        ->and($items[$this->bag->id]->quantity)->toBe('2.0000')
        ->and($items->has($newTray->id))->toBeTrue()
        ->and(ShrinkWrapTypeItem::query()->whereKey($removed->id)->exists())->toBeFalse()
        // La auditoría nombra la línea por su tipo y su materia prima, no por el id.
        ->and(Activity::query()->where('subject_type', ShrinkWrapTypeItem::class)->where('subject_id', $removed->id)->where('event', 'deleted')->value('description'))
        ->toBe('Línea de tipo de termoencogido "Galón · BANDEJA-1" eliminada');
});

it('conserva al editar una materia prima ya desactivada de la receta, pero no ofrece las inactivas para recetas nuevas', function () {
    actingAsRole(SystemRole::Admin);
    $type = ShrinkWrapType::factory()->create(['name' => 'Galón']);
    ShrinkWrapTypeItem::factory()->create(['shrink_wrap_type_id' => $type->id, 'raw_material_id' => $this->bag->id]);
    $this->bag->update(['is_active' => false]);

    $this->get(route('catalogs.shrink-wrap-types.create'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rawMaterialOptions', fn ($options) => collect($options)->pluck('label')->all() === ['BANDEJA-1']));

    $this->get(route('catalogs.shrink-wrap-types.edit', $type))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rawMaterialOptions', fn ($options) => collect($options)->pluck('label')->sort()->values()->all() === ['BANDEJA-1', 'BOLSA-1']
                && collect($options)->firstWhere('label', 'BOLSA-1')['is_active'] === false)
            // La pantalla avisa que la receta usa una materia prima desactivada.
            ->where('type.items.0.code', 'BOLSA-1')
            ->where('type.items.0.is_active', false));

    $this->put(route('catalogs.shrink-wrap-types.update', $type), shrinkWrapTypePayload([
        'name' => 'Galón grande',
        'items' => [['raw_material_id' => $this->bag->id, 'quantity' => '1']],
    ]))->assertSessionHasNoErrors();

    expect($type->fresh()->name)->toBe('Galón grande');
});

it('ofrece solo materias primas de empaque secundario', function () {
    actingAsRole(SystemRole::Admin);
    RawMaterial::factory()->create(['code' => 'RESINA-1']);

    $this->get(route('catalogs.shrink-wrap-types.create'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rawMaterialOptions', fn ($options) => collect($options)->pluck('label')->all() === ['BANDEJA-1', 'BOLSA-1']));
});

it('elimina un tipo que nunca se usó junto con su receta', function () {
    actingAsRole(SystemRole::Admin);
    $type = ShrinkWrapType::factory()->create();
    ShrinkWrapTypeItem::factory()->create(['shrink_wrap_type_id' => $type->id, 'raw_material_id' => $this->bag->id]);

    $this->delete(route('catalogs.shrink-wrap-types.destroy', $type))
        ->assertRedirect(route('catalogs.shrink-wrap-types.index'));

    expect(ShrinkWrapType::query()->whereKey($type->id)->exists())->toBeFalse()
        ->and(ShrinkWrapTypeItem::query()->count())->toBe(0);
});

it('cuenta como actividad estar en una receta: la materia prima no se puede eliminar', function () {
    actingAsRole(SystemRole::SuperAdmin);
    ShrinkWrapTypeItem::factory()->create(['raw_material_id' => $this->bag->id]);
    $this->bag->update(['is_active' => false]);

    $this->get(route('raw-materials.show', $this->bag))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('hasActivity', true)
            ->where('can.delete', false));

    $this->get(route('raw-materials.index', ['search' => 'BOLSA-1', 'status' => 'all']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rawMaterials.data.0.code', 'BOLSA-1')
            ->where('rawMaterials.data.0.has_activity', true));

    $this->get(route('raw-materials.show', $this->tray))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('hasActivity', false));
});

it('no deja pasar a otro tipo una bolsa que está en una receta, pero sí a otra categoría de empaque secundario', function () {
    actingAsRole(SystemRole::Admin);
    ShrinkWrapTypeItem::factory()->create(['raw_material_id' => $this->bag->id]);
    $chemicals = RawMaterialCategory::factory()->create();
    $otherBags = RawMaterialCategory::factory()->secondaryPackaging()->create();

    $this->put(route('raw-materials.update', $this->bag), shrinkWrapMaterialPayload($this->bag, $chemicals))
        ->assertSessionHasErrors(['category_id' => 'No se puede pasar a una categoría de tipo químico: esta materia prima está en la receta de 1 tipo de termoencogido.']);

    $this->put(route('raw-materials.update', $this->bag), shrinkWrapMaterialPayload($this->bag, $otherBags))
        ->assertSessionHasNoErrors();

    expect($this->bag->fresh()->category_id)->toBe($otherBags->id);
});

it('audita el borrado de cada línea de la receta al eliminar un tipo', function () {
    actingAsRole(SystemRole::Admin);
    $type = ShrinkWrapType::factory()->create();
    $item = ShrinkWrapTypeItem::factory()->create(['shrink_wrap_type_id' => $type->id, 'raw_material_id' => $this->bag->id]);

    $this->delete(route('catalogs.shrink-wrap-types.destroy', $type));

    expect(Activity::query()->where('subject_type', ShrinkWrapTypeItem::class)->where('subject_id', $item->id)->where('event', 'deleted')->exists())
        ->toBeTrue();
});

it('detecta la actividad aunque solo venga cargado alguno de sus indicadores', function () {
    ShrinkWrapTypeItem::factory()->create(['raw_material_id' => $this->bag->id]);

    // Un indicador cargado (sin actividad) no debe hacer creer que los demás también lo están.
    $material = RawMaterial::query()->withExists('inventoryBatches as activity_inventory_batches')->findOrFail($this->bag->id);

    expect(app(RawMaterialUsageService::class)->hasActivity($material))->toBeTrue();
});
