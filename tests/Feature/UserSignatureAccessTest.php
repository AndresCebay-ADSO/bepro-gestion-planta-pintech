<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Las firmas viven en el disco privado y solo se sirven con sesión y la policy `viewSignature`.
 */
beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');

    $this->owner = userWithRole(SystemRole::Operator, ['signature_path' => 'signatures/firma.png']);
    Storage::disk('local')->put('signatures/firma.png', 'contenido-firma');
});

it('no guarda las firmas en el disco público', function () {
    expect(User::SIGNATURE_DISK)->toBe('local')
        ->and($this->owner->signature_url)->toStartWith(route('users.signature', $this->owner));

    Storage::disk('public')->assertMissing('signatures/firma.png');
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
