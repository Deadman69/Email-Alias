<?php

namespace App\Enums;

enum Role: string
{
    case User       = 'user';
    case Admin      = 'admin';
    case SuperAdmin = 'super_admin';

    /**
     * Human-readable label for display.
     */
    public function label(): string
    {
        return match ($this) {
            Role::User       => 'User',
            Role::Admin      => 'Admin',
            Role::SuperAdmin => 'Super Admin',
        };
    }

    /**
     * Badge colour for Flux UI.
     */
    public function color(): string
    {
        return match ($this) {
            Role::User       => 'zinc',
            Role::Admin      => 'blue',
            Role::SuperAdmin => 'violet',
        };
    }

    /**
     * Whether this role grants at least the given access level.
     *
     * Hierarchy:  User (0) < Admin (1) < SuperAdmin (2)
     */
    public function isAtLeast(Role $role): bool
    {
        $order = [
            self::User->value       => 0,
            self::Admin->value      => 1,
            self::SuperAdmin->value => 2,
        ];

        return $order[$this->value] >= $order[$role->value];
    }
}
