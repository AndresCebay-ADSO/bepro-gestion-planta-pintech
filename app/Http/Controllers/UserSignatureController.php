<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sirve la imagen de la firma desde el disco privado. Antes estaba en el disco público y cualquiera con la URL la veía
 * sin iniciar sesión.
 */
class UserSignatureController extends Controller
{
    public function __invoke(User $user): StreamedResponse
    {
        $disk = Storage::disk(User::SIGNATURE_DISK);

        abort_if($user->signature_path === null || ! $disk->exists($user->signature_path), 404);

        return $disk->response($user->signature_path, null, [
            // Privada: ni proxies ni CDN la guardan; el navegador sí, y `v` en la URL invalida la caché al cambiarla.
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
