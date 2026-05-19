<?php

namespace App\Enums;

enum QrDocumentType: string
{
    case FichaTecnica = 'ficha_tecnica';
    case HojaSeguridad = 'hoja_seguridad';
    case CertificadoCalidad = 'certificado_calidad';

    public function label(): string
    {
        return match ($this) {
            self::FichaTecnica => __('Ficha técnica'),
            self::HojaSeguridad => __('Hoja de seguridad'),
            self::CertificadoCalidad => __('Certificado de calidad'),
        };
    }
}
