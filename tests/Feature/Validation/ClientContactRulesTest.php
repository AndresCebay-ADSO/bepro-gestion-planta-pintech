<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\Client;
use App\Models\SalesOrder;

/*
 * Auditoría 3.6, PR 10 (PV-02, D-PV2, CLI-03, USR-04, USR-03): NIT y teléfono de contacto con una sola regla en el
 * cliente, la cotización, el pedido y el usuario. Antes la cotización aceptaba 30 caracteres y el pedido 20: un pedido
 * convertido con un NIT de 25 no se podía volver a guardar.
 */

it('limits the NIT to 20 characters in clients, quotations and sales orders', function (): void {
    actingAsRole(SystemRole::Admin, ['email_verified_at' => now()]);
    $client = Client::factory()->create();
    $order = SalesOrder::factory()->pending()->create(['client_id' => $client->id]);

    $tooLong = str_repeat('1', 21);

    $this->post(route('clients.store'), ['business_name' => 'Cliente', 'nit' => $tooLong])
        ->assertSessionHasErrors('nit');
    $this->put(route('clients.update', $client), ['business_name' => 'Cliente', 'nit' => $tooLong])
        ->assertSessionHasErrors('nit');
    $this->post(route('quotations.store'), ['client_id' => $client->id, 'client_nit' => $tooLong])
        ->assertSessionHasErrors('client_nit');
    $this->patch(route('sales-orders.update', $order), ['client_nit' => $tooLong])
        ->assertSessionHasErrors('client_nit');
});

it('lets a sales order be saved again with the NIT and phone it already has', function (): void {
    actingAsRole(SystemRole::Admin, ['email_verified_at' => now()]);
    $order = SalesOrder::factory()->pending()->create(['client_nit' => '901.674.872-1', 'client_phone' => '+57 300 123 4567']);

    $this->patch(route('sales-orders.update', $order), [
        'client_nit' => $order->client_nit,
        'client_phone' => $order->client_phone,
        'notes' => 'Cambio solo la nota',
    ])->assertSessionHasNoErrors()->assertRedirect();
});

it('accepts real phone numbers and rejects text in the phone of every contact form', function (string $phone, bool $valid): void {
    actingAsRole(SystemRole::Admin, ['email_verified_at' => now()]);
    $client = Client::factory()->create();

    $response = $this->put(route('clients.update', $client), ['business_name' => 'Cliente', 'phone' => $phone]);

    $valid ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('phone');
})->with([
    'celular' => ['3001234567', true],
    'con indicativo' => ['+57 300 123 4567', true],
    'fijo con paréntesis' => ['(602) 555 1234', true],
    'con puntos y almohadilla' => ['310.555.1234 #12', true],
    'texto' => ['llamar luego', false],
    'muy corto' => ['12345', false],
    'más de 20' => ['3001234567 3001234567 1', false],
    'solo signos' => ['+++++++', false],
    'signos y espacios' => ['()-./# +', false],
    'menos de 7 dígitos' => ['12-34-5 #1', false],
    'siete dígitos con formato' => ['123 45 67', true],
]);

it('applies the same phone rule to sales orders and to the user profile', function (): void {
    $user = actingAsRole(SystemRole::Admin, ['email_verified_at' => now()]);
    $order = SalesOrder::factory()->pending()->create();

    $this->patch(route('sales-orders.update', $order), ['client_phone' => 'llamar luego'])
        ->assertSessionHasErrors(['client_phone' => 'El teléfono solo puede tener números, espacios y los signos + ( ) - . / #, con al menos 7 dígitos y hasta 20 caracteres.']);

    $this->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'phone' => 'llamar luego'])
        ->assertSessionHasErrors('phone');
    // El teléfono del usuario pasa de 15 a 20: «+57 300 123 4567» tiene 16.
    $this->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'phone' => '+57 300 123 4567'])
        ->assertSessionHasNoErrors();
});

it('rejects an email without a full domain', function (): void {
    $user = actingAsRole(SystemRole::Admin, ['email_verified_at' => now()]);

    $this->patch(route('profile.update'), ['name' => $user->name, 'email' => 'nuevo@pintech'])
        ->assertSessionHasErrors(['email' => 'El correo electrónico debe incluir un dominio completo, por ejemplo empresa.com.']);
});
