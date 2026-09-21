<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\QrDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * La imagen original de la firma vive en el disco privado y solo se sirve con sesión y la policy `viewSignature`.
 * El certificado PDF no depende de esta regla: lleva la firma incrustada.
 */
beforeEach(function () {
    Storage::fake('local');
    // Defensivo: la subida de firmas no debe escribir nada en el disco público.
    Storage::fake('public');

    // Firmante de certificados (production_orders.complete) y alguien que no firma.
    $this->signer = userWithRole(SystemRole::Production, ['signature_path' => 'signatures/firmante.png']);
    $this->nonSigner = userWithRole(SystemRole::Operator, ['signature_path' => 'signatures/operador.png']);
    Storage::disk('local')->put('signatures/firmante.png', 'firma-firmante');
    Storage::disk('local')->put('signatures/operador.png', 'firma-operador');
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
        ->and($user->signature_url)->toBe(route('users.signature', $user))
        ->and(Storage::disk('public')->allFiles())->toBe([]);
    Storage::disk('local')->assertExists($path);
});

it('exige iniciar sesión para ver una firma', function () {
    $this->get(route('users.signature', $this->signer))->assertRedirect(route('login'));
});

it('sirve la firma según quién la pide y de quién es', function (string $viewer, string $owner, bool $allowed) {
    $viewerUser = match ($viewer) {
        'self' => $this->{$owner},
        default => userWithRole(SystemRole::from($viewer)),
    };

    $response = $this->actingAs($viewerUser)->get(route('users.signature', $this->{$owner}));

    if ($allowed) {
        $response->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    } else {
        $response->assertForbidden();
    }
})->with([
    'el propio usuario ve la suya' => ['self', 'nonSigner', true],
    'Admin ve la de quien puede editar' => [SystemRole::Admin->value, 'nonSigner', true],
    'Producción no ve la de quien no firma' => [SystemRole::Production->value, 'nonSigner', false],
    'Operador ve la del firmante (ficha de la orden)' => [SystemRole::Operator->value, 'signer', true],
    'Producción ve la del firmante' => [SystemRole::Production->value, 'signer', true],
    'Comercial no ve órdenes ni firmas' => [SystemRole::Commercial->value, 'signer', false],
]);

it('con users.edit y sin ver órdenes solo ve la firma de quien puede gestionar', function () {
    $superAdmin = userWithRole(SystemRole::SuperAdmin, ['signature_path' => 'signatures/super.png']);
    Storage::disk('local')->put('signatures/super.png', 'firma-super');

    // Rol personalizado que edita usuarios pero no ve órdenes de producción.
    $hr = User::factory()->create();
    $hr->givePermissionTo([Permission::DashboardView->value, Permission::UsersView->value, Permission::UsersEdit->value]);

    $viewer = User::factory()->create(['signature_path' => 'signatures/consulta.png']);
    $viewer->givePermissionTo(Permission::DashboardView->value);
    Storage::disk('local')->put('signatures/consulta.png', 'firma-consulta');

    $this->actingAs($hr)->get(route('users.signature', $viewer))->assertOk();
    $this->actingAs($hr)->get(route('users.signature', $this->nonSigner))->assertForbidden();
    $this->actingAs($hr)->get(route('users.signature', $superAdmin))->assertForbidden();

    // Admin ve órdenes y el SuperAdmin puede firmar certificados: su firma aparece en la ficha de la orden.
    $this->actingAs(userWithRole(SystemRole::Admin))->get(route('users.signature', $superAdmin))->assertOk();
});

it('responde 404 si el usuario no tiene firma o la ruta está vacía', function (?string $path) {
    $user = userWithRole(SystemRole::Operator, ['signature_path' => $path]);

    $this->actingAs($user)->get(route('users.signature', $user))->assertNotFound();
})->with([null, '']);

it('no cambia el acceso al certificado: Producción descarga el que firmó la Admin', function () {
    $admin = userWithRole(SystemRole::Admin, ['signature_path' => 'signatures/admin.png']);
    $certificate = QrDocument::factory()->create(['uploaded_by' => $admin->id]);
    Storage::disk('local')->put($certificate->file_path, '%PDF-certificado-con-firma-incrustada');

    $this->actingAs($this->signer)
        ->get(route('qr-codes.documents.download', [$certificate->qr_code_id, $certificate]))
        ->assertOk()
        ->assertStreamedContent('%PDF-certificado-con-firma-incrustada');
});
