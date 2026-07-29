<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict access to Admin users (and Super Admins).
 * Use `super_admin` middleware for pages requiring full platform control.
 *
 * Requests authenticated via an AppToken (see EnsureApiTokenOrSanctum) have no
 * associated user/role — they are allowed through here, and the specific
 * admin ability is enforced by the controller instead.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->attributes->has('app_token')) {
            return $next($request);
        }

        if (! $request->user()?->role?->isAtLeast(Role::Admin)) {
            abort(403, 'Administrator access required.');
        }

        return $next($request);
    }
}
