<?php

declare(strict_types=1);

namespace App\Enums\Contracts;

use App\Enums\Tone;

/**
 * Enum que se muestra como pastilla de color. Quien la implementa declara `tone()` con un `match` sin `default`, para
 * que un caso nuevo sin tono falle en `ToneEnumsTest`, y se registra en `App\Support\ToneEnums::ALL` para llegar al
 * front en la prop compartida `enums`.
 */
interface HasTone
{
    public function label(): string;

    public function tone(): Tone;
}
