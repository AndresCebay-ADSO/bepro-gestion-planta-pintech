<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Features;
use Tests\Support\TestDatabaseGuard;

abstract class TestCase extends BaseTestCase
{
    /**
     * Los traits (RefreshDatabase) corren después de que Laravel cambia a la base de cada proceso en paralelo:
     * aquí ya se ve la base real y todavía no se ha borrado nada.
     *
     * @return array<class-string, class-string>
     */
    protected function setUpTraits()
    {
        $connection = DB::connection();

        TestDatabaseGuard::ensureSafe($connection->getDriverName(), $connection->getDatabaseName());

        return parent::setUpTraits();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
