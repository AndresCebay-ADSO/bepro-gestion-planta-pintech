<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\QrCode;
use App\Models\User;

class QrCodePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::QrCodesView->value);
    }

    public function view(User $user, QrCode $qrCode): bool
    {
        return $user->can(Permission::QrCodesView->value);
    }

    public function update(User $user, ?QrCode $qrCode = null): bool
    {
        return $user->can(Permission::QrCodesUpdate->value);
    }
}
