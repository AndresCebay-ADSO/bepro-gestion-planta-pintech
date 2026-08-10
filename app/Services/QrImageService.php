<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\QrCode;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\QrCode as QrCodeImage;
use Endroid\QrCode\Writer\PngWriter;

class QrImageService
{
    public function generatePng(QrCode $qrCode): string
    {
        $url = route('qr.public.show', ['token' => $qrCode->token]);

        $image = new QrCodeImage(
            data: $url,
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 300,
            margin: 10,
        );

        $logoPath = public_path('images/beprologoqr.png');
        $logo = file_exists($logoPath)
            ? new Logo(path: $logoPath, resizeToWidth: 130, punchoutBackground: false)
            : null;

        return (new PngWriter)->write($image, $logo)->getString();
    }
}
