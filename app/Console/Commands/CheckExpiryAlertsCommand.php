<?php

namespace App\Console\Commands;

use App\Services\AlertService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('alerts:check-expiry')]
#[Description('Scan inventory batches and generate expiry alerts')]
class CheckExpiryAlertsCommand extends Command
{
    public function handle(AlertService $alertService): int
    {
        $processed = $alertService->scanExpiringBatches();

        $this->info("Lotes evaluados: {$processed}");

        return Command::SUCCESS;
    }
}
