<?php

declare(strict_types=1);

namespace App\Enums;

enum QrDocumentType: string
{
    case TechnicalDataSheet = 'technical_data_sheet';
    case SafetyDataSheet = 'safety_data_sheet';
    case QualityCertificate = 'quality_certificate';

    /**
     * Reglas del PDF que se sube, sea documento de producto o certificado de un lote.
     *
     * @return array<int, string>
     */
    public static function fileRules(): array
    {
        return ['required', 'file', 'mimes:pdf', 'extensions:pdf', 'max:10240'];
    }

    public function label(): string
    {
        return match ($this) {
            self::TechnicalDataSheet => __('Ficha técnica'),
            self::SafetyDataSheet => __('Hoja de seguridad'),
            self::QualityCertificate => __('Certificado de calidad'),
        };
    }
}
