<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInternalRequest
{
    /**
     * Allow only requests from the SMTP receiver using the shared secret.
     * This endpoint must never be reachable from the public internet.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('emailalias.smtp_secret');

        $incoming = $request->header('X-SMTP-Secret', '');

        if (empty($secret) || ! hash_equals($secret, $incoming)) {
            abort(403, 'Unauthorized internal request.');
        }

        return $next($request);
    }
}
