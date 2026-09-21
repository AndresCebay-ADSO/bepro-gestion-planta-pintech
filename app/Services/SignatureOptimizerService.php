<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class SignatureOptimizerService
{
    private const int MAX_WIDTH = 400;

    private const int MAX_HEIGHT = 200;

    /**
     * Optimiza y almacena una imagen de firma en el disco especificado.
     *
     * @throws ValidationException
     */
    public function optimizeAndStore(
        UploadedFile $file,
        string $directory = 'signatures',
        string $disk = User::SIGNATURE_DISK,
    ): string {
        $optimized = null;

        try {
            $optimized = $this->optimize($file);

            $path = $optimized->store($directory, $disk);

            if ($path === false) {
                throw new \RuntimeException('No se pudo guardar la imagen de firma en el disco.');
            }

            return $path;
        } catch (\Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'signature' => 'No se pudo procesar la imagen de firma. Asegúrate de que sea un archivo de imagen válido.',
            ]);
        } finally {
            if ($optimized !== null && $optimized !== $file && file_exists($optimized->getRealPath())) {
                @unlink($optimized->getRealPath());
            }
        }
    }

    /**
     * Redimensiona una imagen de firma a dimensiones optimas para PDF.
     * Si la imagen ya esta dentro de los limites pero no es PNG, la convierte a PNG.
     *
     * @return UploadedFile El archivo optimizado listo para almacenar
     */
    public function optimize(UploadedFile $file): UploadedFile
    {
        $sourcePath = $file->getRealPath();
        $mimeType = $file->getMimeType();

        $previousLimit = ini_get('memory_limit');
        $this->ensureAdequateMemoryLimit();

        try {
            $sourceImage = $this->createImageFromFile($sourcePath, $mimeType);

            if ($sourceImage === false) {
                throw new \RuntimeException('No se pudo procesar la imagen de firma.');
            }

            $originalWidth = (int) imagesx($sourceImage);
            $originalHeight = (int) imagesy($sourceImage);

            if ($mimeType === 'image/png' && $originalWidth <= self::MAX_WIDTH && $originalHeight <= self::MAX_HEIGHT) {
                imagedestroy($sourceImage);

                return $file;
            }

            $ratio = min(
                self::MAX_WIDTH / max(1, $originalWidth),
                self::MAX_HEIGHT / max(1, $originalHeight)
            );
            $newWidth = (int) max(1, round($originalWidth * min(1.0, $ratio)));
            $newHeight = (int) max(1, round($originalHeight * min(1.0, $ratio)));

            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

            if ($resizedImage === false) {
                imagedestroy($sourceImage);
                throw new \RuntimeException('No se pudo crear el canvas de redimensionamiento.');
            }

            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);

            imagecopyresampled(
                $resizedImage,
                $sourceImage,
                0,
                0,
                0,
                0,
                $newWidth,
                $newHeight,
                $originalWidth,
                $originalHeight
            );

            imagedestroy($sourceImage);

            $tempPath = tempnam(sys_get_temp_dir(), 'signature_');

            if ($tempPath === false) {
                imagedestroy($resizedImage);
                throw new \RuntimeException('No se pudo crear archivo temporal para la firma optimizada.');
            }

            try {
                if (! imagepng($resizedImage, $tempPath, 6)) {
                    throw new \RuntimeException('No se pudo escribir la imagen optimizada en el archivo temporal.');
                }
            } catch (\Throwable $e) {
                @unlink($tempPath);
                imagedestroy($resizedImage);
                throw $e;
            }

            imagedestroy($resizedImage);

            return new UploadedFile(
                $tempPath,
                pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME).'.png',
                'image/png',
                null,
                true
            );
        } finally {
            if ($previousLimit !== false) {
                ini_set('memory_limit', $previousLimit);
            }
        }
    }

    /**
     * Ajusta el memory_limit solo si es inferior a 512M y no es ilimitado.
     */
    private function ensureAdequateMemoryLimit(): void
    {
        $currentLimit = ini_get('memory_limit');
        if ($currentLimit === '-1' || $currentLimit === false || $currentLimit === '') {
            return;
        }

        $bytes = $this->parseMemoryLimitToBytes((string) $currentLimit);
        if ($bytes > 0 && $bytes < 536870912) {
            ini_set('memory_limit', '512M');
        }
    }

    /**
     * Convierte una notación de límite de memoria (ej. '512M', '1G') a bytes.
     */
    private function parseMemoryLimitToBytes(string $limit): int
    {
        $limit = trim($limit);
        if ($limit === '' || $limit === '-1') {
            return -1;
        }

        $last = strtolower($limit[strlen($limit) - 1]);
        $val = (int) $limit;

        return match ($last) {
            'g' => $val * 1024 * 1024 * 1024,
            'm' => $val * 1024 * 1024,
            'k' => $val * 1024,
            default => $val,
        };
    }

    /**
     * Crea un recurso GD desde un archivo segun su mime type.
     */
    private function createImageFromFile(string $path, string $mimeType): \GdImage|false
    {
        return match ($mimeType) {
            'image/jpeg', 'image/jpg' => imagecreatefromjpeg($path),
            'image/png' => imagecreatefrompng($path),
            'image/gif' => imagecreatefromgif($path),
            'image/webp' => imagecreatefromwebp($path),
            default => false,
        };
    }
}
