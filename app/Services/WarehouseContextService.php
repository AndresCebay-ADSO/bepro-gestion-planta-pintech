<?php

namespace App\Services;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection;

class WarehouseContextService
{
    /**
     * @return Collection<int, Warehouse>
     */
    public function availableWarehouses(User $user): Collection
    {
        if ($user->hasRole('admin')) {
            return Warehouse::query()
                ->select('id', 'name', 'city', 'is_active')
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        return $user->warehouses()
            ->select('warehouses.id', 'warehouses.name', 'warehouses.city', 'warehouses.is_active')
            ->where('warehouses.is_active', true)
            ->orderBy('warehouses.name')
            ->get();
    }

    public function resolveCurrentWarehouse(User $user, ?int $sessionWarehouseId = null): ?Warehouse
    {
        $available = $this->availableWarehouses($user);

        if ($available->isEmpty()) {
            return null;
        }

        if ($sessionWarehouseId !== null) {
            $currentFromSession = $available->firstWhere('id', $sessionWarehouseId);

            if ($currentFromSession) {
                return $currentFromSession;
            }
        }

        $defaultWarehouse = $user->defaultWarehouse();
        if ($defaultWarehouse) {
            $currentFromDefault = $available->firstWhere('id', $defaultWarehouse->id);
            if ($currentFromDefault) {
                return $currentFromDefault;
            }
        }

        return $available->first();
    }
}

