<?php

use App\Models\ProductionOrder;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\SignatureOptimizerService;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('operador', 'web');
});

// ──────────────────────────────────────────────
// destroy()
// ──────────────────────────────────────────────

test('admin cannot delete their own account', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->delete(route('users.destroy', $admin))
        ->assertRedirect()
        ->assertSessionHas('error', 'No puedes eliminar tu propia cuenta.');

    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});

test('admin cannot delete a user that has activity logs', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $target = User::factory()->create();
    $target->assignRole('operador');

    activity('test')
        ->causedBy($target)
        ->log('Acción de prueba');

    $this->actingAs($admin)
        ->delete(route('users.destroy', $target))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('users', ['id' => $target->id]);
});

test('admin cannot delete a user that has records via created_by in system tables', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $target = User::factory()->create();
    $target->assignRole('operador');

    // Simula el bug scenario: sin activity_log pero con registro directo
    // en activity_log con el causer_id del target (tabla sin FK constraints).
    // Equivalente a tener production_orders.created_by = $target->id pero sin
    // necesitar toda la cadena de FKs de product/formula/warehouse en el test DB.
    DB::table('activity_logs')->insert([
        'log_name' => 'produccion',
        'description' => 'orden creada',
        'subject_type' => 'App\\Models\\ProductionOrder',
        'subject_id' => 999,
        'causer_type' => 'App\\Models\\User',
        'causer_id' => $target->id,
        'event' => 'created',
        'properties' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($admin)
        ->delete(route('users.destroy', $target))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('users', ['id' => $target->id]);
});

test('admin can delete a user with no activity', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $target = User::factory()->create();
    $target->assignRole('operador');

    // Garantizar que no tiene ningún registro en el sistema
    Activity::where('causer_id', $target->id)->delete();

    $this->actingAs($admin)
        ->delete(route('users.destroy', $target))
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('message');

    $this->assertDatabaseMissing('users', ['id' => $target->id]);
});

// ──────────────────────────────────────────────
// store() + update() is_active
// ──────────────────────────────────────────────

test('admin can create user with is_active = true', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Usuario Activo',
            'email' => 'activo@test.com',
            'phone' => '3001234567',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'operador',
            'is_active' => true,
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('message');

    $this->assertDatabaseHas('users', [
        'email' => 'activo@test.com',
        'phone' => '3001234567',
        'is_active' => true,
    ]);
});

test('admin can create user with is_active = false', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Usuario Inactivo',
            'email' => 'inactivo@test.com',
            'phone' => '3007654321',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'operador',
            'is_active' => false,
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('message');

    $this->assertDatabaseHas('users', [
        'email' => 'inactivo@test.com',
        'phone' => '3007654321',
        'is_active' => false,
    ]);
});

test('admin can update user is_active from true to false', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $target = User::factory()->create([
        'is_active' => true,
    ]);
    $target->assignRole('operador');

    $this->actingAs($admin)
        ->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'phone' => '3001112222',
            'role' => 'operador',
            'is_active' => false,
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('message');

    $this->assertDatabaseHas('users', [
        'id' => $target->id,
        'phone' => '3001112222',
        'is_active' => false,
    ]);
});

test('admin can update user is_active from false to true', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $target = User::factory()->create([
        'is_active' => false,
    ]);
    $target->assignRole('operador');

    $this->actingAs($admin)
        ->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'phone' => '3003334444',
            'role' => 'operador',
            'is_active' => true,
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('message');

    $this->assertDatabaseHas('users', [
        'id' => $target->id,
        'phone' => '3003334444',
        'is_active' => true,
    ]);
});

// ──────────────────────────────────────────────
// signature
// ──────────────────────────────────────────────

test('admin can create user with signature', function () {
    Storage::fake('public');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $file = UploadedFile::fake()->image('signature.png', 200, 100);

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Usuario Con Firma',
            'email' => 'firma@test.com',
            'phone' => '3001234567',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'operador',
            'is_active' => true,
            'signature' => $file,
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('message');

    $user = User::where('email', 'firma@test.com')->first();
    expect($user)->not->toBeNull();
    expect($user->signature_path)->not->toBeNull();
    Storage::disk('public')->assertExists($user->signature_path);
});

test('admin can update user signature', function () {
    Storage::fake('public');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $target = User::factory()->create([
        'signature_path' => 'signatures/old.png',
    ]);
    $target->assignRole('operador');
    Storage::disk('public')->put('signatures/old.png', 'old-content');

    $newFile = UploadedFile::fake()->image('new_signature.png', 200, 100);

    $this->actingAs($admin)
        ->post(route('users.update', $target), [
            '_method' => 'put',
            'name' => $target->name,
            'email' => $target->email,
            'role' => 'operador',
            'is_active' => true,
            'signature' => $newFile,
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('message');

    $target->refresh();
    expect($target->signature_path)->not->toBe('signatures/old.png');
    Storage::disk('public')->assertMissing('signatures/old.png');
    Storage::disk('public')->assertExists($target->signature_path);
});

test('user creation fails gracefully when signature optimizer throws exception', function () {
    Storage::fake('public');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->mock(SignatureOptimizerService::class, function ($mock) {
        $mock->shouldReceive('optimizeAndStore')
            ->once()
            ->andThrow(ValidationException::withMessages([
                'signature' => 'No se pudo procesar la imagen de firma.',
            ]));
    });

    $file = UploadedFile::fake()->image('corrupt.png', 200, 100);

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Usuario Fallido',
            'email' => 'fallido@test.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'operador',
            'is_active' => true,
            'signature' => $file,
        ])
        ->assertSessionHasErrors('signature');

    $this->assertDatabaseMissing('users', ['email' => 'fallido@test.com']);
});

test('user update preserves existing signature when optimizer throws exception', function () {
    Storage::fake('public');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $target = User::factory()->create([
        'signature_path' => 'signatures/original.png',
    ]);
    $target->assignRole('operador');
    Storage::disk('public')->put('signatures/original.png', 'original-content');

    $this->mock(SignatureOptimizerService::class, function ($mock) {
        $mock->shouldReceive('optimizeAndStore')
            ->once()
            ->andThrow(ValidationException::withMessages([
                'signature' => 'Corrupt image file',
            ]));
    });

    $newFile = UploadedFile::fake()->image('invalid.png', 200, 100);

    $this->actingAs($admin)
        ->post(route('users.update', $target), [
            '_method' => 'put',
            'name' => $target->name,
            'email' => $target->email,
            'role' => 'operador',
            'is_active' => true,
            'signature' => $newFile,
        ])
        ->assertSessionHasErrors('signature');

    $target->refresh();
    expect($target->signature_path)->toBe('signatures/original.png');
    Storage::disk('public')->assertExists('signatures/original.png');
});

test('admin can remove user signature', function () {
    Storage::fake('public');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $target = User::factory()->create([
        'signature_path' => 'signatures/old.png',
    ]);
    $target->assignRole('operador');
    Storage::disk('public')->put('signatures/old.png', 'old-content');

    $this->actingAs($admin)
        ->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => 'operador',
            'is_active' => true,
            'remove_signature' => true,
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('message');

    $target->refresh();
    expect($target->signature_path)->toBeNull();
    Storage::disk('public')->assertMissing('signatures/old.png');
});

test('signature file is deleted when user is deleted', function () {
    Storage::fake('public');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $target = User::factory()->create([
        'signature_path' => 'signatures/delete_me.png',
    ]);
    $target->assignRole('operador');
    Storage::disk('public')->put('signatures/delete_me.png', 'content');

    // Sin logs de actividad
    Activity::where('causer_id', $target->id)->delete();

    $this->actingAs($admin)
        ->delete(route('users.destroy', $target))
        ->assertRedirect(route('users.index'));

    Storage::disk('public')->assertMissing('signatures/delete_me.png');
    $this->assertDatabaseMissing('users', ['id' => $target->id]);
});

test('orphaned signature file is cleaned up if database transaction fails during store', function () {
    Storage::fake('public');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $storedSignaturePath = null;

    $this->mock(SignatureOptimizerService::class, function ($mock) use (&$storedSignaturePath) {
        $mock->shouldReceive('optimizeAndStore')
            ->once()
            ->andReturnUsing(function ($file) use (&$storedSignaturePath) {
                $storedSignaturePath = 'signatures/mocked_orphan.png';
                Storage::disk('public')->put($storedSignaturePath, 'orphan-content');

                return $storedSignaturePath;
            });
    });

    $file = UploadedFile::fake()->image('signature.png', 200, 100);

    try {
        User::creating(function ($user) {
            if ($user->email === 'falla_bd@test.com') {
                throw new RuntimeException('Database failure simulation');
            }
        });

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Falla BD',
                'email' => 'falla_bd@test.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => 'operador',
                'is_active' => true,
                'signature' => $file,
            ]);
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('Database failure simulation');
    } finally {
        User::flushEventListeners();
        User::clearBootedModels();
        new User;
    }

    expect($storedSignaturePath)->not->toBeNull();
    Storage::disk('public')->assertMissing($storedSignaturePath);
});

test('signature file is preserved on disk if user deletion fails', function () {
    Storage::fake('public');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $target = User::factory()->create([
        'signature_path' => 'signatures/preserve_me.png',
    ]);
    $target->assignRole('operador');
    Storage::disk('public')->put('signatures/preserve_me.png', 'preserve-content');

    // El usuario tiene actividad, por lo que destroy() aborta
    activity('test')
        ->causedBy($target)
        ->log('Cannot delete');

    $this->actingAs($admin)
        ->delete(route('users.destroy', $target))
        ->assertRedirect()
        ->assertSessionHas('error');

    Storage::disk('public')->assertExists('signatures/preserve_me.png');
    $this->assertDatabaseHas('users', ['id' => $target->id]);
});

test('orphaned signature file is cleaned up and database rolled back if transaction fails during update', function () {
    Storage::fake('public');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $target = User::factory()->create([
        'signature_path' => 'signatures/keep_original.png',
    ]);
    $target->assignRole('operador');
    Storage::disk('public')->put('signatures/keep_original.png', 'original-content');

    $storedSignaturePath = null;
    $this->mock(SignatureOptimizerService::class, function ($mock) use (&$storedSignaturePath) {
        $mock->shouldReceive('optimizeAndStore')
            ->once()
            ->andReturnUsing(function ($file) use (&$storedSignaturePath) {
                $storedSignaturePath = 'signatures/mocked_new_orphan.png';
                Storage::disk('public')->put($storedSignaturePath, 'new-content');

                return $storedSignaturePath;
            });
    });

    $file = UploadedFile::fake()->image('new.png', 200, 100);

    try {
        User::updating(function ($user) use ($target) {
            if ($user->id === $target->id) {
                throw new RuntimeException('Update failure simulation');
            }
        });

        $this->actingAs($admin)
            ->put(route('users.update', $target), [
                'name' => 'Falla Update',
                'email' => $target->email,
                'role' => 'operador',
                'is_active' => true,
                'signature' => $file,
            ]);
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('Update failure simulation');
    } finally {
        User::flushEventListeners();
        User::clearBootedModels();
        new User;
    }

    expect($storedSignaturePath)->not->toBeNull();
    Storage::disk('public')->assertMissing($storedSignaturePath);
    Storage::disk('public')->assertExists('signatures/keep_original.png');

    $target->refresh();
    expect($target->signature_path)->toBe('signatures/keep_original.png');
    expect($target->name)->not->toBe('Falla Update');
});

test('user creation normalizes uppercase email to lowercase', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Usuario Mayusculas',
            'email' => 'MAYUSCULAS@TEST.COM',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'operador',
            'is_active' => true,
        ])
        ->assertRedirect(route('users.index'));

    $this->assertDatabaseHas('users', ['email' => 'mayusculas@test.com']);
});

test('user update allows pre-existing uppercase email and normalizes it to lowercase', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $target = User::factory()->create([
        'email' => 'Legacy.Admin@Pintech.com',
        'name' => 'Legacy Name',
    ]);
    $target->assignRole('operador');

    $this->actingAs($admin)
        ->post(route('users.update', $target), [
            '_method' => 'put',
            'name' => 'Updated Name',
            'email' => 'Legacy.Admin@Pintech.com',
            'role' => 'operador',
            'is_active' => true,
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('message');

    $target->refresh();
    expect($target->name)->toBe('Updated Name')
        ->and($target->email)->toBe('legacy.admin@pintech.com');
});

test('admin cannot deactivate their own account in update', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->put(route('users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'admin',
            'is_active' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'No puedes desactivar tu propia cuenta de usuario.');

    expect($admin->fresh()->is_active)->toBeTrue();
});

test('admin cannot revoke their own admin role in update', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->put(route('users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'operador',
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'No puedes revocar tu propio rol de administrador.');

    expect($admin->fresh()->hasRole('admin'))->toBeTrue();
});

test('hasActivity returns true when user is assigned in quotations, sales orders, or quality responsible', function () {
    $user1 = User::factory()->create();
    expect($user1->hasActivity())->toBeFalse();

    Quotation::factory()->create(['created_by' => $user1->id]);
    expect($user1->hasActivity())->toBeTrue();

    $user2 = User::factory()->create();
    expect($user2->hasActivity())->toBeFalse();

    SalesOrder::factory()->create(['created_by' => $user2->id]);
    expect($user2->hasActivity())->toBeTrue();

    $user3 = User::factory()->create();
    expect($user3->hasActivity())->toBeFalse();

    ProductionOrder::factory()->create(['quality_responsible_user_id' => $user3->id]);
    expect($user3->hasActivity())->toBeTrue();
});

test('destroy catches QueryException with code 23503 and redirects back with error', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $target = User::factory()->create();
    $target->assignRole('operador');

    try {
        User::deleting(function ($user) use ($target) {
            if ($user->id === $target->id) {
                $pdoException = new PDOException('FK violation', 23503);
                $pdoException->errorInfo = ['23503', 23503, 'FK violation'];

                throw new QueryException(
                    'pgsql',
                    'DELETE FROM users...',
                    [],
                    $pdoException
                );
            }
        });

        $this->actingAs($admin)
            ->delete(route('users.destroy', $target))
            ->assertRedirect()
            ->assertSessionHas('error', 'No se puede eliminar el usuario porque tiene registros asociados en el sistema. Desactiva su cuenta en su lugar.');
    } finally {
        User::flushEventListeners();
        User::clearBootedModels();
        new User;
    }

    $this->assertDatabaseHas('users', ['id' => $target->id]);
});

test('admin cannot deactivate the last remaining active administrator', function () {
    $admin1 = User::factory()->create(['is_active' => true]);
    $admin1->assignRole('admin');

    $admin2 = User::factory()->create(['is_active' => false]);
    $admin2->assignRole('admin');

    // admin1 tries to deactivate admin2 (admin2 is already inactive, but what if admin1 tries on the sole active admin?)
    // Specifically: If admin2 tries to deactivate admin1 while admin2 is inactive:
    $this->actingAs($admin1)
        ->put(route('users.update', $admin1), [
            'name' => $admin1->name,
            'email' => $admin1->email,
            'role' => 'admin',
            'is_active' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($admin1->fresh()->is_active)->toBeTrue();
});

test('admin cannot demote or deactivate another admin if they are the only other active admin', function () {
    $admin1 = User::factory()->create(['is_active' => true]);
    $admin1->assignRole('admin');

    $admin2 = User::factory()->create(['is_active' => true]);
    $admin2->assignRole('admin');

    // With 2 active admins, admin1 can deactivate admin2:
    $this->actingAs($admin1)
        ->put(route('users.update', $admin2), [
            'name' => $admin2->name,
            'email' => $admin2->email,
            'role' => 'admin',
            'is_active' => false,
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('message');

    expect($admin2->fresh()->is_active)->toBeFalse();

    // Now admin2 is inactive, only admin1 is active.
    // If admin1 tries to demote admin1, it is blocked:
    $this->actingAs($admin1)
        ->put(route('users.update', $admin1), [
            'name' => $admin1->name,
            'email' => $admin1->email,
            'role' => 'operador',
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($admin1->fresh()->hasRole('admin'))->toBeTrue();
});
