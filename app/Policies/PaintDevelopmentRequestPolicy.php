<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PaintDevelopmentRequestStatus;
use App\Models\PaintDevelopmentRequest;
use App\Models\User;

class PaintDevelopmentRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'produccion', 'comercial']);
    }

    public function view(User $user, PaintDevelopmentRequest $request): bool
    {
        return $user->hasAnyRole(['admin', 'produccion'])
            || ($user->hasRole('comercial') && $request->created_by === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'comercial']);
    }

    public function update(User $user, PaintDevelopmentRequest $request): bool
    {
        if ($request->status !== PaintDevelopmentRequestStatus::Draft) {
            return false;
        }

        return $user->hasRole('admin')
            || ($user->hasRole('comercial') && $request->created_by === $user->id);
    }

    public function exportPdf(User $user, PaintDevelopmentRequest $request): bool
    {
        return $this->view($user, $request);
    }

    public function updateStatus(User $user, PaintDevelopmentRequest $request): bool
    {
        return $user->hasAnyRole(['admin', 'produccion']);
    }
}
