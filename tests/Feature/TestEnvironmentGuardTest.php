<?php

declare(strict_types=1);

/**
 * Asegura que la suite de pruebas corra con la configuración aislada de testing.
 *
 * En entornos con Docker (compose.dev.yaml con env_file), las variables del .env
 * se cargan como variables del sistema ($_SERVER), que Laravel lee antes que $_ENV.
 * Esta prueba garantiza que las entradas <server> de phpunit.xml neutralicen
 * el entorno del contenedor (B60, B64) y eviten efectos secundarios como envíos
 * reales de correo (mail.default=smtp), persistencia de sesiones en archivo o
 * escrituras en los logs de desarrollo.
 */
test('test suite runs with isolated testing configuration', function (): void {
    expect(config('app.env'))->toBe('testing')
        ->and(config('mail.default'))->toBe('array')
        ->and(config('session.driver'))->toBe('array')
        ->and(config('cache.default'))->toBe('array')
        ->and(config('queue.default'))->toBe('sync')
        ->and(config('logging.default'))->toBeNull();
});
