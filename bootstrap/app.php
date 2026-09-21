<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (SymfonyResponse $response, Throwable $e, Request $request) {
            if (
                $response->getStatusCode() === 429 &&
                $request->header('X-Inertia')
            ) {
                return back()->with([
                    'error' => __('auth.throttle', ['seconds' => 60]),
                ]);
            }

            // Páginas de error con Inertia. 500 y 503 conservan la traza de Laravel mientras APP_DEBUG está activo.
            // 419: la sesión o el token CSRF expiraron. Un `back()` perdería el aviso: si la sesión expiró, el usuario ya
            // no está autenticado y la página anterior redirige al login, que consume el mensaje flash.
            $status = $response->getStatusCode();
            $rendersErrorPage = in_array($status, [403, 404, 419], true)
                || (in_array($status, [500, 503], true) && ! config('app.debug'));

            if ($rendersErrorPage && ($request->header('X-Inertia') || ! $request->expectsJson())) {
                try {
                    return Inertia::render('ErrorPage', ['status' => $status])
                        ->toResponse($request)
                        ->setStatusCode($status);
                } catch (Throwable) {
                    // Si la página de error también falla, se entrega la respuesta original de Laravel.
                    return $response;
                }
            }

            return $response;
        });
    })->create();
