<?php

declare(strict_types=1);

use App\Enums\SalesOrderStatus;
use App\Support\EnumOptions;
use Tests\TestCase;

// `label()` traduce con `__()`: hace falta la aplicación.
uses(TestCase::class);

enum TestEnumStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Sent => 'Enviado',
        };
    }
}

enum TestEnumNoLabel: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

it('maps enum cases to value label arrays', function () {
    $result = EnumOptions::for(TestEnumStatus::cases());

    expect($result)->toBe([
        ['value' => 'draft', 'label' => 'Borrador'],
        ['value' => 'sent', 'label' => 'Enviado'],
    ]);
});

it('falls back to value string when label method is missing', function () {
    $result = EnumOptions::for(TestEnumNoLabel::cases());

    expect($result)->toBe([
        ['value' => 'active', 'label' => 'active'],
        ['value' => 'inactive', 'label' => 'inactive'],
    ]);
});

it('returns empty array for empty input', function () {
    $result = EnumOptions::for([]);

    expect($result)->toBe([]);
});

it('adds the tone of enums that implement HasTone', function () {
    $result = EnumOptions::for(SalesOrderStatus::cases());

    expect($result[0])->toBe(['value' => 'pending', 'label' => 'Pendiente', 'tone' => 'warning'])
        ->and(array_column($result, 'tone'))->toHaveCount(count(SalesOrderStatus::cases()));
});

it('leaves out the tone of enums without HasTone', function () {
    expect(EnumOptions::for(TestEnumStatus::cases())[0])->not->toHaveKey('tone');
});
