<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Alias;
use App\Models\PersonalAccessToken;
use Illuminate\Http\Request;

/**
 * Base controller for API v1 routes.
 *
 * Provides a helper to enforce alias-level token restrictions on top of the
 * standard Laravel policies. Both checks must pass to access an alias resource.
 */
abstract class BaseApiController extends Controller
{
    /**
     * Verify that the current token is allowed to access the given alias.
     * Must be called after policy authorization (which checks ownership/sharing).
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    protected function checkTokenAliasAccess(Request $request, Alias $alias): void
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken && ! $token->canAccessAlias($alias->id)) {
            abort(403, 'This token is not authorized to access this alias.');
        }
    }
}
