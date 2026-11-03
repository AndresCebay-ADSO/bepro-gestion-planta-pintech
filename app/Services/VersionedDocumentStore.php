<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\QrDocumentType;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Único lugar donde nace la versión de un documento (de un producto o del QR de un lote): la nueva queda vigente y la
 * anterior pasa al historial. Si algo falla no queda ni un registro sin archivo ni un archivo sin registro.
 */
class VersionedDocumentStore
{
    /**
     * @param  Model  $owner  Dueño de los documentos; se bloquea para que dos versiones no calculen el mismo número.
     * @param  string  $relation  Relación del dueño que guarda sus documentos.
     * @param  Closure(int): string  $pathFor  Ruta del archivo, según el número de versión.
     */
    public function store(
        Model $owner,
        string $relation,
        QrDocumentType $type,
        string $content,
        string $fileName,
        Closure $pathFor,
        int $userId,
    ): Model {
        $path = null;

        try {
            return DB::transaction(function () use ($owner, $relation, $type, $content, $fileName, $pathFor, $userId, &$path): Model {
                $lockedOwner = $owner->newQuery()->lockForUpdate()->findOrFail($owner->getKey());

                $version = ((int) $lockedOwner->{$relation}()
                    ->where('document_type', $type->value)
                    ->max('version')) + 1;

                $path = $pathFor($version);

                // El disco tiene `throw => false`: un fallo de escritura solo se ve en lo que devuelve `put`.
                if (! Storage::disk('local')->put($path, $content)) {
                    throw new \RuntimeException('No se pudo guardar el archivo del documento.');
                }

                $lockedOwner->{$relation}()
                    ->where('document_type', $type->value)
                    ->update(['is_current' => false]);

                return $lockedOwner->{$relation}()->create([
                    'document_type' => $type,
                    'file_name' => $fileName,
                    'file_path' => $path,
                    'file_size' => strlen($content),
                    'mime_type' => 'application/pdf',
                    'version' => $version,
                    'is_current' => true,
                    'uploaded_by' => $userId,
                ]);
            });
        } catch (Throwable $exception) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }
    }
}
