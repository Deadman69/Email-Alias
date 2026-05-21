<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;

class EnsureScimAuth
{
    public function handle(Request $request, \Closure $next): mixed
    {
        $token = config('emailalias.scim_bearer_token', '');

        if (empty($token)) {
            return response()->json(['detail' => 'SCIM not configured.'], 503);
        }

        $provided = $request->bearerToken();

        if (! $provided || ! hash_equals($token, $provided)) {
            return response()->json(
                ['schemas' => ['urn:ietf:params:scim:api:messages:2.0:Error'], 'detail' => 'Unauthorized.', 'status' => 401],
                401,
                ['WWW-Authenticate' => 'Bearer realm="SCIM"']
            );
        }

        return $next($request);
    }
}
