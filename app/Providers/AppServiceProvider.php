<?php

namespace App\Providers;

use App\Listeners\LogFailedLoginAttempt;
use App\Models\Alert;
use App\Models\FinishedInventory;
use App\Models\FinishedInventoryMovement;
use App\Models\Formula;
use App\Models\PaintDevelopmentRequest;
use App\Models\PriceList;
use App\Models\ProductionOrder;
use App\Models\ProductionRemnant;
use App\Models\RawMaterial;
use App\Models\User;
use App\Models\Warehouse;
use App\Policies\AlertPolicy;
use App\Policies\FinishedInventoryMovementPolicy;
use App\Policies\FinishedInventoryPolicy;
use App\Policies\FormulaPolicy;
use App\Policies\PaintDevelopmentRequestPolicy;
use App\Policies\PriceListPolicy;
use App\Policies\ProductionOrderPolicy;
use App\Policies\ProductionRemnantPolicy;
use App\Policies\RawMaterialPolicy;
use App\Policies\WarehousePolicy;
use App\Services\DecimalCalculator;
use App\Services\FormulaService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DecimalCalculator::class, function ($app) {
            return new DecimalCalculator;
        });

        $this->app->singleton(FormulaService::class, function ($app) {
            return new FormulaService($app->make(DecimalCalculator::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();

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

        Gate::policy(Alert::class, AlertPolicy::class);
        Gate::policy(Formula::class, FormulaPolicy::class);
        Gate::policy(PaintDevelopmentRequest::class, PaintDevelopmentRequestPolicy::class);
        Gate::policy(PriceList::class, PriceListPolicy::class);
        Gate::policy(ProductionOrder::class, ProductionOrderPolicy::class);
        Gate::policy(ProductionRemnant::class, ProductionRemnantPolicy::class);
        Gate::policy(FinishedInventory::class, FinishedInventoryPolicy::class);
        Gate::policy(FinishedInventoryMovement::class, FinishedInventoryMovementPolicy::class);
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

    private function configureRateLimiting(): void
    {
        RateLimiter::for('production-preview-costs', function (Request $request): Limit {
            $user = $request->user();

            return Limit::perMinute(30)->by(
                $user?->id !== null
                    ? 'user:'.$user->id
                    : 'ip:'.$request->ip()
            );
        });
    }
}
