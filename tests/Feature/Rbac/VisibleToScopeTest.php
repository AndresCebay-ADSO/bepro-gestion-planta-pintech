<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\Client;
use App\Models\PaintDevelopmentRequest;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

/**
 * scopeVisibleTo (Quotation, SalesOrder, PaintDevelopmentRequest): con view_all ve todo, con view_own
 * solo lo propio, y sin usuario no debe ver nada — nunca "todo" (docs/REVISION_RAMA_RBAC.md, hallazgo #1
 * de la tercera revisión). El scope solo se invoca hoy detrás de 'auth', así que esto es una prueba de
 * defensa en profundidad: protege contra un futuro uso del scope desde un job o comando sin usuario.
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $client = Client::factory()->create();
    $owner = User::factory()->create();
    $someoneElse = User::factory()->create();

    Quotation::factory()->create(['client_id' => $client->id, 'created_by' => $owner->id]);
    Quotation::factory()->create(['client_id' => $client->id, 'created_by' => $someoneElse->id]);

    SalesOrder::factory()->create(['client_id' => $client->id, 'created_by' => $owner->id]);
    SalesOrder::factory()->create(['client_id' => $client->id, 'created_by' => $someoneElse->id]);

    PaintDevelopmentRequest::factory()->create(['created_by' => $owner->id]);
    PaintDevelopmentRequest::factory()->create(['created_by' => $someoneElse->id]);

    $this->owner = $owner;
});

it('no devuelve ningún registro cuando el usuario es null, en los tres modelos con dueño', function (string $model) {
    expect($model::query()->visibleTo(null)->count())->toBe(0)
        ->and($model::query()->count())->toBeGreaterThan(0);
})->with([
    'cotizaciones' => [Quotation::class],
    'pedidos' => [SalesOrder::class],
    'desarrollo de pinturas' => [PaintDevelopmentRequest::class],
]);

it('con view_own solo ve los registros propios, en los tres modelos con dueño', function (string $model) {
    expect($model::query()->visibleTo($this->owner)->pluck('created_by')->unique()->all())->toBe([$this->owner->id]);
})->with([
    'cotizaciones' => [Quotation::class],
    'pedidos' => [SalesOrder::class],
    'desarrollo de pinturas' => [PaintDevelopmentRequest::class],
]);

it('con view_all ve todos los registros de cada modelo con dueño', function () {
    $manager = userWithRole(SystemRole::Admin);

    expect(Quotation::query()->visibleTo($manager)->count())->toBe(2)
        ->and(SalesOrder::query()->visibleTo($manager)->count())->toBe(2)
        ->and(PaintDevelopmentRequest::query()->visibleTo($manager)->count())->toBe(2);
});
