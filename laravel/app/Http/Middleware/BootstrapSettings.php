<?php

namespace App\Http\Middleware;

use App\Services\SettingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * Loads platform settings from the database and overrides the corresponding
 * Laravel config keys so all existing config('emailalias.*') calls
 * transparently reflect the admin-configured values.
 *
 * Runs on every web request (prepended to the global middleware stack).
 * Results are cached via SettingService, so the DB is only hit on
 * the first request after a settings change.
 */
class BootstrapSettings
{
    public function __construct(private readonly SettingService $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $all = $this->settings->all();

            foreach (SettingService::CONFIG_MAP as $settingKey => $configKey) {
                if (array_key_exists($settingKey, $all) && $all[$settingKey] !== null) {
                    Config::set($configKey, $this->settings->get($settingKey));
                }
            }
        } catch (\Throwable) {
            // Never crash the application due to a missing/corrupt settings table.
        }

        return $next($request);
    }
}
