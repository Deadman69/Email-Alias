<?php

namespace App\Policies;

use App\Models\Alias;
use App\Models\User;

class AliasPolicy
{
    /**
     * Admins bypass all policy checks.
     */
    public function before(User $user): ?bool
    {
        if ($user->is_admin) {
            return true;
        }

        return null;
    }

    /**
     * Any authenticated user can view the alias list.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * A user can only view their own aliases.
     */
    public function view(User $user, Alias $alias): bool
    {
        return $alias->user_id === $user->id;
    }

    /**
     * Any authenticated user can create aliases.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Only the alias owner can update it.
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
}
