<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\InventoryBatch;
use App\Models\Supplier;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;

// Proveedores, en Configuración → Catálogos. Los ve quien tiene `catalogs.view` y los gestionan Admin y SuperAdmin
// (`suppliers.manage`), porque el Admin es quien registra las entradas de materia prima.

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function supplierPayload(array $overrides = []): array
{
    return array_replace([
        'name' => 'Químicos del Valle',
        'nit' => '900123456-7',
        'contact_name' => 'Ana Pérez',
        'phone' => '3101234567',
        'email' => 'ventas@quimicos.test',
        'notes' => 'Entrega los martes.',
        'is_active' => true,
    ], $overrides);
}

it('deja crear, editar y desactivar a Admin y SuperAdmin', function (SystemRole $role) {
    actingAsRole($role);

    $this->get(route('catalogs.suppliers.create'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Settings/Catalogs/Suppliers/Create'));

    $this->post(route('catalogs.suppliers.store'), supplierPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('catalogs.suppliers.index'));

    $supplier = Supplier::query()->sole();
    expect($supplier)
        ->name->toBe('Químicos del Valle')
        ->nit->toBe('900123456-7')
        ->contact_name->toBe('Ana Pérez')
        ->phone->toBe('3101234567')
        ->email->toBe('ventas@quimicos.test')
        ->notes->toBe('Entrega los martes.')
        ->is_active->toBeTrue();

    $this->get(route('catalogs.suppliers.edit', $supplier))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Settings/Catalogs/Suppliers/Edit')
            ->where('supplier.notes', 'Entrega los martes.'));

    $this->put(route('catalogs.suppliers.update', $supplier), supplierPayload(['name' => 'Químicos del Cauca', 'is_active' => false]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('catalogs.suppliers.index'));

    expect($supplier->fresh())
        ->name->toBe('Químicos del Cauca')
        ->is_active->toBeFalse();
})->with([SystemRole::SuperAdmin, SystemRole::Admin]);

it('niega el catálogo a los demás roles', function (SystemRole $role) {
    actingAsRole($role);
    $supplier = Supplier::factory()->create();

    $this->get(route('catalogs.suppliers.index'))->assertForbidden();
    $this->get(route('catalogs.suppliers.create'))->assertForbidden();
    $this->post(route('catalogs.suppliers.store'), supplierPayload())->assertForbidden();
    $this->get(route('catalogs.suppliers.edit', $supplier))->assertForbidden();
    $this->put(route('catalogs.suppliers.update', $supplier), supplierPayload())->assertForbidden();
    $this->delete(route('catalogs.suppliers.destroy', $supplier))->assertForbidden();
})->with([SystemRole::Production, SystemRole::Operator, SystemRole::Commercial]);

it('deja ver el listado con catalogs.view, pero no gestionar sin suppliers.manage', function () {
    $this->actingAs(userWithPermissions([Permission::CatalogsView]));
    $supplier = Supplier::factory()->create();

    $this->get(route('catalogs.suppliers.index'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Settings/Catalogs/Suppliers/Index')
            ->where('can.create', false)
            ->where('suppliers.data.0.can.update', false)
            ->where('suppliers.data.0.can.delete', false));

    $this->post(route('catalogs.suppliers.store'), supplierPayload())->assertForbidden();
    $this->put(route('catalogs.suppliers.update', $supplier), supplierPayload())->assertForbidden();
    $this->delete(route('catalogs.suppliers.destroy', $supplier))->assertForbidden();
});

it('hace depender suppliers.manage de catalogs.view y no lo reserva a SuperAdmin', function () {
    expect(Permission::SuppliersManage->dependencies())->toBe([Permission::CatalogsView])
        ->and(Permission::SuppliersManage->isReserved())->toBeFalse()
        ->and(Permission::SuppliersManage->defaultRoles())->toBe([SystemRole::Admin]);
});

it('solo exige el nombre', function () {
    actingAsRole(SystemRole::Admin);

    $this->post(route('catalogs.suppliers.store'), ['name' => 'Solo nombre'])->assertSessionHasNoErrors();

    expect(Supplier::query()->sole())
        ->name->toBe('Solo nombre')
        ->nit->toBeNull()
        ->contact_name->toBeNull()
        ->phone->toBeNull()
        ->email->toBeNull()
        ->notes->toBeNull()
        ->is_active->toBeTrue();

    $this->post(route('catalogs.suppliers.store'), supplierPayload(['name' => '']))
        ->assertSessionHasErrors('name');
});

it('deja varios proveedores sin NIT', function () {
    actingAsRole(SystemRole::Admin);

    $this->post(route('catalogs.suppliers.store'), ['name' => 'Uno', 'nit' => ''])->assertSessionHasNoErrors();
    $this->post(route('catalogs.suppliers.store'), ['name' => 'Dos', 'nit' => ''])->assertSessionHasNoErrors();

    expect(Supplier::query()->whereNull('nit')->count())->toBe(2);
});

it('no repite el nombre sin distinguir mayúsculas ni el NIT', function (array $overrides, string $field) {
    actingAsRole(SystemRole::Admin);
    Supplier::factory()->create(['name' => 'Químicos del Valle', 'nit' => '900123456-7']);

    $this->post(route('catalogs.suppliers.store'), supplierPayload(['name' => 'Otro', 'nit' => '111', ...$overrides]))
        ->assertSessionHasErrors($field);

    expect(Supplier::query()->count())->toBe(1);
})->with([
    'nombre igual' => [['name' => 'Químicos del Valle'], 'name'],
    'nombre en minúsculas' => [['name' => 'químicos del valle'], 'name'],
    'NIT igual' => [['nit' => '900123456-7'], 'nit'],
]);

it('al editar conserva su propio nombre y NIT, pero no toma los de otro', function () {
    actingAsRole(SystemRole::Admin);
    $supplier = Supplier::factory()->create(['name' => 'Químicos del Valle', 'nit' => '900123456-7']);
    Supplier::factory()->create(['name' => 'Otro', 'nit' => '111']);

    $this->put(route('catalogs.suppliers.update', $supplier), supplierPayload())->assertSessionHasNoErrors();
    $this->put(route('catalogs.suppliers.update', $supplier), supplierPayload(['name' => 'QUÍMICOS DEL VALLE']))->assertSessionHasNoErrors();

    $this->put(route('catalogs.suppliers.update', $supplier), supplierPayload(['name' => 'otro']))->assertSessionHasErrors('name');
    $this->put(route('catalogs.suppliers.update', $supplier), supplierPayload(['nit' => '111']))->assertSessionHasErrors('nit');
});

it('al editar sin enviar is_active conserva el estado', function () {
    actingAsRole(SystemRole::Admin);
    $supplier = Supplier::factory()->inactive()->create();

    $this->put(route('catalogs.suppliers.update', $supplier), ['name' => $supplier->name])->assertSessionHasNoErrors();

    expect($supplier->fresh()->is_active)->toBeFalse();
});

it('acepta justo lo que cabe en cada columna', function () {
    actingAsRole(SystemRole::Admin);

    $this->post(route('catalogs.suppliers.store'), [
        'name' => str_repeat('n', 150),
        'nit' => str_repeat('1', 20),
        'contact_name' => str_repeat('c', 255),
        'phone' => str_repeat('3', 20),
        'email' => str_repeat('e', 242).'@example.test',
        'notes' => str_repeat('o', 2000),
    ])->assertSessionHasNoErrors();

    expect(Supplier::query()->count())->toBe(1);
});

it('rechaza lo que no cabe o no tiene el formato', function (string $field, string $value) {
    actingAsRole(SystemRole::Admin);

    $this->post(route('catalogs.suppliers.store'), supplierPayload([$field => $value]))
        ->assertSessionHasErrors($field);

    expect(Supplier::query()->count())->toBe(0);
})->with([
    'nombre largo' => ['name', str_repeat('n', 151)],
    'NIT largo' => ['nit', str_repeat('1', 21)],
    'contacto largo' => ['contact_name', str_repeat('c', 256)],
    'teléfono largo' => ['phone', str_repeat('3', 21)],
    'teléfono con letras' => ['phone', 'llamar al 31012'],
    'correo sin formato' => ['email', 'no-es-correo'],
    'correo largo' => ['email', str_repeat('e', 243).'@example.test'],
    'notas largas' => ['notes', str_repeat('o', 2001)],
]);

it('elimina un proveedor sin lotes y lo deja en la auditoría', function () {
    actingAsRole(SystemRole::Admin);
    $supplier = Supplier::factory()->create(['name' => 'Sin uso']);

    $this->delete(route('catalogs.suppliers.destroy', $supplier))
        ->assertRedirect(route('catalogs.suppliers.index'));

    expect(Supplier::query()->find($supplier->id))->toBeNull()
        ->and(Activity::query()->where('subject_type', Supplier::class)->where('subject_id', $supplier->id)->where('event', 'deleted')->value('description'))
        ->toBe('Proveedor "Sin uso" eliminado');
});

it('no elimina un proveedor con lotes: pide desactivarlo', function () {
    actingAsRole(SystemRole::Admin);
    $supplier = Supplier::factory()->create();
    InventoryBatch::factory()->create(['supplier_id' => $supplier->id]);

    $this->from(route('catalogs.suppliers.index'))
        ->delete(route('catalogs.suppliers.destroy', $supplier))
        ->assertRedirect(route('catalogs.suppliers.index'))
        ->assertInertiaFlash('error', 'El proveedor tiene lotes registrados. Desactívalo en su lugar.');

    expect(Supplier::query()->find($supplier->id))->not->toBeNull();
});

it('audita los datos del proveedor con su propio log', function () {
    actingAsRole(SystemRole::Admin);

    $this->post(route('catalogs.suppliers.store'), supplierPayload());

    $supplier = Supplier::query()->sole();
    $created = Activity::query()->where('subject_type', Supplier::class)->where('subject_id', $supplier->id)->where('event', 'created')->sole();

    expect($created->log_name)->toBe('proveedores')
        ->and($created->description)->toBe('Proveedor "Químicos del Valle" creado')
        ->and($created->properties['attributes'])->toMatchArray([
            'name' => 'Químicos del Valle',
            'nit' => '900123456-7',
            'contact_name' => 'Ana Pérez',
            'phone' => '3101234567',
            'email' => 'ventas@quimicos.test',
            'notes' => 'Entrega los martes.',
            'is_active' => true,
        ]);

    $this->put(route('catalogs.suppliers.update', $supplier), supplierPayload(['is_active' => false]));

    $updated = Activity::query()->where('subject_type', Supplier::class)->where('subject_id', $supplier->id)->where('event', 'updated')->sole();
    expect($updated->properties['old']['is_active'])->toBeTrue()
        ->and($updated->properties['attributes']['is_active'])->toBeFalse();
});

it('lista por nombre e indica si está en uso', function () {
    actingAsRole(SystemRole::Admin);
    // Ni por id ascendente ni descendente quedarían en orden alfabético.
    $used = Supplier::factory()->create(['name' => 'Beta']);
    Supplier::factory()->create(['name' => 'Alfa']);
    Supplier::factory()->create(['name' => 'Gamma']);
    InventoryBatch::factory()->create(['supplier_id' => $used->id]);

    $this->get(route('catalogs.suppliers.index'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('can.create', true)
            ->where('suppliers.data.0.name', 'Alfa')
            ->where('suppliers.data.0.in_use', false)
            ->where('suppliers.data.0.can.update', true)
            ->where('suppliers.data.1.name', 'Beta')
            ->where('suppliers.data.1.in_use', true)
            ->where('suppliers.data.2.name', 'Gamma')
            ->missing('suppliers.data.0.notes'));
});

it('busca en nombre, NIT y contacto, y filtra por estado', function (array $query, string $expected) {
    actingAsRole(SystemRole::Admin);
    Supplier::factory()->create(['name' => 'Químicos del Valle', 'nit' => '900111', 'contact_name' => 'Ana Pérez']);
    Supplier::factory()->inactive()->create(['name' => 'Resinas Andinas', 'nit' => '800222', 'contact_name' => 'Luis Gómez']);

    $this->get(route('catalogs.suppliers.index', $query))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('suppliers.data', 1)
            ->where('suppliers.data.0.name', $expected));
})->with([
    'nombre' => [['search' => 'químicos'], 'Químicos del Valle'],
    'NIT' => [['search' => '800222'], 'Resinas Andinas'],
    'contacto' => [['search' => 'gómez'], 'Resinas Andinas'],
    'activos' => [['status' => 'active'], 'Químicos del Valle'],
    'inactivos' => [['status' => 'inactive'], 'Resinas Andinas'],
]);
