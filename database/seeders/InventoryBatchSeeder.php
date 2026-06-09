<?php

namespace Database\Seeders;

use App\Models\InventoryBatch;
use App\Models\RawMaterial;
use App\Models\Warehouse;
use App\Services\RawMaterialReferencePriceService;
use Illuminate\Database\Seeder;

class InventoryBatchSeeder extends Seeder
{
    public function run(): void
    {
        $cali = Warehouse::where('name', 'Planta Cali')->first();

        if (! $cali) {
            $this->command->error('Warehouse Planta Cali not found. Run WarehouseSeeder first.');

            return;
        }

        $batchesConfig = [
            'A-1' => [
                ['qty' => 1000, 'price' => 37088],
            ],
            'A-2' => [
                ['qty' => 1000, 'price' => 96130],
            ],
            'A-3 A' => [
                ['qty' => 1800, 'price' => 48200],
            ],
            'A-3 C' => [
                ['qty' => 1800, 'price' => 31600],
            ],
            'A-4' => [
                ['qty' => 2000, 'price' => 78650],
            ],
            'A-5' => [
                ['qty' => 3500, 'price' => 6800],
            ],
            'A-6' => [
                ['qty' => 2500, 'price' => 6800],
            ],
            'A-7' => [
                ['qty' => 25, 'price' => 40336],
            ],
            'A-8' => [
                ['qty' => 25, 'price' => 4454],
                ['qty' => 25, 'price' => 4204],
            ],
            'A-9' => [
                ['qty' => 25, 'price' => 8403],
            ],
            'ABA-1' => [
                ['qty' => 20, 'price' => 10350],
            ],
            'ABA-1 PQ' => [
                ['qty' => 80, 'price' => 9905],
            ],
            'ABA-2' => [
                ['qty' => 30, 'price' => 16950],
            ],
            'ABA-3 P' => [
                ['qty' => 200, 'price' => 14400],
            ],
            'ABA-3 QC' => [
                ['qty' => 100, 'price' => 14850],
            ],
            'ABA-4 S' => [
                ['qty' => 30, 'price' => 7500],
            ],
            'ABA-4' => [
                ['qty' => 45, 'price' => 15200],
            ],
            'ABA-5' => [
                ['qty' => 230, 'price' => 4450],
            ],
            'ABA-6' => [
                ['qty' => 400, 'price' => 8900],
            ],
            'ABA-7' => [
                ['qty' => 40, 'price' => 20800],
            ],
            'ABA-8' => [
                ['qty' => 20, 'price' => 12500],
            ],
            'ABA-9' => [
                ['qty' => 15, 'price' => 10900],
            ],
            'ABA-10' => [
                ['qty' => 20, 'price' => 17800],
            ],
            'ABA-10 P' => [
                ['qty' => 20, 'price' => 55820],
            ],
            'ABA-11' => [
                ['qty' => 25, 'price' => 63000],
            ],
            'ABA-12' => [
                ['qty' => 20, 'price' => 50000],
            ],
            'ABA-13' => [
                ['qty' => 20, 'price' => 23840],
                ['qty' => 100, 'price' => 22050],
                ['qty' => 100, 'price' => 22050],
            ],
            'ABA-14' => [
                ['qty' => 20, 'price' => 8250],
            ],
            'ABS-1' => [
                ['qty' => 25, 'price' => 102300],
            ],
            'ABS-1 PU' => [
                ['qty' => 100, 'price' => 106000],
            ],
            'ABS-2' => [
                ['qty' => 100, 'price' => 47000],
            ],
            'ABS-2 C' => [
                ['qty' => 20, 'price' => 42500],
            ],
            'ABS-2 CC' => [
                ['qty' => 20, 'price' => 42500],
            ],
            'ABS-3' => [
                ['qty' => 100, 'price' => 17900],
            ],
            'ABS-3 PU' => [
                ['qty' => 100, 'price' => 18500],
            ],
            'ABS-3 POL' => [
                ['qty' => 100, 'price' => 18500],
            ],
            'ABS-4' => [
                ['qty' => 100, 'price' => 87600],
            ],
            'ABS-4 C' => [
                ['qty' => 100, 'price' => 33100],
            ],
            'ABS-4 P' => [
                ['qty' => 100, 'price' => 46150],
            ],
            'ABS-4 R' => [
                ['qty' => 100, 'price' => 136000],
                ['qty' => 100, 'price' => 153500],
            ],
            'ABS-4 POL' => [
                ['qty' => 100, 'price' => 156000],
            ],
            'ABS-5' => [
                ['qty' => 100, 'price' => 56435],
            ],
            'ABS-5 P' => [
                ['qty' => 100, 'price' => 52510],
                ['qty' => 100, 'price' => 51740],
                ['qty' => 100, 'price' => 50970],
            ],
            'ABS-6' => [
                ['qty' => 100, 'price' => 156500],
            ],
            'ABS-7' => [
                ['qty' => 100, 'price' => 48990],
                ['qty' => 100, 'price' => 45160],
                ['qty' => 100, 'price' => 45160],
            ],
            'ABS-7 ALQ' => [
                ['qty' => 100, 'price' => 27400],
            ],
            'ABS-8' => [
                ['qty' => 100, 'price' => 96000],
            ],
            'ABS-8 C' => [
                ['qty' => 20, 'price' => 44000],
            ],
            'ABS-9' => [
                ['qty' => 100, 'price' => 13600],
            ],
            'ABS-9 A' => [
                ['qty' => 100, 'price' => 13700],
            ],
            'ABS-9 P' => [
                ['qty' => 100, 'price' => 11500],
            ],
            'ABS-9 QC' => [
                ['qty' => 100, 'price' => 13600],
            ],
            'ABS-10' => [
                ['qty' => 100, 'price' => 14950],
            ],
            'ABS-11 QC' => [
                ['qty' => 100, 'price' => 25600],
            ],
            'ABS-11 P' => [
                ['qty' => 20, 'price' => 40600],
            ],
            'ABS-12 A' => [
                ['qty' => 20, 'price' => 29700],
            ],
            'ABS-12 QC' => [
                ['qty' => 100, 'price' => 36200],
            ],
            'ABS-13' => [
                ['qty' => 100, 'price' => 39200],
            ],
            'ABS-13 QC' => [
                ['qty' => 100, 'price' => 35200],
            ],
            'ABS-14' => [
                ['qty' => 100, 'price' => 56500],
            ],
            'ABS-14 C' => [
                ['qty' => 100, 'price' => 21900],
            ],
            'ABS-14 E' => [
                ['qty' => 100, 'price' => 21938],
            ],
            'ABS-15' => [
                ['qty' => 100, 'price' => 70000],
            ],
            'ABS-15 A' => [
                ['qty' => 100, 'price' => 56300],
            ],
            'ABS-16' => [
                ['qty' => 100, 'price' => 48700],
            ],
            'ABS-17' => [
                ['qty' => 100, 'price' => 135000],
            ],
            'ABS-18' => [
                ['qty' => 100, 'price' => 101000],
            ],
            'ABS-19' => [
                ['qty' => 100, 'price' => 78000],
            ],
            'ABS-20' => [
                ['qty' => 100, 'price' => 13900],
            ],
            'C-1' => [
                ['qty' => 100, 'price' => 1300],
            ],
            'C-2' => [
                ['qty' => 100, 'price' => 102000],
            ],
            'C-3' => [
                ['qty' => 1000, 'price' => 10300],
            ],
            'C-3 ROCSA P' => [
                ['qty' => 100, 'price' => 11800],
            ],
            'C-3 POL' => [
                ['qty' => 100, 'price' => 14300],
            ],
            'C-3 POL P' => [
                ['qty' => 1000, 'price' => 12800],
            ],
            'C-4' => [
                ['qty' => 100, 'price' => 2500],
            ],
            'C-5' => [
                ['qty' => 100, 'price' => 1755],
            ],
            'C-5 QC' => [
                ['qty' => 100, 'price' => 1660],
                ['qty' => 100, 'price' => 1810],
            ],
            'C-6' => [
                ['qty' => 100, 'price' => 1453],
                ['qty' => 100, 'price' => 1453],
                ['qty' => 100, 'price' => 1632],
                ['qty' => 100, 'price' => 1632],
            ],
            'C-6 1 HD' => [
                ['qty' => 100, 'price' => 1023],
            ],
            'C-6 1 NT' => [
                ['qty' => 100, 'price' => 1193],
            ],
            'C-7' => [
                ['qty' => 100, 'price' => 1100],
            ],
            'C-7 QM' => [
                ['qty' => 100, 'price' => 813],
            ],
            'C-7 M' => [
                ['qty' => 100, 'price' => 725],
            ],
            'C-8' => [
                ['qty' => 100, 'price' => 5800],
            ],
            'C-9' => [
                ['qty' => 100, 'price' => 3900],
            ],
            'C-10' => [
                ['qty' => 100, 'price' => 7730],
            ],
            'C-11' => [
                ['qty' => 100, 'price' => 10150],
                ['qty' => 100, 'price' => 9470],
                ['qty' => 100, 'price' => 9470],
            ],
            'C-11 M' => [
                ['qty' => 100, 'price' => 13000],
            ],
            'C-12' => [
                ['qty' => 100, 'price' => 5170],
            ],
            'C-13' => [
                ['qty' => 100, 'price' => 90500],
            ],
            'C-14' => [
                ['qty' => 100, 'price' => 611],
                ['qty' => 100, 'price' => 821],
            ],
            'P-1' => [
                ['qty' => 100, 'price' => 41600],
            ],
            'P-2' => [
                ['qty' => 100, 'price' => 11500],
            ],
            'P-3' => [
                ['qty' => 100, 'price' => 41600],
            ],
            'P-4' => [
                ['qty' => 100, 'price' => 12100],
            ],
            'P-5' => [
                ['qty' => 100, 'price' => 31800],
            ],
            'P-6' => [
                ['qty' => 100, 'price' => 35700],
            ],
            'P-7' => [
                ['qty' => 100, 'price' => 10500],
            ],
            'P-8' => [
                ['qty' => 100, 'price' => 14350],
                ['qty' => 100, 'price' => 9080],
                ['qty' => 100, 'price' => 11990],
            ],
            'P-9' => [
                ['qty' => 100, 'price' => 26450],
            ],
            'P-10' => [
                ['qty' => 100, 'price' => 29800],
            ],
            'P-10 P' => [
                ['qty' => 100, 'price' => 34300],
            ],
            'P-11' => [
                ['qty' => 100, 'price' => 58800],
            ],
            'P-12' => [
                ['qty' => 100, 'price' => 76500],
            ],
            'P-13' => [
                ['qty' => 100, 'price' => 30500],
            ],
            'P-14' => [
                ['qty' => 100, 'price' => 81200],
            ],
            'P-15' => [
                ['qty' => 100, 'price' => 11050],
            ],
            'P-16' => [
                ['qty' => 100, 'price' => 22500],
            ],
            'P-17' => [
                ['qty' => 100, 'price' => 23000],
            ],
            'P-18' => [
                ['qty' => 100, 'price' => 26000],
            ],
            'P-19' => [
                ['qty' => 100, 'price' => 25840],
                ['qty' => 100, 'price' => 25840],
            ],
            'P-20' => [
                ['qty' => 100, 'price' => 24880],
            ],
            'P-20B' => [
                ['qty' => 100, 'price' => 22180],
            ],
            'P-20 C' => [
                ['qty' => 100, 'price' => 24880],
            ],
            'P-21' => [
                ['qty' => 100, 'price' => 23000],
            ],
            'P-21 QC' => [
                ['qty' => 100, 'price' => 15500],
            ],
            'P-22' => [
                ['qty' => 100, 'price' => 21850],
            ],
            'P-23' => [
                ['qty' => 100, 'price' => 38180],
            ],
            'P-24' => [
                ['qty' => 100, 'price' => 16320],
            ],
            'P-25' => [
                ['qty' => 100, 'price' => 22850],
            ],
            'P-26' => [
                ['qty' => 100, 'price' => 10500],
            ],
            'P-27' => [
                ['qty' => 100, 'price' => 37826],
            ],
            'P-28' => [
                ['qty' => 100, 'price' => 14800],
            ],
            'P-29' => [
                ['qty' => 100, 'price' => 9200],
            ],
            'P-30' => [
                ['qty' => 100, 'price' => 17650],
            ],
            'P-31' => [
                ['qty' => 100, 'price' => 23000],
                ['qty' => 100, 'price' => 26200],
            ],
            'P-614' => [
                ['qty' => 100, 'price' => 95460],
            ],
            'P-637' => [
                ['qty' => 100, 'price' => 120000],
            ],
            'P-628' => [
                ['qty' => 100, 'price' => 190000],
            ],
            'P-608' => [
                ['qty' => 100, 'price' => 140000],
            ],
            'R-1 A' => [
                ['qty' => 100, 'price' => 11775],
            ],
            'R-2' => [
                ['qty' => 100, 'price' => 11500],
            ],
            'R-3' => [
                ['qty' => 100, 'price' => 10400],
            ],
            'R-4' => [
                ['qty' => 100, 'price' => 6970],
            ],
            'R-4 T' => [
                ['qty' => 100, 'price' => 13400],
            ],
            'R-4 A' => [
                ['qty' => 100, 'price' => 8300],
            ],
            'R-4 P' => [
                ['qty' => 100, 'price' => 7430],
            ],
            'R-5' => [
                ['qty' => 100, 'price' => 11200],
            ],
            'R-5 S KER 3001' => [
                ['qty' => 100, 'price' => 10350],
            ],
            'R-5 S R-601' => [
                ['qty' => 100, 'price' => 11500],
            ],
            'R-5 S' => [
                ['qty' => 100, 'price' => 11200],
            ],
            'R-5 A' => [
                ['qty' => 100, 'price' => 11808],
            ],
            'R-5 C' => [
                ['qty' => 100, 'price' => 11890],
            ],
            'R-5 R' => [
                ['qty' => 100, 'price' => 10400],
            ],
            'R-6 S' => [
                ['qty' => 100, 'price' => 24400],
                ['qty' => 100, 'price' => 25500],
            ],
            'R-6 A' => [
                ['qty' => 100, 'price' => 25050],
            ],
            'R-6 R' => [
                ['qty' => 100, 'price' => 21000],
            ],
            'R-6 C' => [
                ['qty' => 100, 'price' => 22500],
            ],
            'R-7 A' => [
                ['qty' => 400, 'price' => 12650],
                ['qty' => 2400, 'price' => 12650],
            ],
            'R-8 S' => [
                ['qty' => 100, 'price' => 22800],
            ],
            'R-8 P' => [
                ['qty' => 100, 'price' => 18700],
            ],
            'R-9' => [
                ['qty' => 100, 'price' => 12700],
            ],
            'R-10' => [
                ['qty' => 100, 'price' => 5770],
            ],
            'R-11' => [
                ['qty' => 100, 'price' => 11200],
            ],
            'R-12' => [
                ['qty' => 100, 'price' => 6660],
            ],
            'R-14' => [
                ['qty' => 100, 'price' => 15700],
            ],
            'R-13' => [
                ['qty' => 100, 'price' => 11500],
            ],
            'R-15' => [
                ['qty' => 100, 'price' => 90500],
            ],
            'R-16' => [
                ['qty' => 100, 'price' => 22850],
            ],
            'R-17' => [
                ['qty' => 100, 'price' => 23000],
            ],
            'R-18' => [
                ['qty' => 100, 'price' => 35400],
            ],
            'R-20' => [
                ['qty' => 100, 'price' => 11800],
                ['qty' => 100, 'price' => 11800],
            ],
            'R-21' => [
                ['qty' => 100, 'price' => 7940],
            ],
            'R-22' => [
                ['qty' => 100, 'price' => 7400],
            ],
            'R-23' => [
                ['qty' => 100, 'price' => 8800],
            ],
            'R-24' => [
                ['qty' => 100, 'price' => 8900],
            ],
            'R-25' => [
                ['qty' => 100, 'price' => 8900],
            ],
            'S-1' => [
                ['qty' => 100, 'price' => 15130],
            ],
            'S-2' => [
                ['qty' => 100, 'price' => 16900],
            ],
            'S-3' => [
                ['qty' => 100, 'price' => 4600],
                ['qty' => 100, 'price' => 5090],
                ['qty' => 100, 'price' => 6696],
            ],
            'S-4' => [
                ['qty' => 100, 'price' => 4900],
                ['qty' => 100, 'price' => 4900],
                ['qty' => 100, 'price' => 5145],
                ['qty' => 100, 'price' => 6458],
            ],
            'S-4 R' => [
                ['qty' => 100, 'price' => 13400],
            ],
            'S-5' => [
                ['qty' => 100, 'price' => 5450],
            ],
            'S-5 P' => [
                ['qty' => 100, 'price' => 6800],
            ],
            'S-6' => [
                ['qty' => 100, 'price' => 6600],
                ['qty' => 100, 'price' => 7500],
            ],
            'S-7' => [
                ['qty' => 100, 'price' => 5000],
            ],
            'S-7 POL' => [
                ['qty' => 100, 'price' => 4200],
                ['qty' => 100, 'price' => 4000],
                ['qty' => 100, 'price' => 6300],
            ],
            'S-7 CONCQUIMICA' => [
                ['qty' => 100, 'price' => 5100],
            ],
            'S-7 DBBW' => [
                ['qty' => 100, 'price' => 4435],
            ],
            'S-7 R' => [
                ['qty' => 100, 'price' => 4200],
            ],
            'S-8 B' => [
                ['qty' => 100, 'price' => 7800],
                ['qty' => 100, 'price' => 7800],
            ],
            'S-9 R' => [
                ['qty' => 100, 'price' => 7000],
                ['qty' => 100, 'price' => 8100],
            ],
            'S-10' => [
                ['qty' => 100, 'price' => 15210],
            ],
            'S-11' => [
                ['qty' => 100, 'price' => 7000],
            ],
            'S-11 R' => [
                ['qty' => 100, 'price' => 6800],
            ],
            'S-11 S' => [
                ['qty' => 100, 'price' => 7300],
            ],
            'S-12' => [
                ['qty' => 100, 'price' => 8300],
            ],
            'S-13' => [
                ['qty' => 100, 'price' => 6910],
            ],
            'S-14' => [
                ['qty' => 100, 'price' => 18800],
            ],
            'O-1' => [
                ['qty' => 100, 'price' => 105600],
            ],
            'O-2' => [
                ['qty' => 100, 'price' => 14641],
            ],
            'O-3' => [
                ['qty' => 100, 'price' => 24370],
                ['qty' => 100, 'price' => 24370],
            ],
            'AGUA DESTILADA' => [
                ['qty' => 100, 'price' => 1194],
                ['qty' => 100, 'price' => 1000],
            ],
            'AP-423' => [
                ['qty' => 100, 'price' => 45000],
            ],
            'AP-425' => [
                ['qty' => 100, 'price' => 70000],
            ],
            'AP-404' => [
                ['qty' => 100, 'price' => 70000],
            ],
            'AP-405' => [
                ['qty' => 100, 'price' => 70000],
            ],
            'AP-402' => [
                ['qty' => 100, 'price' => 70000],
            ],
            'AP-415' => [
                ['qty' => 100, 'price' => 70000],
            ],
            'AP-435' => [
                ['qty' => 100, 'price' => 70000],
            ],
            'AP-DORADA' => [
                ['qty' => 100, 'price' => 70000],
            ],
            'AP-419' => [
                ['qty' => 100, 'price' => 70000],
            ],
            'AP-421' => [
                ['qty' => 100, 'price' => 70000],
            ],
            'BENZ. S' => [
                ['qty' => 100, 'price' => 11900],
            ],
            'BS' => [
                ['qty' => 100, 'price' => 8400],
            ],
            'ETILE' => [
                ['qty' => 100, 'price' => 13900],
            ],
            'FOSF. S' => [
                ['qty' => 100, 'price' => 14200],
            ],
            'TEA' => [
                ['qty' => 100, 'price' => 10500],
            ],
            'ENV-M-CUÑ' => [
                ['qty' => 290, 'price' => 25890],
            ],
            'ENV-M-GL-TD' => [
                ['qty' => 100, 'price' => 4930],
            ],
            'ENV-M-GL-TF' => [
                ['qty' => 100, 'price' => 5175],
            ],
            'ENV-M-CUA-TD' => [
                ['qty' => 100, 'price' => 2675],
            ],
            'ENV-M-CUA-TF' => [
                ['qty' => 100, 'price' => 2975],
            ],
            'ENV-M-1/16' => [
                ['qty' => 100, 'price' => 2500],
            ],
            'ENV-M-T50' => [
                ['qty' => 100, 'price' => 1],
            ],
            'ENV-M-CIN' => [
                ['qty' => 100, 'price' => 25890],
            ],
            'ENV-P-GL' => [
                ['qty' => 100, 'price' => 5000],
            ],
            'ENV-P-CUÑ' => [
                ['qty' => 100, 'price' => 25890],
            ],
            'ENV-P-2.5-GL' => [
                ['qty' => 100, 'price' => 13400],
            ],
            'ENV-P-T50' => [
                ['qty' => 100, 'price' => 1],
            ],
            'ENV-CUÑ-CIN' => [
                ['qty' => 100, 'price' => 25890],
            ],
        ];

        foreach ($batchesConfig as $code => $batches) {
            $material = RawMaterial::where('code', $code)->first();

            if (! $material) {
                $this->command->warn("Raw material with code '{$code}' not found.");

                continue;
            }

            foreach ($batches as $index => $batch) {
                $daysAgo = 60 - ($index * 15) + rand(0, 10);
                $entryDate = now()->subDays($daysAgo);
                $expiryDate = now()->addDays(rand(180, 720));
                $quantity = max($batch['qty'], 1000);

                InventoryBatch::create([
                    'raw_material_id' => $material->id,
                    'warehouse_id' => $cali->id,
                    'initial_quantity' => $quantity,
                    'remaining_quantity' => $quantity,
                    'unit_price' => $batch['price'],
                    'entry_date' => $entryDate,
                    'expiry_date' => $expiryDate,
                    'lot_number' => 'LOTE-'.$entryDate->format('Y-m').'-'.str_pad($material->id, 4, '0', STR_PAD_LEFT).'-'.($index + 1),
                    'supplier' => 'Proveedor Pepito S.A.',
                ]);
            }

            app(RawMaterialReferencePriceService::class)
                ->syncRawMaterialCurrentPrice((int) $material->id);
        }

        $this->command->info('Inventory batches seeded successfully.');
    }
}
