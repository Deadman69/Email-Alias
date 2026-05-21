<?php

use App\Http\Middleware\BootstrapSettings;
use App\Http\Middleware\EnsureMetricsAccess;
use App\Http\Middleware\EnsureHealthCheckAccess;
use App\Http\Middleware\EnsureInternalRequest;
use App\Http\Middleware\EnsureScimAuth;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Internal routes (SMTP receiver webhook) — no CSRF, no session
            Route::middleware([])
                ->group(base_path('routes/internal.php'));

            // SCIM 2.0 provisioning — no CSRF, no session
            Route::middleware([])
                ->group(base_path('routes/scim.php'));

            // Metrics endpoint (Prometheus scraper)
            Route::middleware(['throttle:60,1', 'metrics.access'])
                ->group(function (): void {
                    Route::get('/metrics', \App\Http\Controllers\MetricsController::class)
                        ->name('metrics');
                });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Load DB settings early so config('emailalias.*') reflects admin choices
        $middleware->prependToGroup('web', BootstrapSettings::class);

        // Set per-user or platform locale — must run after session/auth are resolved
        $middleware->appendToGroup('web', SetLocale::class);

        $middleware->alias([
            'admin'        => EnsureUserIsAdmin::class,
            'super_admin'  => EnsureUserIsSuperAdmin::class,
            'internal'     => EnsureInternalRequest::class,
            'health.access'  => EnsureHealthCheckAccess::class,
            'scim.auth'      => EnsureScimAuth::class,
            'metrics.access' => EnsureMetricsAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
