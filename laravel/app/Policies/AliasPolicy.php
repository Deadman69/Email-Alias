<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Alias;
use App\Models\User;

class AliasPolicy
{
    /**
     * Admins and Super Admins bypass all policy checks.
     */
    public function before(User $user): ?bool
    {
        if ($user->role->isAtLeast(Role::Admin)) {
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
     * Only the alias owner can update it (rename, extend…).
     * Shared users are read-only.
     */
    public function update(User $user, Alias $alias): bool
    {
        return $alias->user_id === $user->id;
    }

    /**
     * Only the alias owner can delete it.
     */
    public function delete(User $user, Alias $alias): bool
    {
        return $alias->user_id === $user->id;
    }

    /**
     * Only the alias owner can manage shares.
     */
    public function share(User $user, Alias $alias): bool
    {
        return $alias->user_id === $user->id;
    }
}
