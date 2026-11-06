<?php

declare(strict_types=1);

use App\Support\DeploymentEnvironment;
use Illuminate\Database\Console\Migrations\FreshCommand;
use Illuminate\Database\Console\WipeCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/** Lee si un comando quedó bloqueado, sin correrlo: correrlo de verdad borraría la base de la prueba si fallara. */
function commandIsProhibited(string $command): bool
{
    return (new ReflectionProperty($command, 'prohibitedFromRunning'))->getValue();
}

/**
 * Simula el arranque de la aplicación en otro entorno. La variable se pone en el entorno del proceso, como la deja
 * `docker compose run -e`: la aplicación la lee de ahí y no de `config()`.
 */
function bootIn(string $environment, bool $allowDestructive = false): void
{
    app()->detectEnvironment(fn () => $environment);
    setProcessVariable('ALLOW_DESTRUCTIVE_COMMANDS', $allowDestructive ? 'true' : null);
    DeploymentEnvironment::applyProtections();
}

function setProcessVariable(string $name, ?string $value): void
{
    if ($value === null) {
        unset($_SERVER[$name], $_ENV[$name]);
        putenv($name);

        return;
    }

    $_SERVER[$name] = $_ENV[$name] = $value;
    putenv("{$name}={$value}");
}

afterEach(function (): void {
    // El bloqueo es estático y sobrevive a la aplicación de cada prueba: se deja como en `testing`.
    setProcessVariable('ALLOW_DESTRUCTIVE_COMMANDS', null);
    DB::prohibitDestructiveCommands(false);
});

it('protege todo entorno que no sea local ni testing, también un APP_ENV mal escrito', function (string $environment, bool $protected) {
    app()->detectEnvironment(fn () => $environment);

    expect(DeploymentEnvironment::isProtected())->toBe($protected);
})->with([
    'local' => ['local', false],
    'testing' => ['testing', false],
    'staging' => ['staging', true],
    'production' => ['production', true],
    'mal escrito' => ['prod', true],
]);

it('bloquea los comandos destructivos en staging y producción', function (string $environment) {
    bootIn($environment);

    expect(commandIsProhibited(FreshCommand::class))->toBeTrue()
        ->and(commandIsProhibited(WipeCommand::class))->toBeTrue();
})->with(['staging', 'production', 'prod']);

it('los deja en local y testing', function (string $environment) {
    bootIn($environment);

    expect(commandIsProhibited(FreshCommand::class))->toBeFalse();
})->with(['local', 'testing']);

it('la variable de un solo comando permite resetear staging, pero nunca producción', function () {
    bootIn('staging', allowDestructive: true);
    expect(commandIsProhibited(FreshCommand::class))->toBeFalse();

    bootIn('production', allowDestructive: true);
    expect(commandIsProhibited(FreshCommand::class))->toBeTrue();
});

it('exige contraseñas fuertes fuera de local y testing', function () {
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
    $weak = ['password' => 'clave-segura-123'];
    // La regla se arma en cada validación, como en un request: `Password::default()` lee el entorno en ese momento.
    $passes = fn (string $password): bool => Validator::make(['password' => $password], ['password' => [Password::default()]])->passes();

    bootIn('testing');
    expect($passes($weak['password']))->toBeTrue();

    bootIn('staging');
    expect($passes($weak['password']))->toBeFalse()
        ->and($passes('Clave-Segura-2026!'))->toBeTrue();
});

it('los datos de demostración se siembran en local, testing y staging, nunca en producción', function (string $environment, bool $allowed) {
    app()->detectEnvironment(fn () => $environment);

    expect(DeploymentEnvironment::allowsDemoData())->toBe($allowed);
})->with([
    'local' => ['local', true],
    'testing' => ['testing', true],
    'staging' => ['staging', true],
    'production' => ['production', false],
    'mal escrito' => ['prod', false],
]);

it('un valor de configuración no abre los comandos destructivos: solo la variable del proceso', function () {
    // Con la configuración en caché, `config()` no vería un `-e` nuevo; y la variable nunca va en el `.env`.
    config(['app.allow_destructive_commands' => true]);
    bootIn('staging');

    expect(commandIsProhibited(FreshCommand::class))->toBeTrue();
});

// El servidor no depende de `APP_LOCALE` en su `.env`: sin ella, la aplicación habla español.
it('el idioma por defecto es el español', function () {
    $saved = [getenv('APP_LOCALE'), getenv('APP_FALLBACK_LOCALE')];
    setProcessVariable('APP_LOCALE', null);
    setProcessVariable('APP_FALLBACK_LOCALE', null);

    try {
        $config = require config_path('app.php');
    } finally {
        setProcessVariable('APP_LOCALE', $saved[0] === false ? null : $saved[0]);
        setProcessVariable('APP_FALLBACK_LOCALE', $saved[1] === false ? null : $saved[1]);
    }

    expect($config['locale'])->toBe('es')
        ->and($config['fallback_locale'])->toBe('es');
});
