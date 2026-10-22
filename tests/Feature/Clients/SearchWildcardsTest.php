<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

/*
 * Auditoría 3.6, PR 10 (LST-01): `%` y `_` del buscador eran comodines de LIKE. Buscar `%` devolvía todo y `_`
 * coincidía con cualquier carácter. Ahora se buscan como texto.
 */

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('super-admin');

    $this->underscore = Client::factory()->create(['business_name' => 'ABA_1 Pinturas', 'nit' => '111']);
    $this->hyphen = Client::factory()->create(['business_name' => 'ABA-1 Pinturas', 'nit' => '222']);
    $this->percent = Client::factory()->create(['business_name' => 'Descuento 100% Pinturas', 'nit' => '333']);
    $this->backslash = Client::factory()->create(['business_name' => 'Ruta C:\\Pinturas', 'nit' => '444']);
    $this->exclamation = Client::factory()->create(['business_name' => '¡Oferta! Pinturas', 'nit' => '555']);
});

it('searches an underscore as text and not as any character', function (): void {
    $this->actingAs($this->admin)
        ->get(route('clients.index', ['search' => 'ABA_1']))
        ->assertInertia(fn ($page) => $page
            ->has('clients.data', 1)
            ->where('clients.data.0.id', $this->underscore->id)
        );
});

it('searches a percent sign as text and not as every row', function (): void {
    $this->actingAs($this->admin)
        ->get(route('clients.index', ['search' => '%']))
        ->assertInertia(fn ($page) => $page
            ->has('clients.data', 1)
            ->where('clients.data.0.id', $this->percent->id)
        );
});

it('searches a backslash as text', function (): void {
    $this->actingAs($this->admin)
        ->get(route('clients.index', ['search' => '\\']))
        ->assertInertia(fn ($page) => $page
            ->has('clients.data', 1)
            ->where('clients.data.0.id', $this->backslash->id)
        );
});

it('searches the escape character itself as text', function (): void {
    $this->actingAs($this->admin)
        ->get(route('clients.index', ['search' => '!']))
        ->assertInertia(fn ($page) => $page
            ->has('clients.data', 1)
            ->where('clients.data.0.id', $this->exclamation->id)
        );
});

it('escapes the wildcards in the audit log search too', function (): void {
    Activity::query()->delete();
    $plain = Activity::create(['log_name' => 'catalogos', 'description' => 'Ajuste 100% aplicado', 'event' => 'updated']);
    Activity::create(['log_name' => 'catalogos', 'description' => 'Ajuste normal aplicado', 'event' => 'updated']);

    $this->actingAs($this->admin)
        ->get(route('audit-logs.index', ['search' => '%']))
        ->assertInertia(fn ($page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.id', $plain->id)
        );
});
