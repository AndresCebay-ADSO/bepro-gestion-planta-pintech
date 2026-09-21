<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * La cola de base de datos considera perdido un job reservado hace más de `retry_after` segundos y lo entrega a otro
 * worker. Si un job puede tardar más que eso, se ejecutaría dos veces a la vez.
 */
it('da a la cola un retry_after mayor que el timeout de cualquier job', function () {
    $retryAfter = (int) config('queue.connections.database.retry_after');

    $timeouts = collect(File::files(app_path('Jobs')))
        ->mapWithKeys(function (SplFileInfo $file): array {
            $class = 'App\\Jobs\\'.$file->getBasename('.php');

            return [$class => (new ReflectionClass($class))->getDefaultProperties()['timeout'] ?? null];
        })
        ->filter();

    expect($timeouts)->not->toBeEmpty();

    foreach ($timeouts as $class => $timeout) {
        expect($retryAfter)->toBeGreaterThan($timeout, "{$class} puede tardar {$timeout} s y retry_after es {$retryAfter} s.");
    }
});
