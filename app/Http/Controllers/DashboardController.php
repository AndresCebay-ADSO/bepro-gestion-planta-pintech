<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Redirect;

class DashboardController extends Controller
{
    /**
     * Redirige al dashboard según el rol del usuario.
     */
    public function redirect()
    {
        $userRole = auth()->user()->getRoleNames()->first();

        return match ($userRole) {
            'admin' => Redirect::route('admin.index'),
            'produccion' => Redirect::route('production.index'),
            'comercial' => Redirect::route('availability.index'),
            'operador' => Redirect::route('operator.index'),
            default => Redirect::route('admin.index'), // fallback
        };
    }
}
