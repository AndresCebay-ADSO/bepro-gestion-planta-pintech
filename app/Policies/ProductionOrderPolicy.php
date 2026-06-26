<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ProductionOrderStatus;
use App\Models\ProductionOrder;
use App\Models\User;

class ProductionOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'produccion', 'comercial', 'operador']);
    }

    public function view(User $user, ProductionOrder $productionOrder): bool
    {
        return $user->hasAnyRole(['admin', 'produccion', 'comercial', 'operador']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'produccion']);
    }

    public function update(User $user, ProductionOrder $productionOrder): bool
    {
        return $user->hasAnyRole(['admin', 'produccion', 'operador']);
    }

    public function updateOperationalData(User $user, ProductionOrder $productionOrder): bool
    {
        if (! $user->hasAnyRole(['admin', 'produccion', 'operador'])) {
            return false;
        }

        if (in_array($productionOrder->status, [
            ProductionOrderStatus::Pending,
            ProductionOrderStatus::Completed,
            ProductionOrderStatus::Cancelled,
        ], true)) {
            return false;
        }

        if ($productionOrder->status === ProductionOrderStatus::PendingReview && $user->hasRole('operador')) {
            return false;
        }

        return true;
    }

    public function complete(User $user, ProductionOrder $productionOrder): bool
    {
        if (! $user->hasAnyRole(['admin', 'produccion'])) {
            return false;
        }

        if (in_array($productionOrder->status, [
            ProductionOrderStatus::Completed,
            ProductionOrderStatus::Cancelled,
        ], true)) {
            return false;
        }

        return in_array($productionOrder->status, [
            ProductionOrderStatus::InProgress,
            ProductionOrderStatus::PendingReview,
        ], true);
    }

    public function startProduction(User $user, ProductionOrder $productionOrder): bool
    {
        if (! $user->hasAnyRole(['admin', 'produccion', 'operador'])) {
            return false;
        }

        return $productionOrder->status === ProductionOrderStatus::Pending;
    }

    public function submitForReview(User $user, ProductionOrder $productionOrder): bool
    {
        if (! $user->hasRole('operador')) {
            return false;
        }

        return $productionOrder->status === ProductionOrderStatus::InProgress;
    }

    public function rejectReview(User $user, ProductionOrder $productionOrder): bool
    {
        if (! $user->hasAnyRole(['admin', 'produccion'])) {
            return false;
        }

        return $productionOrder->status === ProductionOrderStatus::PendingReview;
    }

    public function previewCosts(User $user, ProductionOrder $productionOrder): bool
    {
        return $user->hasAnyRole(['admin', 'produccion']);
    }

    public function delete(User $user, ProductionOrder $productionOrder): bool
    {
        return $user->hasRole('admin');
    }
}
