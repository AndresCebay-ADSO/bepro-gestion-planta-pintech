<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * Una cola considera perdido un job reservado hace más de `retry_after` segundos y lo entrega a otro worker. Si un job
 * puede tardar más que eso, se ejecutaría dos veces a la vez. Se comprueban todas las conexiones que definen
 * `retry_after`, no solo la activa, para que cambiar `QUEUE_CONNECTION` no reabra el problema.
 */
it('da a cada cola un retry_after mayor que el timeout de cualquier job', function () {
    $timeouts = collect(File::files(app_path('Jobs')))
        ->mapWithKeys(function (SplFileInfo $file): array {
            $class = 'App\\Jobs\\'.$file->getBasename('.php');

            return [$class => (new ReflectionClass($class))->getDefaultProperties()['timeout'] ?? null];
        })
        ->filter();

    $retryAfters = collect(config('queue.connections'))
        ->filter(fn (array $connection): bool => isset($connection['retry_after']))
        ->map(fn (array $connection): int => (int) $connection['retry_after']);

    expect($timeouts)->not->toBeEmpty()
        ->and($retryAfters)->toHaveKeys(['database', 'redis', 'beanstalkd']);

    foreach ($retryAfters as $connection => $retryAfter) {
        foreach ($timeouts as $class => $timeout) {
            expect($retryAfter)->toBeGreaterThan($timeout, "{$class} puede tardar {$timeout} s y retry_after de '{$connection}' es {$retryAfter} s.");
        }
    }
});
