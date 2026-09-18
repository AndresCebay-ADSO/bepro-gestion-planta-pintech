<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\Client;
use App\Models\Quotation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Clientes: se desactivan con clients.deactivate y solo se eliminan sin cotizaciones ni pedidos
 * (docs/POLITICA_ELIMINACION.md §3.1).
 */

/**
 * @return array<string, mixed>
 */
function clientUpdatePayload(Client $client, bool $isActive): array
{
    return [
        'business_name' => $client->business_name,
        'nit' => $client->nit,
        'is_active' => $isActive,
    ];
}

it('elimina un cliente sin cotizaciones ni pedidos y rechaza uno que ya cotizó', function () {
    $admin = actingAsRole(SystemRole::Admin);
    $unused = Client::factory()->create();
    $quoted = Client::factory()->create();
    Quotation::factory()->create(['client_id' => $quoted->id, 'created_by' => $admin->id]);

    $this->delete(route('clients.destroy', $unused))->assertRedirect(route('clients.index'));
    $this->assertDatabaseMissing('clients', ['id' => $unused->id]);

    $this->delete(route('clients.destroy', $quoted))->assertSessionHas('error');
    $this->assertDatabaseHas('clients', ['id' => $quoted->id]);
});

it('desactiva y reactiva un cliente con cotizaciones', function () {
    $admin = actingAsRole(SystemRole::Admin);
    $client = Client::factory()->create();
    Quotation::factory()->create(['client_id' => $client->id, 'created_by' => $admin->id]);

    $this->put(route('clients.update', $client), clientUpdatePayload($client, false))->assertRedirect(route('clients.index'));
    expect($client->fresh()->is_active)->toBeFalse();

    $this->put(route('clients.update', $client), clientUpdatePayload($client, true))->assertRedirect(route('clients.index'));
    expect($client->fresh()->is_active)->toBeTrue();
});

it('exige clients.deactivate para cambiar el estado, no para editar el resto', function () {
    // Rol personalizado que edita clientes pero no los desactiva.
    userWithRole(SystemRole::Admin);
    $editor = User::factory()->create();
    $editor->givePermissionTo([Permission::DashboardView->value, Permission::ClientsView->value, Permission::ClientsEdit->value]);
    $this->actingAs($editor);
    $client = Client::factory()->create();

    $this->get(route('clients.edit', $client))
        ->assertInertia(fn (Assert $page) => $page->where('can.deactivate', false));

    $this->put(route('clients.update', $client), clientUpdatePayload($client, false))->assertForbidden();
    expect($client->fresh()->is_active)->toBeTrue();

    $this->put(route('clients.update', $client), [...clientUpdatePayload($client, true), 'business_name' => 'Nuevo nombre'])
        ->assertRedirect(route('clients.index'));
    expect($client->fresh()->business_name)->toBe('Nuevo nombre');
});

it('no ofrece clientes inactivos al crear una cotización', function () {
    actingAsRole(SystemRole::Commercial);
    $active = Client::factory()->create(['business_name' => 'Cliente Activo']);
    $inactive = Client::factory()->create(['business_name' => 'Cliente Inactivo', 'is_active' => false]);

    $this->get(route('quotations.create'))
        ->assertInertia(fn (Assert $page) => $page->where('clients', fn ($clients) => collect($clients)->pluck('id')->contains($active->id)
            && ! collect($clients)->pluck('id')->contains($inactive->id)));
});
