<?php

namespace App\Enums;

enum QrDocumentType: string
{
    case FichaTecnica = 'ficha_tecnica';
    case FichaSeguridad = 'ficha_seguridad';
    case CertificadoCalidad = 'certificado_calidad';

    public function label(): string
    {
        return match ($this) {
            self::FichaTecnica => __('Ficha técnica'),
            self::FichaSeguridad => __('Ficha de seguridad'),
            self::CertificadoCalidad => __('Certificado de calidad'),
        };
    }
}
