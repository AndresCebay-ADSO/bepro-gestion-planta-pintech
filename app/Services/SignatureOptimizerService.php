<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;

class SignatureOptimizerService
{
    private const int MAX_WIDTH = 400;

    private const int MAX_HEIGHT = 200;

    /**
     * Redimensiona una imagen de firma a dimensiones optimas para PDF.
     * Si la imagen ya esta dentro de los limites, la convierte a PNG.
     *
     * @return UploadedFile El archivo optimizado listo para almacenar
     */
    public function optimize(UploadedFile $file): UploadedFile
    {
        $sourcePath = $file->getRealPath();
        $mimeType = $file->getMimeType();

        $previousLimit = ini_get('memory_limit');
        ini_set('memory_limit', '512M');

        try {
            $sourceImage = $this->createImageFromFile($sourcePath, $mimeType);

            if ($sourceImage === false) {
                throw new \RuntimeException('No se pudo procesar la imagen de firma.');
            }

            $originalWidth = (int) imagesx($sourceImage);
            $originalHeight = (int) imagesy($sourceImage);

            if ($originalWidth <= self::MAX_WIDTH && $originalHeight <= self::MAX_HEIGHT) {
                imagedestroy($sourceImage);

                return $file;
            }

            $ratio = min(self::MAX_WIDTH / $originalWidth, self::MAX_HEIGHT / $originalHeight);
            $newWidth = (int) round($originalWidth * $ratio);
            $newHeight = (int) round($originalHeight * $ratio);

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

            imagepng($resizedImage, $tempPath, 6);
            imagedestroy($resizedImage);

            return new UploadedFile(
                $tempPath,
                $file->getClientOriginalName(),
                'image/png',
                null,
                true
            );
        } finally {
            ini_set('memory_limit', $previousLimit);
        }
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
