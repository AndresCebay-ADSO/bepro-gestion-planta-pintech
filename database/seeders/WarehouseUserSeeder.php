<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SystemRole;
use App\Enums\WarehouseType;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $warehouses = Warehouse::query()->orderBy('name')->get();

        // Debe coincidir con WarehouseSeeder (antes se usaba "Planta Neiva", que no existe).
        $defaultPivotWarehouse = $warehouses->firstWhere('name', 'Bodega Neiva')
            ?? $warehouses->first();

        $factoryWarehouse = Warehouse::query()
            ->where('type', WarehouseType::Factory->value)
            ->first();

        $adminUsers = User::query()->role(SystemRole::Admin->value)->get();
        foreach ($adminUsers as $admin) {
            $sync = [];
            foreach ($warehouses as $warehouse) {
                $sync[$warehouse->id] = [
                    'is_default' => $defaultPivotWarehouse && $warehouse->id === $defaultPivotWarehouse->id,
                ];
            }

            $admin->warehouses()->syncWithoutDetaching($sync);
        }

        foreach (User::query()->role(SystemRole::Production->value)->get() as $productionUser) {
            if ($factoryWarehouse !== null) {
                $productionUser->warehouses()->syncWithoutDetaching([
                    $factoryWarehouse->id => ['is_default' => true],
                ]);
            }
        }

        $commercialUsers = User::query()->role(SystemRole::Commercial->value)->get();
        foreach ($commercialUsers as $commercialUser) {
            $sync = [];
            foreach ($warehouses as $warehouse) {
                $sync[$warehouse->id] = [
                    'is_default' => $defaultPivotWarehouse && $warehouse->id === $defaultPivotWarehouse->id,
                ];
            }

            $commercialUser->warehouses()->syncWithoutDetaching($sync);
        }
    }
}
