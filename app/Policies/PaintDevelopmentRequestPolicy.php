<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PaintDevelopmentRequestStatus;
use App\Enums\Permission;
use App\Models\PaintDevelopmentRequest;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOwnedRecords;

class PaintDevelopmentRequestPolicy
{
    use AuthorizesOwnedRecords;

    public function viewAny(User $user): bool
    {
        return $user->canAny([
            Permission::PaintDevelopmentRequestsViewOwn->value,
            Permission::PaintDevelopmentRequestsViewAll->value,
        ]);
    }

    public function view(User $user, PaintDevelopmentRequest $request): bool
    {
        return $this->canAccess($user, $request);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::PaintDevelopmentRequestsCreate->value);
    }

    public function update(User $user, PaintDevelopmentRequest $request): bool
    {
        return $request->status === PaintDevelopmentRequestStatus::Draft
            && $user->can(Permission::PaintDevelopmentRequestsEdit->value)
            && $this->canAccess($user, $request);
    }

    public function submit(User $user, PaintDevelopmentRequest $request): bool
    {
        return $request->status === PaintDevelopmentRequestStatus::Draft
            && $user->can(Permission::PaintDevelopmentRequestsSubmit->value)
            && $this->canAccess($user, $request);
    }

    public function exportPdf(User $user, PaintDevelopmentRequest $request): bool
    {
        return $user->can(Permission::PaintDevelopmentRequestsExportPdf->value)
            && $this->canAccess($user, $request);
    }

    public function updateStatus(User $user, PaintDevelopmentRequest $request): bool
    {
        return $user->can(Permission::PaintDevelopmentRequestsUpdateStatus->value)
            && $this->canAccess($user, $request);
    }

    private function canAccess(User $user, PaintDevelopmentRequest $request): bool
    {
        return $this->canAccessOwnedRecord(
            $user,
            $request->created_by,
            Permission::PaintDevelopmentRequestsViewAll,
            Permission::PaintDevelopmentRequestsViewOwn,
        );
    }
}
