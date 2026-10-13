<?php

declare(strict_types=1);

namespace App\Support;

final class ImageDataUri
{
    /**
     * Imagen local como data URI, para incrustarla en un PDF de DomPDF sin que tenga que leer archivos.
     * Devuelve null si el archivo no existe, y la plantilla muestra su texto de respaldo.
     */
    public static function fromFile(string $path): ?string
    {
        if (! file_exists($path)) {
            return null;
        }

        $mimeType = mime_content_type($path) ?: 'image/png';

        return 'data:'.$mimeType.';base64,'.base64_encode((string) file_get_contents($path));
    }
}
