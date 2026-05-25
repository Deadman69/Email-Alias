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
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'azure_id', 'locale', 'timezone', 'role', 'external_id', 'is_active'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'role'              => Role::class,
            'is_active'         => 'boolean',
        ];
    }

    /**
     * When a user is deleted, cascade their aliases through Eloquent so each
     * Alias::booted() hook fires, which cascades to emails then attachments.
     *
     * This ensures physical files on disk are always deleted — the DB-level FK
     * cascade (cascadeOnDelete) only fires raw SQL and bypasses model events.
     *
     * chunkById prevents loading all aliases into memory for users with many aliases.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $user) {
            $user->aliases()
                ->when(
                    method_exists($user, 'isForceDeleting') && $user->isForceDeleting(),
                    fn ($q) => $q->withTrashed()
                )
                ->chunkById(50, fn ($aliases) => $aliases->each->delete());
        });
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

    /**
     * Check if the user has been created by SSO
     */
    public function isSSO(): bool
    {
        return filled($this->external_id) || filled($this->azure_id);
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
