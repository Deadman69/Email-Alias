<?php

namespace App\Http\Middleware;

use App\Services\SettingService;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the application locale for the current request.
 *
 * Resolution order:
 *   1. Authenticated user's personal locale preference (users.locale)
 *   2. Platform default locale (app_locale setting)
 *   3. Hard-coded fallback: 'en'
 *
 * Must run after the session and auth middlewares so Auth::user() is available.
 */
class SetLocale
{
    public function __construct(private readonly SettingService $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale
            ?? $this->settings->get('app_locale', 'en');

        if (in_array($locale, ['en', 'fr'], true)) {
            App::setLocale($locale);
            Carbon::setLocale($locale);
        }

        // Apply per-user timezone so all Carbon/date output is in the user's local time.
        $timezone = $request->user()?->timezone;
        if ($timezone && @timezone_open($timezone) !== false) {
            date_default_timezone_set($timezone);
        }

        return $next($request);
    }
}
