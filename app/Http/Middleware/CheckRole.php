<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Si no está autenticado, redirigir a login
        if (! $request->user()) {
            return redirect()->route('login');
        }

        // Verificar si el usuario tiene al menos uno de los roles requeridos
        if ($request->user()->hasAnyRole($roles)) {
            return $next($request);
        }

        // Si no tiene permisos, abortar con 403
        abort(403, 'No tienes permiso para acceder a este recurso.');
    }
}
