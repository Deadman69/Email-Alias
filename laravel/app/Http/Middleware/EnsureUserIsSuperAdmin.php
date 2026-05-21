<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict access to Super Admin users only.
 * Super Admins can configure platform settings (SSO, auth, limits…).
 * Regular Admins are denied.
 */
class EnsureUserIsSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== Role::SuperAdmin) {
            abort(403, 'Super administrator access required.');
        }

        return $next($request);
    }
}
