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

        // fileExists() y no exists(): una ruta vacía apunta a la carpeta raíz, y exists() la da por buena.
        abort_if(blank($user->signature_path) || ! $disk->fileExists($user->signature_path), 404);

        return $disk->response($user->signature_path, null, [
            // Sin caché: en planta los equipos son compartidos y la firma no debe quedar en el navegador al cerrar
            // sesión. La imagen pesa pocos KB.
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
