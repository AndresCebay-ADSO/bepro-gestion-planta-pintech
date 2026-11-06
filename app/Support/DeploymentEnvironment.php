<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

/**
 * Qué permite cada entorno. Es la única fuente: el arranque de la aplicación aplica las protecciones y los seeders
 * preguntan aquí si pueden sembrar datos de prueba.
 *
 * - `local` y `testing` (la máquina de quien desarrolla y las pruebas): sin protecciones y con todos los datos de prueba.
 * - Cualquier otro valor de `APP_ENV` (`staging`, `production` o uno mal escrito): protegido. Así un `APP_ENV` mal puesto
 *   también queda bloqueado, en vez de quedar abierto.
 */
final class DeploymentEnvironment
{
    /** @var list<string> */
    private const UNPROTECTED = ['local', 'testing'];

    /**
     * Fuera de `local` y `testing`: comandos destructivos bloqueados y contraseñas exigentes.
     */
    public static function isProtected(): bool
    {
        return ! app()->environment(self::UNPROTECTED);
    }

    /**
     * Datos de demostración (lotes y pedidos de prueba): también en staging, donde se siembran una sola vez y a mano.
     * Nunca en producción. Los usuarios de prueba no cuentan aquí: solo se siembran donde el entorno no está protegido.
     */
    public static function allowsDemoData(): bool
    {
        return app()->environment([...self::UNPROTECTED, 'staging']);
    }

    /**
     * `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `migrate:rollback` y `db:wipe`. En un entorno protegido solo se
     * permiten en staging, y solo mientras `ALLOW_DESTRUCTIVE_COMMANDS=true` esté puesta para resetear la base a mano.
     * Producción nunca los permite, tenga o no la variable.
     */
    public static function allowsDestructiveCommands(): bool
    {
        if (! self::isProtected()) {
            return true;
        }

        return app()->environment('staging') && config('app.allow_destructive_commands') === true;
    }

    /**
     * Se llama una vez al arrancar la aplicación (AppServiceProvider).
     */
    public static function applyProtections(): void
    {
        DB::prohibitDestructiveCommands(! self::allowsDestructiveCommands());

        Password::defaults(fn (): ?Password => self::isProtected()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
