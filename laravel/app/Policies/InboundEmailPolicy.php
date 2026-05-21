<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\InboundEmail;
use App\Models\User;

class InboundEmailPolicy
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
     * A user can view an email if they own the alias OR the alias is shared with them.
     *
     * Uses withTrashed() to avoid a null alias causing a 500 instead of a 403
     * when the parent alias has been soft-deleted.
     */
    public function view(User $user, InboundEmail $email): bool
    {
        $alias = $email->alias()->withTrashed()->first();

        if (! $alias) {
            return false;
        }

        return $alias->user_id === $user->id
            || $alias->shares()->where('user_id', $user->id)->exists();
    }

    /**
     * Only the alias owner can delete emails.
     * Shared users have read-only access.
     */
    public function delete(User $user, InboundEmail $email): bool
    {
        $alias = $email->alias()->withTrashed()->first();

        if (! $alias) {
            return false;
        }

        return $alias->user_id === $user->id;
    }
}
