<?php

namespace App\Http\Middleware;

use App\Models\AppToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticate an /api/v1 request via either a Sanctum personal access token
 * (normal user) or an AppToken (machine/platform admin token).
 *
 * When an AppToken is used, `$request->attributes->get('app_token')` is set
 * and `$request->user()` remains null — controllers must check for an
 * app_token attribute before relying on `$request->user()`.
 *
 * See documentation/api-tokens.md for the ability required by each route.
 */
class EnsureApiTokenOrSanctum
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();

        if ($plain) {
            $token = AppToken::findByPlain($plain);

            if ($token) {
                $request->attributes->set('app_token', $token);

                return $next($request);
            }
        }

        if (! Auth::guard('sanctum')->check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Mirrors Illuminate\Auth\Middleware\Authenticate: switch the default guard
        // so Auth::user() / Gate authorization (used by $this->authorize() in
        // controllers) resolve the same user as $request->user().
        Auth::shouldUse('sanctum');
        $request->setUserResolver(fn () => Auth::guard('sanctum')->user());

        return $next($request);
    }
}
