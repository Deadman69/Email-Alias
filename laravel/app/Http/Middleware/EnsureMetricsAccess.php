<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;

class EnsureMetricsAccess
{
    public function handle(Request $request, \Closure $next): mixed
    {
        $token = config('emailalias.metrics_bearer_token', '');

        if (empty($token)) {
            return response('# Metrics endpoint is disabled. Set METRICS_BEARER_TOKEN to enable.', 503)
                ->header('Content-Type', 'text/plain');
        }

        $provided = $request->bearerToken();

        if (! $provided || ! hash_equals($token, $provided)) {
            return response('# Unauthorized', 401)
                ->header('Content-Type', 'text/plain')
                ->header('WWW-Authenticate', 'Bearer realm="metrics"');
        }

        return $next($request);
    }
}
