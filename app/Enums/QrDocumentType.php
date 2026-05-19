<?php

namespace App\Enums;

enum QrDocumentType: string
{
    case TechnicalDataSheet = 'technical_data_sheet';
    case SafetyDataSheet = 'safety_data_sheet';
    case QualityCertificate = 'quality_certificate';

    public function label(): string
    {
        return match ($this) {
            self::TechnicalDataSheet => __('Ficha técnica'),
            self::SafetyDataSheet => __('Hoja de seguridad'),
            self::QualityCertificate => __('Certificado de calidad'),
        };
    }
}
