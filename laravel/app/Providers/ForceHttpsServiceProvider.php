<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ForceHttpsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $shouldForce = env('APP_FORCE_HTTPS') ?? Str::startsWith(config('app.url'), 'https://');
        if ($shouldForce) {
            URL::forceScheme('https');
        }
    }
}
