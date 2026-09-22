<?php

namespace App\Providers;

use App\Contracts\CardProviderInterface;
use App\Contracts\CardRecognitionService;
use App\Services\TesseractCardRecognitionService;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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
        $this->app->bind(CardProviderInterface::class, ScryfallCardProvider::class);
        $this->app->bind(CardRecognitionService::class, TesseractCardRecognitionService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

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

        RateLimiter::for('scanner', fn (Request $request): Limit => Limit::perMinute(30)
            ->by($request->user()->id.'|'.$request->ip()));

        RateLimiter::for('scanner-confirmation', fn (Request $request): Limit => Limit::perMinute(30)
            ->by($request->user()->id.'|'.$request->ip()));
    }
}
