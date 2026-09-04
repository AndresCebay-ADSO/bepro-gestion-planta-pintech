<?php

use App\Models\Product;
use App\Services\ProductionCostRecalculationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Symfony\Component\Console\Command\Command;

$plantTimezone = (string) config('app.plant_timezone', 'America/Bogota');

Schedule::command('activitylog:clean')->dailyAt('03:00')->timezone($plantTimezone);
Schedule::command('alerts:check-expiry')->dailyAt('06:00')->timezone($plantTimezone);

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command(
    'costs:recalculate-product {product_id? : ID de producto a recalcular} {--chunk=100 : Tamaño de lote para recálculo masivo}',
    function (): int {
        $service = app(ProductionCostRecalculationService::class);
        $productId = $this->argument('product_id');

        if ($productId !== null) {
            $product = Product::query()->select('id', 'code')->find((int) $productId);

            if ($product === null) {
                $this->error("No existe producto con ID {$productId}.");

                return Command::FAILURE;
            }

            $result = $service->recalculateForProduct((int) $product->id);

            if ($result === null) {
                $this->warn("Producto {$product->code}: sin fórmula activa, no se recalculó.");

                return Command::SUCCESS;
            }

            $this->info("Producto {$product->code}: recálculo completado.");

            return Command::SUCCESS;
        }

        $chunkSize = max(1, (int) $this->option('chunk'));
        $total = 0;
        $recalculated = 0;

        Product::query()
            ->select('id')
            ->whereHas('activeFormula')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($products) use ($service, &$total, &$recalculated): void {
                foreach ($products as $product) {
                    $total++;
                    if ($service->recalculateForProduct((int) $product->id) !== null) {
                        $recalculated++;
                    }
                }
            });

        $this->info("Productos con fórmula activa procesados: {$total}");
        $this->info("Productos recalculados: {$recalculated}");

        return Command::SUCCESS;
    }
)->purpose('Recalcula costos y precios de productos/variantes para backfill');
