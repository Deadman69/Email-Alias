<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

/**
 * Extended Sanctum token with alias restriction and expiry support.
 *
 * @property array|null $restricted_alias_ids  null = full access to all user aliases
 * @property \Carbon\CarbonImmutable|null $expires_at
 */
class PersonalAccessToken extends SanctumToken
{
    protected $fillable = [
        'tokenable_type',
        'tokenable_id',
        'name',
        'token',
        'abilities',
        'restricted_alias_ids',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'restricted_alias_ids' => 'array',
            'expires_at'           => 'immutable_datetime',
        ];
    }

    /**
     * Whether the token grants access to the given alias.
     * A null restriction list means access to all of the owner's aliases.
     */
    public function canAccessAlias(string $aliasId): bool
    {
        if ($this->restricted_alias_ids === null) {
            return true;
        }

        return in_array($aliasId, $this->restricted_alias_ids, true);
    }

    /**
     * Override Sanctum's expiry check to use our custom expires_at column.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
