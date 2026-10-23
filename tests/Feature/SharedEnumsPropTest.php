<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Support\ToneEnums;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Prop compartida `enums` (ToneEnums): etiqueta y tono de cada estado para `<StatusBadge>`. Se envía una sola vez
 * (`Inertia::once`) y solo con sesión.
 */

it('shares the label and tone of every toned enum with a signed-in user', function (): void {
    actingAsRole(SystemRole::Operator);

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('enums.SalesOrderStatus.pending', ['label' => 'Pendiente', 'tone' => 'warning'])
            ->where('enums', ToneEnums::shared()));
});

it('does not share the enums with a guest', function (): void {
    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->missing('enums'));
});

it('does not resend the enums once the client has them', function (): void {
    actingAsRole(SystemRole::Admin);

    $response = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Except-Once-Props' => 'enums',
    ])->get(route('dashboard'));

    $response->assertOk();
    expect($response->json('props'))->not->toHaveKey('enums')
        ->and($response->json('onceProps'))->toHaveKey('enums');
});
