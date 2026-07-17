<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Models\User;

class QuotationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'comercial']);
    }

    public function view(User $user, Quotation $quotation): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('comercial') && $quotation->created_by === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'comercial']);
    }

    public function update(User $user, Quotation $quotation): bool
    {
        if ($quotation->status !== QuotationStatus::Draft) {
            return false;
        }

        return $user->hasRole('admin')
            || ($user->hasRole('comercial') && $quotation->created_by === $user->id);
    }

    public function delete(User $user, Quotation $quotation): bool
    {
        return $user->hasRole('admin') && $quotation->status === QuotationStatus::Draft;
    }

    public function exportPdf(User $user, Quotation $quotation): bool
    {
        return $this->view($user, $quotation);
    }

    public function updateStatus(User $user, Quotation $quotation): bool
    {
        return $user->hasRole('admin')
            || ($user->hasRole('comercial') && $quotation->created_by === $user->id);
    }
}
