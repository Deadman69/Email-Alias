<?php

namespace App\Http\Middleware;

use App\Enums\HealthVisibility;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controls access to the /health and /api/v1/health endpoints.
 *
 * The visibility level is set via the `health_check_visibility` platform
 * setting (Super Admin panel) and falls back to the .env value:
 *
 *   - 'public' : no authentication required (default)
 *   - 'auth'   : any authenticated user (session or Bearer token)
 *   - 'admin'  : only Admin or SuperAdmin users
 *
 * Authentication is resolved opportunistically — the middleware does NOT
 * require the standard `auth` middleware to run beforehand. It checks the
 * session guard first, then falls back to the Sanctum guard for Bearer tokens.
 */
class EnsureHealthCheckAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $visibility = HealthVisibility::tryFrom(
            config('emailalias.health_check_visibility', HealthVisibility::Public->value)
        ) ?? HealthVisibility::Public;

        if ($visibility === HealthVisibility::Public) {
            return $next($request);
        }

        // Resolve user from session (web) or Bearer token (API) without
        // requiring upstream auth middleware.
        $user = Auth::guard('web')->user();

        if (! $user && $request->bearerToken()) {
            $user = Auth::guard('sanctum')->user();
        }

        if ($visibility === HealthVisibility::Auth && ! $user) {
            abort(401, 'Authentication required.');
        }

        if ($visibility === HealthVisibility::Admin) {
            if (! $user) {
                abort(401, 'Authentication required.');
            }
            if (! $user->isAdmin()) {
                abort(403, 'Admin access required.');
            }
        }

        return $next($request);
    }
}
