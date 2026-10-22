<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TransformsRequest;

/**
 * Quita de todo texto que llega (formularios, JSON y query) los caracteres de control que no se ven: NUL, campana,
 * retroceso, etc. Pegar desde Word, un PDF o Excel puede traerlos, y PostgreSQL recorta el texto en un NUL sin avisar
 * (`a\0b` se guardaba como `a`). Auditoría 3.6, INV-08 y OPC-08.
 *
 * - Conserva tabulador, salto de línea y retorno de carro: son parte del texto de una nota.
 * - Sin el modificador `/u` a propósito: con él, un UTF-8 inválido haría que `preg_replace` devuelva `null` y borre el
 *   campo. Estos bytes nunca aparecen dentro de una secuencia multibyte, así que el resto del texto queda intacto.
 * - No toca las contraseñas (`password`, `password_confirmation`, `current_password`): pueden llevar cualquier carácter
 *   y una limpieza cambiaría lo que el usuario escribió. Los archivos subidos no pasan por aquí.
 */
class StripControlCharacters extends TransformsRequest
{
    protected function transform($key, $value)
    {
        if (! is_string($value) || str_contains(strtolower((string) $key), 'password')) {
            return $value;
        }

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?? $value;
    }
}
