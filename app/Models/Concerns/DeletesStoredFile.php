<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * El archivo se borra solo si el borrado del registro se confirma: si la transacción se revierte, el documento sigue
 * intacto (docs/POLITICA_ELIMINACION.md §3.4).
 *
 * @property string $file_path
 */
trait DeletesStoredFile
{
    protected static function bootDeletesStoredFile(): void
    {
        static::deleted(function (self $document): void {
            $path = $document->file_path;

            DB::afterCommit(fn () => Storage::disk('local')->delete($path));
        });
    }
}
