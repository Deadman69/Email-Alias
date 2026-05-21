<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password', 'role', 'azure_id', 'locale'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'role'              => Role::class,
        ];
    }

    // ── Backward-compatible accessors ────────────────────────────────────────────

    /**
     * Backward-compatible `$user->is_admin` — true for Admin and SuperAdmin.
     * Keeps all existing middleware, policies and Blade checks working.
     */
    public function getIsAdminAttribute(): bool
    {
        return $this->role->isAtLeast(Role::Admin);
    }

    // ── Role helpers ─────────────────────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role->isAtLeast(Role::Admin);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SuperAdmin;
    }

    public function hasRole(Role $role): bool
    {
        return $this->role === $role;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────────

    /**
     * Get the user's initials (first two words).
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    // ── Relations ─────────────────────────────────────────────────────────────────

    public function aliases(): HasMany
    {
        return $this->hasMany(Alias::class);
    }

    /**
     * Aliases shared with this user (read-only access).
     */
    public function sharedAliases(): BelongsToMany
    {
        return $this->belongsToMany(Alias::class, 'alias_shares', 'user_id', 'alias_id')
            ->withPivot('shared_by_id')
            ->withTimestamps();
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
