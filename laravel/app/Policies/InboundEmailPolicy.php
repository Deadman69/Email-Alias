<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\InboundEmail;
use App\Models\User;

class InboundEmailPolicy
{
    /**
     * Super Admins bypass all email policy checks.
     * Regular Admins can view email content only when the platform setting
     * `admin_can_read_emails` is enabled (defaults to false).
     * No admin bypass is granted for destructive actions (delete).
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->role === Role::SuperAdmin) {
            return true;
        }

        if ($user->role->isAtLeast(Role::Admin) && $ability === 'view') {
            return config('emailalias.admin_can_read_emails', false) ? true : null;
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
     * Only the alias owner can mutate (mark read/unread) an email.
     * Shared users have read-only access.
     */
    public function update(User $user, InboundEmail $email): bool
    {
        $alias = $email->alias()->withTrashed()->first();

        if (! $alias) {
            return false;
        }

        return $alias->user_id === $user->id;
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
