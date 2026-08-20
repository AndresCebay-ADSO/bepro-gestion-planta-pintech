<?php

declare(strict_types=1);

namespace App\Support;

final class EnumOptions
{
    /**
     * @param  array<int, \BackedEnum>  $cases
     * @return array<int, array{value: string, label: string}>
     */
    public static function for(array $cases): array
    {
        return array_map(
            fn (\BackedEnum $case) => [
                'value' => (string) $case->value,
                'label' => method_exists($case, 'label') ? $case->label() : (string) $case->value,
            ],
            $cases
        );
    }
}
