<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * Los parámetros de ruta que se resuelven a un modelo (`/products/{product}`). Una URL con un valor que no es un número
 * (`/products/abc`) o que no cabe en un `bigint` no llega a la consulta: la ruta no coincide y responde 404, en vez del
 * 500 de PostgreSQL (`invalid input syntax for type bigint`). Auditoría 3.6, T-01.
 *
 * El patrón vale por nombre de parámetro y se copia a cada ruta al registrarla, así que `register()` corre en el
 * arranque, antes de cargar las rutas. Un `->whereNumber()` suelto en una ruta lo pisa y deja pasar cualquier cantidad
 * de dígitos: no se usa. `ModelRouteParametersTest` falla si una ruta nueva resuelve un modelo con un nombre que no
 * está aquí.
 *
 * No están (a propósito): `id` y `hash` de Fortify (verificación de correo), `token` del QR público y `path` del disco.
 */
final class ModelRouteParameters
{
    /** Hasta 18 dígitos: el `bigint` llega a 19 (9.223.372.036.854.775.807), y el último no siempre cabe. */
    public const PATTERN = '[0-9]{1,18}';

    /** @var list<string> */
    public const NAMES = [
        'adjustment',
        'alert',
        'client',
        'document',
        'finished_inventory_movement',
        'formula',
        'inventory_movement',
        'paintDevelopmentRequest',
        'plan',
        'product',
        'product_category',
        'production_order',
        'qrCode',
        'quotation',
        'raw_material',
        'raw_material_category',
        'role',
        'sales_order',
        'shrink_wrap',
        'shrink_wrap_type',
        'unit_of_measure',
        'user',
        'variant',
        'warehouse',
    ];

    public static function register(): void
    {
        foreach (self::NAMES as $name) {
            Route::pattern($name, self::PATTERN);
        }
    }
}
