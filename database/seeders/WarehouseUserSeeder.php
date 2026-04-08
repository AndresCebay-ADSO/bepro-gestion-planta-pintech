<?php

namespace Database\Seeders;

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
        $neiva = $warehouses->firstWhere('name', 'Planta Neiva');

        $adminUsers = User::query()->role('admin')->get();
        foreach ($adminUsers as $admin) {
            $sync = [];
            foreach ($warehouses as $warehouse) {
                $sync[$warehouse->id] = [
                    'is_default' => $neiva ? $warehouse->id === $neiva->id : false,
                ];
            }

            $admin->warehouses()->syncWithoutDetaching($sync);
        }

        $productionUsers = User::query()->role('produccion')->get();
        foreach ($productionUsers as $productionUser) {
            if ($neiva) {
                $productionUser->warehouses()->sync([
                    $neiva->id => ['is_default' => true],
                ]);
            }
        }

        $commercialUsers = User::query()->role('comercial')->get();
        foreach ($commercialUsers as $commercialUser) {
            $sync = [];
            foreach ($warehouses as $warehouse) {
                $sync[$warehouse->id] = [
                    'is_default' => $neiva ? $warehouse->id === $neiva->id : false,
                ];
            }

            $commercialUser->warehouses()->syncWithoutDetaching($sync);
        }
    }
}

