<?php

declare(strict_types=1);

namespace App\Enums;

enum QuotationItemType: string
{
    case Primer = 'primer';
    case SelfPriming = 'self_priming';
    case Topcoat = 'topcoat';
    case IntermediateCoat = 'intermediate_coat';
    case Reducer = 'reducer';

    public function label(): string
    {
        return match ($this) {
            self::Primer => __('Imprimante'),
            self::SelfPriming => __('Auto-imprimante'),
            self::Topcoat => __('Acabado'),
            self::IntermediateCoat => __('Barrera'),
            self::Reducer => __('Ajustador'),
        };
    }
}
