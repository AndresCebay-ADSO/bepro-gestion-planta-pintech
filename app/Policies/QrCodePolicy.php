<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\QrCode;
use App\Models\User;

class QrCodePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'produccion']);
    }

    public function view(User $user, QrCode $qrCode): bool
    {
        return $user->hasAnyRole(['admin', 'produccion']);
    }

    public function update(User $user, ?QrCode $qrCode = null): bool
    {
        return $user->hasAnyRole(['admin', 'produccion']);
    }

    public function delete(User $user, QrCode $qrCode): bool
    {
        return $user->hasRole('admin');
    }
}
