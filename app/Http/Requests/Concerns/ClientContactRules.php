<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\Client;

/**
 * NIT y teléfono de contacto de un cliente: una sola regla para el cliente, la cotización, el pedido y el usuario.
 * Antes la cotización aceptaba 30 caracteres y el pedido 20, y un pedido convertido con un NIT de 25 ya no se podía
 * guardar (auditoría 3.6, PV-02 y D-PV2).
 */
trait ClientContactRules
{
    /**
     * @return list<string>
     */
    protected function nitRules(): array
    {
        return ['nullable', 'string', 'max:'.Client::NIT_MAX_LENGTH];
    }

    /**
     * El mensaje del `regex` vive en `lang/es/validation.php` (`custom.phone.regex` y `custom.client_phone.regex`).
     *
     * @return list<mixed>
     */
    protected function phoneRules(): array
    {
        return ['nullable', 'string', 'regex:'.Client::PHONE_PATTERN];
    }
}
