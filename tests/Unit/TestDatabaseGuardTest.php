<?php

declare(strict_types=1);

use Tests\Support\TestDatabaseGuard;

test('acepta solo bases de pruebas', function (string $driver, string $database, bool $expected) {
    expect(TestDatabaseGuard::isTestDatabase($driver, $database))->toBe($expected);
})->with([
    'SQLite en memoria' => ['sqlite', ':memory:', true],
    'PostgreSQL de pruebas' => ['pgsql', 'pintech_erp_test', true],
    'proceso en paralelo' => ['pgsql', 'pintech_erp_test_test_1', true],
    'SQLite en archivo de pruebas' => ['sqlite', '/var/www/database/pintech_test.sqlite', true],
    'base de desarrollo' => ['pgsql', 'pintech_erp', false],
    'SQLite en archivo de desarrollo' => ['sqlite', '/var/www/database/database.sqlite', false],
    'nombre que solo contiene test' => ['pgsql', 'pintech_test_backup', false],
    'sufijo sin guion bajo' => ['pgsql', 'pintechtest', false],
    'PostgreSQL con punto tras el sufijo' => ['pgsql', 'pintech_erp_test.backup', false],
]);

test('detiene los tests antes de tocar una base que no es de pruebas', function () {
    expect(fn () => TestDatabaseGuard::ensureSafe('pgsql', 'pintech_erp'))
        ->toThrow(RuntimeException::class, 'Los tests se detuvieron antes de tocar la base «pintech_erp»');
});
