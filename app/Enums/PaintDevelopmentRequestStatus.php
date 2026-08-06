<?php

declare(strict_types=1);

namespace App\Enums;

enum PaintDevelopmentRequestStatus: string
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
