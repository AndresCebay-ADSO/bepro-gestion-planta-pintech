<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Models\User;
use App\Policies\Concerns\AuthorizesOwnedRecords;

class QuotationPolicy
{
    use AuthorizesOwnedRecords;

    public function viewAny(User $user): bool
    {
        return $user->canAny([Permission::QuotationsViewOwn->value, Permission::QuotationsViewAll->value]);
    }

    public function view(User $user, Quotation $quotation): bool
    {
        return $this->canAccess($user, $quotation);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::QuotationsCreate->value);
    }

    public function update(User $user, Quotation $quotation): bool
    {
        return $quotation->status === QuotationStatus::Draft
            && $user->can(Permission::QuotationsEdit->value)
            && $this->canAccess($user, $quotation);
    }

    public function exportPdf(User $user, Quotation $quotation): bool
    {
        return $user->can(Permission::QuotationsExportPdf->value)
            && $this->canAccess($user, $quotation);
    }

    public function updateStatus(User $user, Quotation $quotation): bool
    {
        return $quotation->convert_to_order_id === null
            && $user->can(Permission::QuotationsUpdateStatus->value)
            && $this->canAccess($user, $quotation);
    }

    /**
     * Convertir crea un pedido de venta: exige también sales_orders.create.
     */
    public function convertToOrder(User $user, Quotation $quotation): bool
    {
        return $quotation->status === QuotationStatus::Accepted
            && $quotation->convert_to_order_id === null
            && $user->can(Permission::QuotationsConvertToOrder->value)
            && $user->can(Permission::SalesOrdersCreate->value)
            && $this->canAccess($user, $quotation);
    }

    private function canAccess(User $user, Quotation $quotation): bool
    {
        return $this->canAccessOwnedRecord(
            $user,
            $quotation->created_by,
            Permission::QuotationsViewAll,
            Permission::QuotationsViewOwn,
        );
    }
}
