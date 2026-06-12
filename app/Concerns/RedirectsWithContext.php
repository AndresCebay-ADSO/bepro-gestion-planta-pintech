<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait RedirectsWithContext
{
    protected function redirectWithContext(
        Request $request,
        string $defaultRoute,
        array $defaultParameters = [],
        ?string $flashKey = 'success',
        ?string $flashMessage = null,
    ): RedirectResponse {
        $returnTo = $this->resolveReturnTo($request);

        $redirect = $returnTo !== null
            ? redirect($returnTo)
            : redirect()->route($defaultRoute, $defaultParameters);

        if ($flashKey !== null && $flashMessage !== null) {
            $redirect->with($flashKey, $flashMessage);
        }

        return $redirect;
    }

    protected function resolveReturnTo(Request $request): ?string
    {
        $returnTo = $request->input('return_to');

        if (! is_string($returnTo) || $returnTo === '') {
            return null;
        }

        $path = $this->extractInternalPath($returnTo);

        if ($path === null || ! $this->isSafeInternalPath($path)) {
            return null;
        }

        return $path;
    }

    protected function extractInternalPath(string $returnTo): ?string
    {
        if (str_starts_with($returnTo, '/') && ! str_starts_with($returnTo, '//')) {
            return $returnTo;
        }

        if (! str_contains($returnTo, '://')) {
            return null;
        }

        $parsed = parse_url($returnTo);

        if ($parsed === false || ! isset($parsed['path'])) {
            return null;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if ($appHost !== null && isset($parsed['host']) && $parsed['host'] !== $appHost) {
            return null;
        }

        $path = $parsed['path'];

        if (isset($parsed['query']) && $parsed['query'] !== '') {
            $path .= '?'.$parsed['query'];
        }

        return $path;
    }

    protected function isSafeInternalPath(string $path): bool
    {
        if (str_contains($path, '\\')) {
            return false;
        }

        $pathComponent = str_contains($path, '?')
            ? substr($path, 0, (int) strpos($path, '?'))
            : $path;

        if (! str_starts_with($pathComponent, '/')) {
            return false;
        }

        if (str_starts_with($pathComponent, '//')) {
            return false;
        }

        return ! str_contains($pathComponent, '://');
    }
}
