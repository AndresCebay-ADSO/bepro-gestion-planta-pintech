<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOwnedRecords;

class SalesOrderPolicy
{
    use AuthorizesOwnedRecords;

    public function viewAny(User $user): bool
    {
        return $user->canAny([Permission::SalesOrdersViewOwn->value, Permission::SalesOrdersViewAll->value]);
    }

    public function view(User $user, SalesOrder $salesOrder): bool
    {
        return $this->canAccess($user, $salesOrder);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::SalesOrdersCreate->value);
    }

    /**
     * Datos del pedido (contacto, dirección, prioridad, fechas, notas): solo mientras está pendiente.
     */
    public function edit(User $user, SalesOrder $salesOrder): bool
    {
        return $salesOrder->status === SalesOrderStatus::Pending
            && $user->can(Permission::SalesOrdersEdit->value)
            && $this->canAccess($user, $salesOrder);
    }

    public function updateStatus(User $user, SalesOrder $salesOrder): bool
    {
        return $salesOrder->status->nextTransitions() !== []
            && $user->can(Permission::SalesOrdersUpdateStatus->value)
            && $this->canAccess($user, $salesOrder);
    }

    private function canAccess(User $user, SalesOrder $salesOrder): bool
    {
        return $this->canAccessOwnedRecord(
            $user,
            $salesOrder->created_by,
            Permission::SalesOrdersViewAll,
            Permission::SalesOrdersViewOwn,
        );
    }
}
