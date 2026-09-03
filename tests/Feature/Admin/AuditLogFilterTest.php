<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create([
        'name' => 'Admin Boss',
        'email' => 'admin@pintech.com',
        'email_verified_at' => now(),
    ]);
    $this->admin->assignRole('admin');

    $this->operator = User::factory()->create([
        'name' => 'Carlos Operador',
        'email' => 'carlos@pintech.com',
        'email_verified_at' => now(),
    ]);

    Activity::query()->delete();

    $this->logA = Activity::create([
        'log_name' => 'usuarios',
        'description' => 'Usuario creado exitosamente',
        'subject_type' => User::class,
        'subject_id' => $this->operator->id,
        'causer_type' => User::class,
        'causer_id' => $this->admin->id,
        'event' => 'created',
        'created_at' => '2026-06-01 10:00:00',
    ]);

    $this->logB = Activity::create([
        'log_name' => 'inventario',
        'description' => 'Ajuste de inventario realizado',
        'subject_type' => User::class,
        'subject_id' => $this->operator->id,
        'causer_type' => User::class,
        'causer_id' => $this->operator->id,
        'event' => 'updated',
        'created_at' => '2026-06-15 14:30:00',
    ]);
});

it('filters by description in search', function (): void {
    actingAs($this->admin);

    $response = get(route('audit-logs.index', ['search' => 'Ajuste de inventario']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Admin/AuditLogs/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.id', $this->logB->id)
    );
});

it('filters by causer name in search', function (): void {
    actingAs($this->admin);

    $response = get(route('audit-logs.index', ['search' => 'Admin Boss']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Admin/AuditLogs/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.id', $this->logA->id)
    );
});

it('filters by causer email in search', function (): void {
    actingAs($this->admin);

    $response = get(route('audit-logs.index', ['search' => 'carlos@pintech.com']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Admin/AuditLogs/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.id', $this->logB->id)
    );
});

it('filters by log_name', function (): void {
    actingAs($this->admin);

    $response = get(route('audit-logs.index', ['log_name' => 'usuarios']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Admin/AuditLogs/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.id', $this->logA->id)
    );
});

it('filters by event', function (): void {
    actingAs($this->admin);

    $response = get(route('audit-logs.index', ['event' => 'updated']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Admin/AuditLogs/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.id', $this->logB->id)
    );
});

it('filters by date range', function (): void {
    actingAs($this->admin);

    $response = get(route('audit-logs.index', [
        'date_from' => '2026-06-10',
        'date_to' => '2026-06-20',
    ]));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Admin/AuditLogs/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.id', $this->logB->id)
    );
});

it('combines multiple filters correctly', function (): void {
    actingAs($this->admin);

    $response = get(route('audit-logs.index', [
        'search' => 'creado',
        'log_name' => 'usuarios',
        'event' => 'created',
        'date_from' => '2026-05-01',
        'date_to' => '2026-06-05',
    ]));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Admin/AuditLogs/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.id', $this->logA->id)
    );
});

it('preserves query string in pagination links', function (): void {
    actingAs($this->admin);

    $response = get(route('audit-logs.index', ['search' => 'Usuario']));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Admin/AuditLogs/Index')
            ->has('logs.data', 1)
            ->has('logs.links')
            ->where('logs.links', fn ($links) => collect($links)->contains(
                fn ($link) => $link['url'] !== null && str_contains((string) $link['url'], 'search=Usuario')
            ))
    );
});

it('ignores unknown filter keys and strips whitespace', function (): void {
    actingAs($this->admin);

    $response = get(route('audit-logs.index', [
        'search' => '   Ajuste   ',
        'bogus' => 'val',
    ]));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Admin/AuditLogs/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.id', $this->logB->id)
    );
});

it('fails validation when date_to is before date_from', function (): void {
    actingAs($this->admin);

    $response = getJson(route('audit-logs.index', [
        'date_from' => '2026-06-20',
        'date_to' => '2026-06-10',
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['date_to']);
});

it('forbids unauthorized users from accessing audit logs', function (): void {
    actingAs($this->operator);

    $response = get(route('audit-logs.index'));

    $response->assertForbidden();
});
