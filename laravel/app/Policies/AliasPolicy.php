<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Alias;
use App\Models\User;

class AliasPolicy
{
    /**
     * Super Admins bypass all alias policy checks.
     * Regular Admins can only bypass read-only checks (viewAny / view) to
     * support the admin dashboard — they must not be able to modify or delete
     * other users' aliases through normal web routes.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->role === Role::SuperAdmin) {
            return true;
        }

        if ($user->role->isAtLeast(Role::Admin) && in_array($ability, ['viewAny', 'view'], true)) {
            return true;
        }

        return null;
    }

    /**
     * Any authenticated user can see the alias list (scoped to own aliases in queries).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Owner OR a user the alias has been shared with can view it.
     */
    public function view(User $user, Alias $alias): bool
    {
        return $alias->user_id === $user->id
            || $alias->shares()->where('user_id', $user->id)->exists();
    }

    /**
     * Any authenticated user can create aliases.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * The alias owner, or a user it was shared with editable=true, can update it.
     */
    public function update(User $user, Alias $alias): bool
    {
        return $alias->user_id === $user->id
            || $alias->shares()->where('user_id', $user->id)->where('editable', true)->exists();
    }

    /**
     * The alias owner, or a user it was shared with editable=true, can delete it.
     */
    public function delete(User $user, Alias $alias): bool
    {
        return $alias->user_id === $user->id
            || $alias->shares()->where('user_id', $user->id)->where('editable', true)->exists();
    }

    /**
     * Only the alias owner can manage shares.
     */
    public function share(User $user, Alias $alias): bool
    {
        return $alias->user_id === $user->id;
    }
}
