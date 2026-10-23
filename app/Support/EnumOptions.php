<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Contracts\HasTone;

final class EnumOptions
{
    /**
     * Opciones para selects y filtros. Los enums con tono (`HasTone`) traen también `tone`, para pintar la opción igual
     * que su pastilla.
     *
     * @param  array<int, \BackedEnum>  $cases
     * @return array<int, array{value: string, label: string, tone?: string}>
     */
    public static function for(array $cases): array
    {
        return array_map(
            fn (\BackedEnum $case) => [
                'value' => (string) $case->value,
                'label' => method_exists($case, 'label') ? $case->label() : (string) $case->value,
                ...($case instanceof HasTone ? ['tone' => $case->tone()->value] : []),
            ],
            $cases
        );
    }
}
