<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;

// Catálogo de unidades de medida (3.1), en Configuración → Catálogos. SuperAdmin lo gestiona; Admin solo lo consulta
// (docs/MATRIZ_RBAC.md §3).

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function uomPayload(array $overrides = []): array
{
    return array_merge([
        'code' => 'cun',
        'name' => 'Cuñete',
        'symbol' => 'cñ',
        'description' => null,
        'to_kg_conversion' => null,
        'to_liter_conversion' => '18.9271',
        'is_active' => true,
    ], $overrides);
}

/**
 * Una unidad con su factor de volumen y usada por una materia prima.
 */
function unitInUse(string $literFactor = '3.7854'): UnitOfMeasure
{
    $unit = UnitOfMeasure::factory()->create(['code' => 'gl', 'symbol' => 'gl', 'to_liter_conversion' => $literFactor]);
    RawMaterial::factory()->create(['unit_of_measure_id' => $unit->id]);

    return $unit;
}

it('lista las unidades con cuántos registros usan cada una', function () {
    actingAsRole(SystemRole::SuperAdmin);
    $used = unitInUse();
    ProductVariant::factory()->count(2)->create(['unit_of_measure_id' => $used->id]);
    $unused = UnitOfMeasure::factory()->create(['name' => 'Zeta sin uso']);

    $this->get(route('catalogs.units-of-measure.index'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Settings/Catalogs/UnitsOfMeasure/Index')
            ->where('can.create', true)
            ->where('units.data', fn ($rows) => collect($rows)->firstWhere('id', $used->id)['usage'] === [
                'raw_materials' => 1,
                'products' => 0,
                'product_variants' => 2,
                'formula_details' => 0,
            ] && collect($rows)->firstWhere('id', $used->id)['affects_conversions'] === true
                && collect($rows)->firstWhere('id', $unused->id)['in_use'] === false));
});

it('filtra por texto y por estado', function () {
    actingAsRole(SystemRole::SuperAdmin);
    UnitOfMeasure::factory()->create(['code' => 'kg', 'name' => 'Kilogramo', 'symbol' => 'kg']);
    UnitOfMeasure::factory()->create(['code' => 'l', 'name' => 'Litro', 'symbol' => 'L', 'is_active' => false]);

    $this->get(route('catalogs.units-of-measure.index', ['search' => 'KILO']))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('units.data', fn ($rows) => collect($rows)->pluck('code')->all() === ['kg']));

    $this->get(route('catalogs.units-of-measure.index', ['status' => 'inactive']))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('units.data', fn ($rows) => collect($rows)->pluck('code')->all() === ['l']));
});

it('deja a Admin consultar el catálogo pero no modificarlo', function () {
    actingAsRole(SystemRole::Admin);
    $unit = UnitOfMeasure::factory()->create();

    $this->get(route('catalogs.units-of-measure.index'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('can.create', false)
            ->where('units.data.0.can', ['update' => false, 'delete' => false]));

    $this->get(route('catalogs.units-of-measure.create'))->assertForbidden();
    $this->post(route('catalogs.units-of-measure.store'), uomPayload())->assertForbidden();
    $this->get(route('catalogs.units-of-measure.edit', $unit))->assertForbidden();
    $this->put(route('catalogs.units-of-measure.update', $unit), uomPayload())->assertForbidden();
    $this->delete(route('catalogs.units-of-measure.destroy', $unit))->assertForbidden();

    expect(UnitOfMeasure::query()->where('code', 'cun')->exists())->toBeFalse()
        ->and($unit->fresh())->not->toBeNull();
});

it('niega el catálogo a los roles sin catalogs.view', function (SystemRole $role) {
    actingAsRole($role);

    $this->get(route('catalogs.units-of-measure.index'))->assertForbidden();
})->with([SystemRole::Production, SystemRole::Operator, SystemRole::Commercial]);

it('crea una unidad con el código en minúsculas y lo audita', function () {
    actingAsRole(SystemRole::SuperAdmin);

    $this->post(route('catalogs.units-of-measure.store'), uomPayload(['code' => '  CUN ']))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('catalogs.units-of-measure.index'));

    $unit = UnitOfMeasure::query()->where('code', 'cun')->sole();

    expect($unit->to_liter_conversion)->toBe('18.9271')
        ->and($unit->to_kg_conversion)->toBeNull();

    $log = Activity::query()->where('subject_type', UnitOfMeasure::class)->where('subject_id', $unit->id)->sole();

    expect($log->log_name)->toBe('unidades_medida')
        ->and($log->description)->toBe('Unidad de medida "cun" creada');
});

it('valida los campos de la unidad', function (array $overrides, string $field) {
    actingAsRole(SystemRole::SuperAdmin);
    UnitOfMeasure::factory()->create(['code' => 'kg']);

    $this->post(route('catalogs.units-of-measure.store'), uomPayload($overrides))
        ->assertSessionHasErrors($field);
})->with([
    'código repetido, aunque cambien las mayúsculas' => [['code' => 'KG'], 'code'],
    'sin nombre' => [['name' => ''], 'name'],
    'sin símbolo' => [['symbol' => ''], 'symbol'],
    'equivalencia en cero' => [['to_liter_conversion' => '0'], 'to_liter_conversion'],
    'equivalencia negativa' => [['to_liter_conversion' => '-1'], 'to_liter_conversion'],
    'más de 4 decimales' => [['to_liter_conversion' => '1.00001'], 'to_liter_conversion'],
    'por encima del tope de la columna' => [['to_liter_conversion' => '1000000'], 'to_liter_conversion'],
    'peso y volumen a la vez' => [['to_kg_conversion' => '1', 'to_liter_conversion' => '1'], 'to_kg_conversion'],
]);

it('admite una unidad que no se convierte', function () {
    actingAsRole(SystemRole::SuperAdmin);

    $this->post(route('catalogs.units-of-measure.store'), uomPayload(['code' => 'u', 'to_liter_conversion' => '']))
        ->assertSessionHasNoErrors();

    expect(UnitOfMeasure::query()->where('code', 'u')->sole()->to_liter_conversion)->toBeNull();
});

it('edita nombre, símbolo y estado de una unidad en uso sin pedir confirmación', function () {
    actingAsRole(SystemRole::SuperAdmin);
    $unit = unitInUse();

    $this->put(route('catalogs.units-of-measure.update', $unit), uomPayload([
        'code' => 'gl',
        'name' => 'Galón americano',
        'symbol' => 'gal',
        'to_liter_conversion' => '3.7854',
        'is_active' => false,
    ]))->assertSessionHasNoErrors()->assertRedirect(route('catalogs.units-of-measure.index'));

    expect($unit->fresh())
        ->name->toBe('Galón americano')
        ->symbol->toBe('gal')
        ->is_active->toBeFalse();
});

it('no toma «1.5000» y «1.50» como un cambio de equivalencia', function () {
    actingAsRole(SystemRole::SuperAdmin);
    $unit = unitInUse('1.5');

    $this->put(route('catalogs.units-of-measure.update', $unit), uomPayload(['code' => 'gl', 'to_liter_conversion' => '1.50']))
        ->assertSessionHasNoErrors();
});

it('exige confirmar el cambio de equivalencia de una unidad en uso y lo audita con el valor anterior', function () {
    actingAsRole(SystemRole::SuperAdmin);
    $unit = unitInUse('3.7854');
    $payload = uomPayload(['code' => 'gl', 'to_liter_conversion' => '3.8']);

    $this->put(route('catalogs.units-of-measure.update', $unit), $payload)
        ->assertSessionHasErrors('confirm_factor_change');
    expect($unit->fresh()->to_liter_conversion)->toBe('3.7854');

    $this->put(route('catalogs.units-of-measure.update', $unit), [...$payload, 'confirm_factor_change' => true])
        ->assertSessionHasNoErrors();
    expect($unit->fresh()->to_liter_conversion)->toBe('3.8000');

    $log = Activity::query()
        ->where('subject_type', UnitOfMeasure::class)
        ->where('subject_id', $unit->id)
        ->where('event', 'updated')
        ->sole();

    expect($log->properties['old']['to_liter_conversion'])->toBe('3.7854')
        ->and($log->properties['attributes']['to_liter_conversion'])->toBe('3.8000');
});

it('exige confirmación también al pasar de volumen a peso o a no convertirse', function (array $factors) {
    actingAsRole(SystemRole::SuperAdmin);
    $unit = unitInUse();

    $this->put(route('catalogs.units-of-measure.update', $unit), uomPayload(['code' => 'gl', ...$factors]))
        ->assertSessionHasErrors('confirm_factor_change');
})->with([
    'a peso' => [['to_kg_conversion' => '3.7854', 'to_liter_conversion' => null]],
    'sin conversión' => [['to_kg_conversion' => null, 'to_liter_conversion' => null]],
]);

it('no pide confirmación si la unidad solo la usan productos o presentaciones', function () {
    actingAsRole(SystemRole::SuperAdmin);
    $unit = UnitOfMeasure::factory()->create(['code' => 'gl', 'to_liter_conversion' => '3.7854']);
    ProductVariant::factory()->create(['unit_of_measure_id' => $unit->id]);

    // Su unidad solo se muestra: no entra en ninguna conversión de fórmula.
    $this->put(route('catalogs.units-of-measure.update', $unit), uomPayload(['code' => 'gl', 'to_liter_conversion' => '3.8']))
        ->assertSessionHasNoErrors();
});

it('no cuenta como cambio una equivalencia que no viene en la petición', function () {
    actingAsRole(SystemRole::SuperAdmin);
    $unit = unitInUse('3.7854');
    $payload = uomPayload(['code' => 'gl']);
    unset($payload['to_kg_conversion'], $payload['to_liter_conversion']);

    $this->put(route('catalogs.units-of-measure.update', $unit), $payload)->assertSessionHasNoErrors();

    expect($unit->fresh()->to_liter_conversion)->toBe('3.7854');
});

it('conserva el estado de la unidad si la edición no lo envía', function () {
    actingAsRole(SystemRole::SuperAdmin);
    $unit = UnitOfMeasure::factory()->create(['code' => 'gl', 'is_active' => false, 'to_liter_conversion' => '3.7854']);
    $payload = uomPayload(['code' => 'gl', 'to_liter_conversion' => '3.7854']);
    unset($payload['is_active']);

    $this->put(route('catalogs.units-of-measure.update', $unit), $payload)->assertSessionHasNoErrors();

    expect($unit->fresh()->is_active)->toBeFalse();
});

it('responde con un error de validación, no con un error del servidor, si un campo llega como lista', function (string $field) {
    actingAsRole(SystemRole::SuperAdmin);

    $this->post(route('catalogs.units-of-measure.store'), uomPayload([$field => ['1']]))
        ->assertRedirect()
        ->assertSessionHasErrors($field);
})->with(['to_kg_conversion', 'to_liter_conversion', 'description']);

it('cambia la equivalencia de una unidad sin uso sin pedir confirmación', function () {
    actingAsRole(SystemRole::SuperAdmin);
    $unit = UnitOfMeasure::factory()->create(['code' => 'gl', 'to_liter_conversion' => '3.7854']);

    $this->put(route('catalogs.units-of-measure.update', $unit), uomPayload(['code' => 'gl', 'to_liter_conversion' => '3.8']))
        ->assertSessionHasNoErrors();

    expect($unit->fresh()->to_liter_conversion)->toBe('3.8000');
});

it('elimina una unidad que nunca se usó y la audita', function () {
    actingAsRole(SystemRole::SuperAdmin);
    $unit = UnitOfMeasure::factory()->create(['code' => 'tmp']);

    $this->delete(route('catalogs.units-of-measure.destroy', $unit))
        ->assertRedirect(route('catalogs.units-of-measure.index'));

    expect(UnitOfMeasure::query()->whereKey($unit->id)->exists())->toBeFalse()
        ->and(Activity::query()->where('log_name', 'unidades_medida')->where('event', 'deleted')->value('description'))
        ->toBe('Unidad de medida "tmp" eliminada');
});

it('no elimina una unidad en uso y propone desactivarla', function () {
    actingAsRole(SystemRole::SuperAdmin);
    $unit = unitInUse();

    $this->delete(route('catalogs.units-of-measure.destroy', $unit))
        ->assertSessionHas('error', 'La unidad de medida está en uso (materias primas, productos, presentaciones o fórmulas). Desactívala en su lugar.');

    expect(UnitOfMeasure::query()->whereKey($unit->id)->exists())->toBeTrue();
});

it('entrega a la edición cuánto se usa la unidad', function () {
    actingAsRole(SystemRole::SuperAdmin);
    $unit = unitInUse();

    $this->get(route('catalogs.units-of-measure.edit', $unit))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Settings/Catalogs/UnitsOfMeasure/Edit')
            ->where('unit.in_use', true)
            ->where('unit.usage.raw_materials', 1));
});
