<?php

declare(strict_types=1);

// Los avisos de éxito y error viajan por el canal flash nativo de Inertia 3 (`Inertia::flash()`),
// nunca en la sesión de Laravel (`->with()` o `session()->flash()`).
test('los avisos flash en app/ y bootstrap/ usan Inertia::flash() y no ->with() ni session()->flash()', function () {
    $directories = [base_path('app'), base_path('bootstrap')];
    $patterns = [
        '/->with\(\s*[\'"](?:success|error)[\'"]/s',
        '/->with\(\s*\[[^\]]*[\'"](?:success|error)[\'"]\s*=>/s',
        '/session\(\)->flash\(\s*[\'"](?:success|error)[\'"]/s',
        '/Session::flash\(\s*[\'"](?:success|error)[\'"]/s',
    ];

    $violations = [];
    $checked = 0;

    foreach ($directories as $directory) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $checked++;
            $contents = (string) file_get_contents($file->getPathname());

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $contents) === 1) {
                    $relativePath = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $violations[] = "{$relativePath}: usa Inertia::flash('success'|'error', ...) en lugar de ->with() o session()->flash().";
                    break;
                }
            }
        }
    }

    expect($checked)->toBeGreaterThan(0)
        ->and($violations)->toBe(
            [],
            "Usa Inertia::flash() para los avisos de éxito y error:\n".implode(PHP_EOL, $violations)
        );
});
