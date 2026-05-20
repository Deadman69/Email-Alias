<?php

namespace App\Policies;

use App\Models\InboundEmail;
use App\Models\User;

class InboundEmailPolicy
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
     * A user can only view emails in their own aliases.
     */
    public function view(User $user, InboundEmail $email): bool
    {
        return $email->alias->user_id === $user->id;
    }

    /**
     * A user can only delete emails in their own aliases.
     */
    public function delete(User $user, InboundEmail $email): bool
    {
        return $email->alias->user_id === $user->id;
    }
}
