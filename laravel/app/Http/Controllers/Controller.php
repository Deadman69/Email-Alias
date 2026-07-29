<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Authorize an API request for the given ability, accepting either a
     * Sanctum-authenticated user with that token ability, or an AppToken
     * (see EnsureApiTokenOrSanctum) with that ability. 403s otherwise.
     *
     * Required abilities per route are documented in documentation/api-tokens.md.
     */
    protected function authorizeAbility(Request $request, string $ability): void
    {
        $appToken = $request->attributes->get('app_token');

        if ($appToken) {
            abort_unless($appToken->can($ability), 403, "Token missing required ability: {$ability}.");

            return;
        }

        abort_unless($request->user()?->tokenCan($ability), 403);
    }

    /**
     * Same as authorizeAbility(), additionally requiring the acting user to
     * have the Admin role when authenticated as a regular user. AppTokens
     * carry no role — the ability itself (e.g. admin:users) is the gate.
     */
    protected function authorizeAdminAbility(Request $request, string $ability): void
    {
        $appToken = $request->attributes->get('app_token');

        if ($appToken) {
            abort_unless($appToken->can($ability), 403, "Token missing required ability: {$ability}.");

            return;
        }

        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($request->user()->tokenCan($ability), 403);
    }
}
