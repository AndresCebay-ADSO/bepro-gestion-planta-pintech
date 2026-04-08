<?php

namespace Database\Seeders;

use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;

class RawMaterialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $kgUnitId = UnitOfMeasure::query()->where('code', 'kg')->value('id');
        $literUnitId = UnitOfMeasure::query()->where('code', 'l')->value('id');

        $defaultUnitId = $kgUnitId ?? $literUnitId;
        if (! $defaultUnitId) {
            return;
        }

        $items = [
            ['code' => 'MP001', 'unit_of_measure_id' => $kgUnitId ?? $defaultUnitId, 'current_price' => 18.5000],
            ['code' => 'RES01', 'unit_of_measure_id' => $kgUnitId ?? $defaultUnitId, 'current_price' => 24.9000],
            ['code' => 'AC4', 'unit_of_measure_id' => $literUnitId ?? $defaultUnitId, 'current_price' => 12.7500],
        ];

        foreach ($items as $item) {
            RawMaterial::updateOrCreate(
                ['code' => $item['code']],
                [
                    'unit_of_measure_id' => $item['unit_of_measure_id'],
                    'current_price' => $item['current_price'],
                    'previous_price' => null,
                    'minimum_stock' => 0,
                    'alert_days_before_expiry' => 30,
                    'is_active' => true,
                ]
            );
        }
    }
}
