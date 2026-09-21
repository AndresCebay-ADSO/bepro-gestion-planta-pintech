<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Las firmas viven en el disco privado y solo se sirven con sesión y la policy `viewSignature`.
 */
beforeEach(function () {
    Storage::fake('local');
    // Defensivo: la subida de firmas no debe escribir nada en el disco público.
    Storage::fake('public');

    $this->owner = userWithRole(SystemRole::Operator, ['signature_path' => 'signatures/firma.png']);
    Storage::disk('local')->put('signatures/firma.png', 'contenido-firma');
});

it('guarda en el disco privado la firma que sube un usuario, nunca en el público', function () {
    $user = userWithRole(SystemRole::Operator);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'signature' => UploadedFile::fake()->image('firma.png', 200, 50),
        ])
        ->assertSessionHasNoErrors();

    $path = $user->refresh()->signature_path;

    expect($path)->not->toBeNull()
        ->and(User::SIGNATURE_DISK)->toBe('local')
        ->and($user->signature_url)->toStartWith(route('users.signature', $user))
        ->and(Storage::disk('public')->allFiles())->toBe([]);
    Storage::disk('local')->assertExists($path);
});

it('exige iniciar sesión para ver una firma', function () {
    $this->get(route('users.signature', $this->owner))->assertRedirect(route('login'));
});

it('sirve la firma al propio usuario, a quien edita usuarios y a quien completa órdenes', function (?SystemRole $role) {
    $this->actingAs($role === null ? $this->owner : userWithRole($role))
        ->get(route('users.signature', $this->owner))
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=86400, private')
        ->assertStreamedContent('contenido-firma');
})->with([
    'el propio usuario' => [null],
    'admin (users.edit)' => [SystemRole::Admin],
    'producción (production_orders.complete)' => [SystemRole::Production],
]);

it('con users.edit solo ve la firma de quien puede gestionar, salvo que además complete órdenes', function () {
    $superAdmin = userWithRole(SystemRole::SuperAdmin, ['signature_path' => 'signatures/super.png']);
    Storage::disk('local')->put('signatures/super.png', 'firma-super');

    // Rol personalizado que edita usuarios pero no completa órdenes.
    $hr = User::factory()->create();
    $hr->givePermissionTo([Permission::DashboardView->value, Permission::UsersView->value, Permission::UsersEdit->value]);

    // Misma regla que update(): ve la firma de quien no tiene más permisos que él, no la del operador ni la del
    // SuperAdmin.
    $viewer = User::factory()->create(['signature_path' => 'signatures/consulta.png']);
    $viewer->givePermissionTo(Permission::DashboardView->value);
    Storage::disk('local')->put('signatures/consulta.png', 'firma-consulta');

    $this->actingAs($hr)->get(route('users.signature', $viewer))->assertOk();
    $this->actingAs($hr)->get(route('users.signature', $this->owner))->assertForbidden();
    $this->actingAs($hr)->get(route('users.signature', $superAdmin))->assertForbidden();

    // Admin también completa órdenes: el SuperAdmin puede firmar certificados y debe ver su firma para elegirlo.
    $this->actingAs(userWithRole(SystemRole::Admin))->get(route('users.signature', $superAdmin))->assertOk();
});

it('niega la firma a quien no la necesita', function () {
    userWithRole(SystemRole::Admin);
    $commercial = userWithRole(SystemRole::Commercial);

    expect($commercial->can(Permission::UsersEdit->value))->toBeFalse()
        ->and($commercial->can(Permission::ProductionOrdersComplete->value))->toBeFalse();

    $this->actingAs($commercial)->get(route('users.signature', $this->owner))->assertForbidden();
});

it('responde 404 si el usuario no tiene firma', function () {
    $withoutSignature = userWithRole(SystemRole::Operator);

    $this->actingAs($withoutSignature)->get(route('users.signature', $withoutSignature))->assertNotFound();
});
