<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/**
 * Impide que los tests corran sobre una base que no es de pruebas.
 *
 * `RefreshDatabase` borra y recrea todas las tablas. `DB_CONNECTION` y `DB_DATABASE` son las únicas `<env>` de
 * phpunit.xml que el entorno puede pisar (así el CI y el comando local prueban en PostgreSQL), de modo que dentro
 * del contenedor `php-fpm`, que carga `.env`, los tests apuntaban a `pintech_erp` y la vaciaban (B44, 2026-09-24).
 *
 * Se acepta SQLite en memoria o una base cuyo nombre termine en `_test`, también con el sufijo que Laravel añade
 * a cada proceso en paralelo (`pintech_erp_test_test_1`).
 */
final class TestDatabaseGuard
{
    public static function ensureSafe(string $driver, string $database): void
    {
        if (self::isTestDatabase($driver, $database)) {
            return;
        }

        throw new RuntimeException(
            "Los tests se detuvieron antes de tocar la base «{$database}»: no es una base de pruebas y RefreshDatabase "
            .'la borraría. Usa SQLite en memoria (DB_CONNECTION=sqlite DB_DATABASE=:memory:) o una base cuyo nombre '
            .'termine en _test (por ejemplo pintech_erp_test).'
        );
    }

    public static function isTestDatabase(string $driver, string $database): bool
    {
        if ($database === ':memory:') {
            return true;
        }

        // Solo en SQLite la base es un archivo: cuenta su nombre, sin carpeta ni extensión. En un servidor
        // (PostgreSQL) cuenta el nombre completo, o `pintech_erp_test.backup` pasaría por `pintech_erp_test`.
        $name = $driver === 'sqlite' ? pathinfo($database, PATHINFO_FILENAME) : $database;

        return preg_match('/_test(_\d+)?$/', $name) === 1;
    }
}
