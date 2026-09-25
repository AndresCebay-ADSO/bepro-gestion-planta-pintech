<?php

declare(strict_types=1);

use Tests\Support\TestDatabaseGuard;

test('acepta solo bases de pruebas', function (string $database, bool $expected) {
    expect(TestDatabaseGuard::isTestDatabase($database))->toBe($expected);
})->with([
    'SQLite en memoria' => [':memory:', true],
    'PostgreSQL de pruebas' => ['pintech_erp_test', true],
    'proceso en paralelo' => ['pintech_erp_test_test_1', true],
    'SQLite en archivo de pruebas' => ['/var/www/database/pintech_test.sqlite', true],
    'base de desarrollo' => ['pintech_erp', false],
    'SQLite en archivo de desarrollo' => ['/var/www/database/database.sqlite', false],
    'nombre que solo contiene test' => ['pintech_test_backup', false],
    'sufijo sin guion bajo' => ['pintechtest', false],
]);

test('detiene los tests antes de tocar una base que no es de pruebas', function () {
    expect(fn () => TestDatabaseGuard::ensureSafe('pintech_erp'))
        ->toThrow(RuntimeException::class, 'Los tests se detuvieron antes de tocar la base «pintech_erp»');
});
