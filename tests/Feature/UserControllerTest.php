<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
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
