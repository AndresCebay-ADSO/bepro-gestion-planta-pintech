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

/** Simula el arranque de la aplicación en otro entorno. */
function bootIn(string $environment, bool $allowDestructive = false): void
{
    app()->detectEnvironment(fn () => $environment);
    config(['app.allow_destructive_commands' => $allowDestructive]);
    DeploymentEnvironment::applyProtections();
}

afterEach(function (): void {
    // El bloqueo es estático y sobrevive a la aplicación de cada prueba: se deja como en `testing`.
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

it('la variable temporal permite resetear staging, pero nunca producción', function () {
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
