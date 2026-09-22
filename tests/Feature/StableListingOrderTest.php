<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\PaintDevelopmentRequest;
use App\Models\ProductionOrder;
use App\Models\ProductionRemnant;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/**
 * Los listados ordenan por fecha y desempatan por id. Sin el desempate, dos registros del mismo segundo salen en
 * orden arbitrario (PostgreSQL) y la paginación puede repetirlos o saltárselos.
 *
 * Los casos de auditoría y de saldos solo delatan la falta del desempate en PostgreSQL; en SQLite no se reproducen.
 * El CI corre la suite en los dos motores.
 */
it('ordena por id descendente los registros con la misma fecha', function (string $routeName, string $prop, SystemRole $role, Closure $make) {
    $user = actingAsRole($role);
    $sameMoment = now()->subDay();

    $ids = collect(range(1, 3))
        ->map(fn (): int => $make($user, $sameMoment)->id)
        ->all();

    $this->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // Solo los tres creados: el listado puede traer otros registros (el propio usuario, por ejemplo).
            ->where("{$prop}.data", fn ($rows) => collect($rows)->pluck('id')->intersect($ids)->values()->all() === array_reverse($ids)));
})->with([
    'cotizaciones' => [
        'quotations.index',
        'quotations',
        SystemRole::Admin,
        fn (User $user, $at) => Quotation::factory()->create(['created_by' => $user->id, 'created_at' => $at]),
    ],
    'pedidos' => [
        'sales-orders.index',
        'orders',
        SystemRole::Admin,
        fn (User $user, $at) => SalesOrder::factory()->create(['created_by' => $user->id, 'created_at' => $at]),
    ],
    'órdenes de producción' => [
        'production-orders.index',
        'orders',
        SystemRole::Admin,
        fn (User $user, $at) => ProductionOrder::factory()->create(['created_by' => $user->id, 'created_at' => $at]),
    ],
    'solicitudes de desarrollo' => [
        'paint-development-requests.index',
        'requests',
        SystemRole::Admin,
        fn (User $user, $at) => PaintDevelopmentRequest::factory()->create(['created_by' => $user->id, 'created_at' => $at]),
    ],
    'usuarios' => [
        'users.index',
        'users',
        SystemRole::Admin,
        fn (User $user, $at) => User::factory()->create(['created_at' => $at]),
    ],
    'saldos de producción' => [
        'production.remnants.index',
        'remnants',
        SystemRole::Admin,
        function (User $user, $at) {
            $order = ProductionOrder::factory()->create(['created_by' => $user->id]);

            return ProductionRemnant::factory()->create([
                'source_order_id' => $order->id,
                'product_id' => $order->product_id,
                'warehouse_id' => $order->warehouse_id,
                'created_by' => $user->id,
                'created_at' => $at,
            ]);
        },
    ],
    'auditoría' => [
        'audit-logs.index',
        'logs',
        SystemRole::SuperAdmin,
        fn (User $user, $at) => tap(Activity::query()->create([
            'log_name' => 'security',
            'description' => 'Entrada de prueba',
            'causer_type' => User::class,
            'causer_id' => $user->id,
        ]), fn (Activity $activity) => $activity->forceFill(['created_at' => $at])->save()),
    ],
]);
