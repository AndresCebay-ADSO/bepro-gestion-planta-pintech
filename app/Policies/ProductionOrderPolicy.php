<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\ProductionOrderStatus;
use App\Models\ProductionOrder;
use App\Models\User;

/**
 * Cada habilidad combina un permiso (docs/MATRIZ_RBAC.md) con la regla de estado de la orden.
 */
class ProductionOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ProductionOrdersView->value);
    }

    public function view(User $user, ProductionOrder $productionOrder): bool
    {
        return $user->can(Permission::ProductionOrdersView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ProductionOrdersCreate->value);
    }

    /**
     * Ajustes de línea, plan de empaque y consumo de remanentes.
     */
    public function updateOperationalData(User $user, ProductionOrder $productionOrder): bool
    {
        if (! $user->can(Permission::ProductionOrdersOperate->value)) {
            return false;
        }

        return match ($productionOrder->status) {
            ProductionOrderStatus::InProgress => true,
            // En revisión solo sigue operando quien puede revisar la orden (docs/MATRIZ_RBAC.md §4).
            ProductionOrderStatus::PendingReview => $user->can(Permission::ProductionOrdersComplete->value),
            default => false,
        };
    }

    public function startProduction(User $user, ProductionOrder $productionOrder): bool
    {
        return $productionOrder->status === ProductionOrderStatus::Pending
            && $user->can(Permission::ProductionOrdersOperate->value);
    }

    public function submitForReview(User $user, ProductionOrder $productionOrder): bool
    {
        return $productionOrder->status === ProductionOrderStatus::InProgress
            && $user->can(Permission::ProductionOrdersSubmitForReview->value);
    }

    public function rejectReview(User $user, ProductionOrder $productionOrder): bool
    {
        return $productionOrder->status === ProductionOrderStatus::PendingReview
            && $user->can(Permission::ProductionOrdersRejectReview->value);
    }

    public function complete(User $user, ProductionOrder $productionOrder): bool
    {
        return in_array($productionOrder->status, [
            ProductionOrderStatus::InProgress,
            ProductionOrderStatus::PendingReview,
        ], true)
            && $user->can(Permission::ProductionOrdersComplete->value);
    }

    /**
     * Los estados cancelables los valida CancelProductionOrderAction, que devuelve un mensaje explicativo.
     */
    public function cancel(User $user, ProductionOrder $productionOrder): bool
    {
        return $user->can(Permission::ProductionOrdersCancel->value);
    }

    /**
     * Costos de la orden: vista previa, PDF y Excel.
     */
    public function previewCosts(User $user, ProductionOrder $productionOrder): bool
    {
        return $user->can(Permission::CostsView->value);
    }
}
