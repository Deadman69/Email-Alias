<?php

namespace App\Http\Middleware;

use App\Models\AppToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticate requests using an application-level (machine) token.
 *
 * Expects:  Authorization: Bearer <plain-token>
 *
 * Usage in routes:
 *   Route::middleware('app.token')->get('/api/v1/domains', ...);
 *
 * Optionally require a specific ability:
 *   Route::middleware('app.token:read:domains')->get(...);
 */
class EnsureAppTokenAuth
{
    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        $plain = $request->bearerToken();

        if (! $plain) {
            return response()->json(['error' => 'Missing Authorization header.'], 401);
        }

        $token = AppToken::findByPlain($plain);

        if (! $token) {
            return response()->json(['error' => 'Invalid or expired token.'], 401);
        }

        if ($ability && ! $token->can($ability)) {
            return response()->json(['error' => "Token missing required ability: {$ability}."], 403);
        }

        // Make the resolved token available to the controller.
        $request->attributes->set('app_token', $token);

        return $next($request);
    }
}
