<?php

use App\Enums\SystemRole;
use App\Models\ProductionOrder;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\SignatureOptimizerService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

// ──────────────────────────────────────────────
// destroy()
// ──────────────────────────────────────────────

test('super-admin cannot delete their own account', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    $this->actingAs($admin)
        ->delete(route('users.destroy', $admin))
        ->assertRedirect()
        ->assertSessionHas('error', 'No puedes eliminar tu propia cuenta.');

    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});

test('super-admin cannot delete a user that has activity logs', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    $target = User::factory()->create();
    $target->assignRole(SystemRole::Operator->value);

    activity('test')
        ->causedBy($target)
        ->log('Acción de prueba');

    $this->actingAs($admin)
        ->delete(route('users.destroy', $target))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('users', ['id' => $target->id]);
});

test('super-admin cannot delete a user that has records via created_by in system tables', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    $target = User::factory()->create();
    $target->assignRole(SystemRole::Operator->value);

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

test('super-admin can delete a user with no activity', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    $target = User::factory()->create();
    $target->assignRole(SystemRole::Operator->value);

    // Garantizar que no tiene ningún registro en el sistema
    Activity::where('causer_id', $target->id)->delete();

    $this->actingAs($admin)
        ->delete(route('users.destroy', $target))
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

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
            'role' => SystemRole::Operator->value,
            'is_active' => true,
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

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
            'role' => SystemRole::Operator->value,
            'is_active' => false,
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

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
    $target->assignRole(SystemRole::Operator->value);

    $this->actingAs($admin)
        ->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'phone' => '3001112222',
            'role' => SystemRole::Operator->value,
            'is_active' => false,
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

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
    $target->assignRole(SystemRole::Operator->value);

    $this->actingAs($admin)
        ->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'phone' => '3003334444',
            'role' => SystemRole::Operator->value,
            'is_active' => true,
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

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
            'role' => SystemRole::Operator->value,
            'is_active' => true,
            'signature' => $file,
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

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
    $target->assignRole(SystemRole::Operator->value);
    Storage::disk('public')->put('signatures/old.png', 'old-content');

    $newFile = UploadedFile::fake()->image('new_signature.png', 200, 100);

    $this->actingAs($admin)
        ->post(route('users.update', $target), [
            '_method' => 'put',
            'name' => $target->name,
            'email' => $target->email,
            'role' => SystemRole::Operator->value,
            'is_active' => true,
            'signature' => $newFile,
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

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
            'role' => SystemRole::Operator->value,
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
    $target->assignRole(SystemRole::Operator->value);
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
            'role' => SystemRole::Operator->value,
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
    $target->assignRole(SystemRole::Operator->value);
    Storage::disk('public')->put('signatures/old.png', 'old-content');

    $this->actingAs($admin)
        ->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => SystemRole::Operator->value,
            'is_active' => true,
            'remove_signature' => true,
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

    $target->refresh();
    expect($target->signature_path)->toBeNull();
    Storage::disk('public')->assertMissing('signatures/old.png');
});

test('signature file is deleted when user is deleted', function () {
    Storage::fake('public');

    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    $target = User::factory()->create([
        'signature_path' => 'signatures/delete_me.png',
    ]);
    $target->assignRole(SystemRole::Operator->value);
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
                'role' => SystemRole::Operator->value,
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
    $admin->assignRole('super-admin');

    $target = User::factory()->create([
        'signature_path' => 'signatures/preserve_me.png',
    ]);
    $target->assignRole(SystemRole::Operator->value);
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
    $target->assignRole(SystemRole::Operator->value);
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
                'role' => SystemRole::Operator->value,
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
            'role' => SystemRole::Operator->value,
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
    $target->assignRole(SystemRole::Operator->value);

    $this->actingAs($admin)
        ->post(route('users.update', $target), [
            '_method' => 'put',
            'name' => 'Updated Name',
            'email' => 'Legacy.Admin@Pintech.com',
            'role' => SystemRole::Operator->value,
            'is_active' => true,
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

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

test('users cannot change their own role in update', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->put(route('users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => SystemRole::Operator->value,
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'No puedes cambiar tu propio rol.');

    expect($admin->fresh()->hasRole('admin'))->toBeTrue();
});

test('a user referenced by quotations, sales orders or as quality responsible cannot be deleted', function (string $relation) {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(SystemRole::SuperAdmin->value);

    $target = User::factory()->create();
    $target->assignRole(SystemRole::Operator->value);

    // Sin actividad auditada: el borrado lo impide la clave foránea (docs/POLITICA_ELIMINACION.md §4).
    match ($relation) {
        'quotation' => Quotation::factory()->create(['created_by' => $target->id]),
        'sales_order' => SalesOrder::factory()->create(['created_by' => $target->id]),
        'quality_responsible' => ProductionOrder::factory()->create(['quality_responsible_user_id' => $target->id]),
    };
    Activity::where('causer_id', $target->id)->delete();

    $this->actingAs($superAdmin)
        ->delete(route('users.destroy', $target))
        ->assertSessionHas('error', 'No se puede eliminar el usuario porque tiene registros asociados en el sistema. Desactiva su cuenta en su lugar.');

    $this->assertDatabaseHas('users', ['id' => $target->id]);
})->with(['quotation', 'sales_order', 'quality_responsible']);

test('destroy catches QueryException with code 23503 and redirects back with error', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    $target = User::factory()->create();
    $target->assignRole(SystemRole::Operator->value);

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

test('an admin can deactivate another admin: only the last super-admin is protected by role', function () {
    $admin1 = User::factory()->create(['is_active' => true]);
    $admin1->assignRole(SystemRole::Admin->value);

    $admin2 = User::factory()->create(['is_active' => true]);
    $admin2->assignRole(SystemRole::Admin->value);

    // Mismos permisos: la policy lo permite, y ya no hay una regla de "último administrador".
    $this->actingAs($admin1)
        ->put(route('users.update', $admin2), [
            'name' => $admin2->name,
            'email' => $admin2->email,
            'role' => SystemRole::Admin->value,
            'is_active' => false,
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

    expect($admin2->fresh()->is_active)->toBeFalse();
});

test('admin cannot delete users', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $target = User::factory()->create();
    $target->assignRole(SystemRole::Operator->value);
    Activity::where('causer_id', $target->id)->delete();

    $this->actingAs($admin)
        ->delete(route('users.destroy', $target))
        ->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $target->id]);
});

test('admin cannot edit or update a super-admin user', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $this->actingAs($admin)
        ->get(route('users.edit', $superAdmin))
        ->assertForbidden();

    $this->actingAs($admin)
        ->put(route('users.update', $superAdmin), [
            'name' => 'Intento',
            'email' => $superAdmin->email,
            'is_active' => false,
            'role' => 'admin',
        ])
        ->assertForbidden();

    expect($superAdmin->fresh()->hasRole('super-admin'))->toBeTrue()
        ->and((bool) $superAdmin->fresh()->is_active)->toBeTrue();
});

test('only users with audit_logs.view receive recent activity on the users index', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('can.viewActivity', false)
            ->where('recentActivities', []));

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $this->actingAs($superAdmin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('can.viewActivity', true)
            ->has('recentActivities'));
});

test('the users index only offers the row actions the policy allows', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(SystemRole::SuperAdmin->value);

    $admin = User::factory()->create();
    $admin->assignRole(SystemRole::Admin->value);

    $operator = User::factory()->create();
    $operator->assignRole(SystemRole::Operator->value);

    $rowCan = fn ($users, User $user) => collect($users)->firstWhere('id', $user->id)['can'];

    // Admin gestiona a cualquiera salvo a un SuperAdmin, y no tiene users.delete.
    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->missing('can.delete')
            ->where('users.data', fn ($users) => $rowCan($users, $operator) === ['update' => true, 'delete' => false]
                && $rowCan($users, $superAdmin) === ['update' => false, 'delete' => false]));

    // SuperAdmin elimina a otros, nunca su propia cuenta.
    $this->actingAs($superAdmin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('users.data', fn ($users) => $rowCan($users, $operator) === ['update' => true, 'delete' => true]
                && $rowCan($users, $superAdmin) === ['update' => true, 'delete' => false]));
});

test('the last active super-admin cannot be deactivated or demoted', function () {
    $activeSuperAdmin = User::factory()->create(['is_active' => true]);
    $activeSuperAdmin->assignRole('super-admin');

    $inactiveSuperAdmin = User::factory()->create(['is_active' => false]);
    $inactiveSuperAdmin->assignRole('super-admin');

    $this->actingAs($inactiveSuperAdmin)
        ->put(route('users.update', $activeSuperAdmin), [
            'name' => $activeSuperAdmin->name,
            'email' => $activeSuperAdmin->email,
            'role' => 'admin',
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'No se puede desactivar o degradar al único super administrador activo del sistema.');

    expect($activeSuperAdmin->fresh()->isSuperAdmin())->toBeTrue();
});

test('a blocked super-admin demotion changes nothing and discards the uploaded signature', function () {
    Storage::fake('public');

    $activeSuperAdmin = User::factory()->create(['is_active' => true, 'name' => 'Nombre original']);
    $activeSuperAdmin->assignRole(SystemRole::SuperAdmin->value);

    $inactiveSuperAdmin = User::factory()->create(['is_active' => false]);
    $inactiveSuperAdmin->assignRole(SystemRole::SuperAdmin->value);

    $this->mock(SignatureOptimizerService::class, function ($mock) {
        $mock->shouldReceive('optimizeAndStore')->once()->andReturnUsing(function () {
            Storage::disk('public')->put('signatures/blocked.png', 'new-content');

            return 'signatures/blocked.png';
        });
    });

    $this->actingAs($inactiveSuperAdmin)
        ->put(route('users.update', $activeSuperAdmin), [
            'name' => 'Nombre nuevo',
            'email' => $activeSuperAdmin->email,
            'role' => SystemRole::SuperAdmin->value,
            'is_active' => false,
            'signature' => UploadedFile::fake()->image('firma.png', 200, 100),
        ])
        ->assertSessionHas('error', 'No se puede desactivar o degradar al único super administrador activo del sistema.');

    Storage::disk('public')->assertMissing('signatures/blocked.png');

    $activeSuperAdmin->refresh();
    expect($activeSuperAdmin->name)->toBe('Nombre original')
        ->and((bool) $activeSuperAdmin->is_active)->toBeTrue()
        ->and($activeSuperAdmin->signature_path)->toBeNull();
});

test('the last active super-admin cannot be deleted', function () {
    $activeSuperAdmin = User::factory()->create(['is_active' => true]);
    $activeSuperAdmin->assignRole(SystemRole::SuperAdmin->value);

    // Quien borra es un SuperAdmin con la cuenta desactivada: el objetivo es el único activo.
    $inactiveSuperAdmin = User::factory()->create(['is_active' => false]);
    $inactiveSuperAdmin->assignRole(SystemRole::SuperAdmin->value);
    Activity::where('causer_id', $activeSuperAdmin->id)->delete();

    $this->actingAs($inactiveSuperAdmin)
        ->delete(route('users.destroy', $activeSuperAdmin))
        ->assertRedirect()
        ->assertSessionHas('error', 'No se puede eliminar al único super administrador activo del sistema.');

    $this->assertDatabaseHas('users', ['id' => $activeSuperAdmin->id]);
});

test('an inactive super-admin can be deleted while another one stays active', function () {
    $superAdmin = User::factory()->create(['is_active' => true]);
    $superAdmin->assignRole(SystemRole::SuperAdmin->value);

    $inactiveSuperAdmin = User::factory()->create(['is_active' => false]);
    $inactiveSuperAdmin->assignRole(SystemRole::SuperAdmin->value);
    Activity::where('causer_id', $inactiveSuperAdmin->id)->delete();

    $this->actingAs($superAdmin)
        ->delete(route('users.destroy', $inactiveSuperAdmin))
        ->assertRedirect(route('users.index'));

    $this->assertDatabaseMissing('users', ['id' => $inactiveSuperAdmin->id]);
});

test('a super-admin without activity can be deleted by another super-admin', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(SystemRole::SuperAdmin->value);

    $otherSuperAdmin = User::factory()->create();
    $otherSuperAdmin->assignRole(SystemRole::SuperAdmin->value);
    Activity::where('causer_id', $otherSuperAdmin->id)->delete();

    // Quien borra no puede borrarse a sí mismo, así que siempre queda al menos un SuperAdmin.
    $this->actingAs($superAdmin)
        ->delete(route('users.destroy', $otherSuperAdmin))
        ->assertRedirect(route('users.index'));

    $this->assertDatabaseMissing('users', ['id' => $otherSuperAdmin->id]);
});
