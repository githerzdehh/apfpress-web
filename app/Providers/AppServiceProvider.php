<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Application services are intentionally bound close to their integrations.
    }

    public function boot(): void
    {
        Paginator::useTailwind();
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
