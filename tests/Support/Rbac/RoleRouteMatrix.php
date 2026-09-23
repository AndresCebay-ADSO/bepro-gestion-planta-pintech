<?php

declare(strict_types=1);

namespace Tests\Support\Rbac;

use App\Enums\SystemRole;

/**
 * Qué pantalla principal abre cada rol del sistema, copiado a mano de docs/MATRIZ_RBAC.md §3.
 *
 * No se deriva del seeder ni de los permisos: si se dedujera de ellos, el test solo comprobaría que el código
 * coincide consigo mismo. Al declararlo aparte, cualquier cambio de acceso obliga a tocar esta lista, y eso es
 * justo lo que debe discutirse antes de mergear.
 *
 * `true` = abre la pantalla (200); `false` = la aplicación la niega (403).
 */
final class RoleRouteMatrix
{
    /**
     * Rutas GET sin parámetros protegidas por permiso: las pantallas principales del sistema.
     *
     * @return array<string, array<string, bool>>
     */
    public static function screens(): array
    {
        $all = [
            SystemRole::SuperAdmin->value => true,
            SystemRole::Admin->value => true,
            SystemRole::Production->value => true,
            SystemRole::Operator->value => true,
            SystemRole::Commercial->value => true,
        ];

        return [
            // Módulo => [rol => abre]
            'dashboard' => $all,

            // Usuarios, roles y auditoría: gobierno del sistema.
            'users.index' => self::only(SystemRole::SuperAdmin, SystemRole::Admin),
            'users.create' => self::only(SystemRole::SuperAdmin, SystemRole::Admin),
            'roles.index' => self::only(SystemRole::SuperAdmin),
            'roles.create' => self::only(SystemRole::SuperAdmin),
            'audit-logs.index' => self::only(SystemRole::SuperAdmin),

            // Productos, costos y fórmulas: la fórmula es secreto industrial.
            'products.index' => self::only(SystemRole::SuperAdmin, SystemRole::Admin, SystemRole::Production, SystemRole::Commercial),
            'products.create' => self::only(SystemRole::SuperAdmin, SystemRole::Admin),
            'admin.costs.index' => self::only(SystemRole::SuperAdmin, SystemRole::Admin),
            'formulas.index' => self::only(SystemRole::SuperAdmin, SystemRole::Admin),
            'formulas.create' => self::only(SystemRole::SuperAdmin, SystemRole::Admin),

            // Materias primas e inventario.
            'raw-materials.index' => self::only(SystemRole::SuperAdmin, SystemRole::Admin, SystemRole::Production),
            'raw-materials.create' => self::only(SystemRole::SuperAdmin, SystemRole::Admin),
            'inventory-movements.index' => self::only(SystemRole::SuperAdmin, SystemRole::Admin, SystemRole::Production, SystemRole::Operator),
            'finished-inventory.index' => $all,
            'finished-inventory-movements.index' => self::only(SystemRole::SuperAdmin, SystemRole::Admin, SystemRole::Production),

            // Producción: Comercial no entra en la planta (§7.3).
            'production-orders.index' => self::only(SystemRole::SuperAdmin, SystemRole::Admin, SystemRole::Production, SystemRole::Operator),
            'production-orders.create' => self::only(SystemRole::SuperAdmin, SystemRole::Admin, SystemRole::Production),
            'production.remnants.index' => self::only(SystemRole::SuperAdmin, SystemRole::Admin, SystemRole::Production, SystemRole::Operator),
            'alerts.index' => self::only(SystemRole::SuperAdmin, SystemRole::Admin, SystemRole::Production),

            // Comercial: cotizaciones, pedidos, clientes y precios.
            'quotations.index' => self::only(SystemRole::SuperAdmin, SystemRole::Admin, SystemRole::Commercial),
            'quotations.create' => self::only(SystemRole::SuperAdmin, SystemRole::Admin, SystemRole::Commercial),
            'sales-orders.index' => self::only(SystemRole::SuperAdmin, SystemRole::Admin, SystemRole::Production, SystemRole::Commercial),
            'sales-orders.create' => self::only(SystemRole::SuperAdmin, SystemRole::Admin, SystemRole::Commercial),
            'clients.index' => self::only(SystemRole::SuperAdmin, SystemRole::Admin, SystemRole::Commercial),
            'clients.create' => self::only(SystemRole::SuperAdmin, SystemRole::Admin, SystemRole::Commercial),
            'prices.index' => self::only(SystemRole::SuperAdmin, SystemRole::Admin, SystemRole::Commercial),
            'paint-development-requests.index' => self::only(SystemRole::SuperAdmin, SystemRole::Admin, SystemRole::Commercial),
            'paint-development-requests.create' => self::only(SystemRole::SuperAdmin, SystemRole::Admin, SystemRole::Commercial),

            // Bodegas y QR.
            'warehouses.index' => self::only(SystemRole::SuperAdmin, SystemRole::Admin, SystemRole::Production, SystemRole::Commercial),
            'warehouses.create' => self::only(SystemRole::SuperAdmin, SystemRole::Admin),
            'qr-codes.index' => self::only(SystemRole::SuperAdmin, SystemRole::Admin, SystemRole::Production, SystemRole::Commercial),
        ];
    }

    /**
     * Rutas que abre cualquier usuario autenticado, con el estado que devuelven y por qué.
     *
     * Están declaradas para que ese "sin permiso" sea una decisión escrita y no un olvido al añadir la ruta.
     * Se separan en dos grupos porque no se vigilan igual: las de la aplicación las cubre
     * `RoutePermissionMap` (marcador AUTHENTICATED) y hay una guarda que exige que aparezcan aquí; las de
     * Fortify quedan fuera de esa clasificación a propósito (`IGNORED_ACTION_PREFIXES`).
     *
     * @return array<string, array{status: int, to?: string}>
     */
    public static function openToAnyUser(): array
    {
        return [
            // De la aplicación: ajustes de la propia cuenta.
            'profile.edit' => ['status' => 200],
            'appearance.edit' => ['status' => 200],

            // De Fortify.
            'password.confirm' => ['status' => 200],
            // Redirige al dashboard: el usuario de las pruebas ya tiene el correo verificado.
            'verification.notice' => ['status' => 302, 'to' => 'dashboard'],
        ];
    }

    /**
     * @return array<string, bool>
     */
    private static function only(SystemRole ...$roles): array
    {
        $allowed = array_map(fn (SystemRole $role) => $role->value, $roles);

        return collect(SystemRole::cases())
            ->mapWithKeys(fn (SystemRole $role) => [$role->value => in_array($role->value, $allowed, true)])
            ->all();
    }
}
