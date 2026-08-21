<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'email_verified_at' => now(),
    ]);
    $this->admin->assignRole('admin');

    $this->userA = User::factory()->create([
        'name' => 'Juan Pérez',
        'email' => 'juan@example.com',
    ]);

    $this->userB = User::factory()->create([
        'name' => 'María García',
        'email' => 'maria@example.com',
    ]);

    $this->userC = User::factory()->create([
        'name' => 'Carlos López',
        'email' => 'carlos@example.com',
    ]);
});

test('filters users by name search', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('users.index', ['search' => 'Juan']));

    $response->assertInertia(fn ($page) => $page
        ->has('users.data', 1)
        ->where('users.data.0.id', $this->userA->id)
        ->where('filters.search', 'Juan')
    );
});

test('filters users by email search', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('users.index', ['search' => 'maria@example.com']));

    $response->assertInertia(fn ($page) => $page
        ->has('users.data', 1)
        ->where('users.data.0.id', $this->userB->id)
        ->where('filters.search', 'maria@example.com')
    );
});

test('search is case insensitive', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('users.index', ['search' => 'juan']));

    $response->assertInertia(fn ($page) => $page
        ->has('users.data', 1)
        ->where('users.data.0.id', $this->userA->id)
    );
});

test('whitespace in search is normalized', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('users.index', ['search' => '  Juan   Pérez  ']));

    $response->assertInertia(fn ($page) => $page
        ->has('users.data', 1)
        ->where('users.data.0.id', $this->userA->id)
    );
});

test('invalid filter keys are ignored', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('users.index', ['search' => 'Juan', 'invalid_key' => 'value']));

    $response->assertInertia(fn ($page) => $page
        ->has('users.data', 1)
        ->where('filters.search', 'Juan')
        ->missing('filters.invalid_key')
    );
});

test('pagination preserves query string', function (): void {
    User::factory()->count(16)->sequence(fn ($sq) => ['name' => "Searchable User {$sq->index}"])->create();

    $response = $this->actingAs($this->admin)
        ->get(route('users.index', ['search' => 'Searchable User']));

    $response->assertInertia(fn ($page) => $page
        ->has('users.data', 15)
        ->where('users.links.2.url', fn ($url) => is_string($url) && str_contains($url, 'search=Searchable') && str_contains($url, 'page=2'))
    );
});

test('non admin users cannot access users index', function (): void {
    $unauthorizedUser = User::factory()->create();
    $unauthorizedUser->assignRole('comercial');

    $this->actingAs($unauthorizedUser)
        ->get(route('users.index'))
        ->assertForbidden();
});
