<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Respuesta de una escritura en la OP desde un formulario pequeño o un diálogo (presentación, ajuste, saldo, color,
 * devolver a planta) que perdió la carrera: la orden cambió de estado mientras esperaba el bloqueo. Va como aviso, para que se vea, y también como error de validación: un
 * redirect sin errores Inertia lo toma como éxito, y el formulario borraba lo que el usuario había escrito.
 */
trait RejectsStaleOrderWrites
{
    protected function staleOrderResponse(\DomainException $exception): RedirectResponse
    {
        Inertia::flash('error', $exception->getMessage());

        return back()
            ->withErrors(['production_order' => $exception->getMessage()]);
    }
}
