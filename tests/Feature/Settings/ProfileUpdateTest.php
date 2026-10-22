<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\SignatureOptimizerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email_verified_at)->not->toBeNull();
});

// Auditoría 3.6, PR 10 (D-USR1): el correo solo lo cambia un administrador desde Usuarios. Cambiarlo desde el perfil dejaba
// al usuario sin verificar y fuera del sistema (las rutas exigen `verified`) hasta abrir el enlace del correo nuevo.
test('a user cannot change their own email from the profile', function () {
    $user = User::factory()->create();
    $original = $user->email;

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Otro Nombre',
            'email' => 'nuevo-correo@example.com',
        ]);

    $response->assertSessionHasErrors(['email' => 'Para cambiar tu correo, pídelo a un administrador.']);

    $user->refresh();
    expect($user->email)->toBe($original)
        ->and($user->name)->not->toBe('Otro Nombre')
        ->and($user->email_verified_at)->not->toBeNull();
});

test('the profile accepts the same email in other letter case and keeps it verified', function () {
    $user = User::factory()->create();
    $user->forceFill(['email' => 'Mixto@Example.com'])->save();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Nuevo', 'email' => 'MIXTO@EXAMPLE.COM'])
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->email)->toBe('mixto@example.com')
        ->and($user->email_verified_at)->not->toBeNull();
});

test('job_title can be updated in profile', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'job_title' => 'Gerente de Producción',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->job_title)->toBe('Gerente de Producción');
});

test('job_title can be cleared', function () {
    $user = User::factory()->create(['job_title' => 'Gerente']);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'job_title' => '',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->job_title)->toBeNull();
});

test('phone can be updated in profile', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '3009876543',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->phone)->toBe('3009876543');
});

test('phone can be cleared', function () {
    $user = User::factory()->create(['phone' => '3009876543']);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->phone)->toBeNull();
});

test('signature can be uploaded', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('firma.png', 200, 50);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'signature' => $file,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->signature_path)->not->toBeNull();
    Storage::disk('local')->assertExists($user->signature_path);
});

test('signature can be removed', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $path = Storage::disk('local')->putFile('signatures', UploadedFile::fake()->image('firma.png'));
    $user->update(['signature_path' => $path]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'remove_signature' => true,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->signature_path)->toBeNull();
    Storage::disk('local')->assertMissing($path);
});

test('signature upload rejects invalid file type', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('document.pdf', 100);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'signature' => $file,
        ]);

    $response->assertSessionHasErrors('signature');
});

test('signature upload rejects oversized file', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('firma.png')->size(2048); // > 1024 KB

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'signature' => $file,
        ]);

    $response->assertSessionHasErrors('signature');
});

test('signature upload rejects image with excessive dimensions', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('huge.png', 5000, 5000);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'signature' => $file,
        ]);

    $response->assertSessionHasErrors('signature');
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('profile update fails gracefully when signature optimizer throws validation exception', function () {
    Storage::fake('local');

    $user = User::factory()->create([
        'signature_path' => 'signatures/keep_me.png',
    ]);
    Storage::disk('local')->put('signatures/keep_me.png', 'existing-signature');

    $this->mock(SignatureOptimizerService::class, function ($mock) {
        $mock->shouldReceive('optimizeAndStore')
            ->once()
            ->andThrow(ValidationException::withMessages([
                'signature' => 'No se pudo procesar la imagen de firma.',
            ]));
    });

    $file = UploadedFile::fake()->image('bad.png', 200, 100);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'signature' => $file,
        ]);

    $response->assertSessionHasErrors('signature');

    $user->refresh();
    expect($user->signature_path)->toBe('signatures/keep_me.png');
    Storage::disk('local')->assertExists('signatures/keep_me.png');
});

test('user with pre-existing uppercase email can update profile without email error', function () {
    $user = User::factory()->create(['email' => 'Admin@Company.com']);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Nuevo Nombre',
            'email' => $user->email,
            'phone' => '3001234567',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();
    expect($user->name)->toBe('Nuevo Nombre')
        ->and($user->phone)->toBe('3001234567')
        ->and($user->email)->toBe('admin@company.com');
});

test('users cannot delete their own account', function () {
    $user = User::factory()->create();

    expect(Route::has('profile.destroy'))->toBeFalse();

    $this->actingAs($user)
        ->delete('/settings/profile')
        ->assertMethodNotAllowed();

    expect($user->fresh())->not->toBeNull();
});
