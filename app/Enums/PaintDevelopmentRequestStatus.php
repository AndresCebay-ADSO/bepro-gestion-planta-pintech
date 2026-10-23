<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\HasTone;

enum PaintDevelopmentRequestStatus: string implements HasTone
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Borrador'),
            self::Submitted => __('Enviada'),
            self::InReview => __('En revisión'),
            self::Approved => __('Aprobada'),
            self::Rejected => __('Rechazada'),
        };
    }

    public function tone(): Tone
    {
        return match ($this) {
            self::Draft => Tone::Neutral,
            self::Submitted => Tone::Info,
            self::InReview => Tone::Warning,
            self::Approved => Tone::Success,
            self::Rejected => Tone::Danger,
        };
    }

    /**
     * Devuelve los estados a los que se puede transicionar desde el estado actual.
     *
     * @return array<int, self>
     */
    public function nextTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted, self::Rejected],
            self::Submitted => [self::InReview, self::Rejected],
            self::InReview => [self::Approved, self::Rejected],
            self::Approved => [],
            self::Rejected => [],
        };
    }
}
