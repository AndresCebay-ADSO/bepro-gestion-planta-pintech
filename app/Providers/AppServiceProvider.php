<?php

namespace App\Providers;

use App\Listeners\LogFailedLoginAttempt;
use App\Models\Formula;
use App\Models\PriceList;
use App\Models\RawMaterial;
use App\Models\User;
use App\Models\Warehouse;
use App\Policies\FormulaPolicy;
use App\Policies\PriceListPolicy;
use App\Policies\RawMaterialPolicy;
use App\Policies\WarehousePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Event::listen(Failed::class, LogFailedLoginAttempt::class);

        Event::listen(Login::class, function (Login $event) {
            /** @var User $user */
            $user = $event->user;
            $user->update([
                'last_login_at' => now(),
            ]);
        });

        Gate::define('view-audit-logs', function ($user) {
            return $user->hasRole('admin');
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Gate::policy(Formula::class, FormulaPolicy::class);
        Gate::policy(PriceList::class, PriceListPolicy::class);
        Gate::policy(RawMaterial::class, RawMaterialPolicy::class);
        Gate::policy(Warehouse::class, WarehousePolicy::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
