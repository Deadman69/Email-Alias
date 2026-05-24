<?php

namespace App\Models;

use App\Enums\AliasType;
use Carbon\CarbonInterface;
use Database\Factories\AliasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(AliasFactory::class)]
#[Fillable(['address', 'local_part', 'domain', 'domain_id', 'type', 'duration', 'user_id', 'label', 'expires_at', 'webhook_url'])]
#[Hidden(['webhook_secret'])]
class Alias extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * Cascade soft-deletes to emails (and their attachments) so no orphan data
     * or physical files are left behind when an alias is deleted.
     *
     * DB-level FK cascades only fire on hard deletes, so we must do this in
     * Eloquent to guarantee the InboundEmail booted() hook (→ Attachment
     * booted() → Storage::delete) runs for every email.
     *
     * Shares are hard-deleted immediately (no soft-delete on alias_shares).
     */
    protected static function booted(): void
    {
        static::deleting(function (self $alias) {
            // Include already-soft-deleted emails when force-deleting
            $alias->inboundEmails()
                ->when($alias->isForceDeleting(), fn ($q) => $q->withTrashed())
                ->chunkById(100, fn ($emails) => $emails->each->delete());

            $alias->shares()->delete();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type'       => AliasType::class,
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Whether this alias has expired.
     */
    protected function isExpired(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->expires_at !== null && $this->expires_at->isPast(),
        );
    }

    /**
     * Human-readable time remaining before expiration.
     */
    public function expiresInHuman(): ?string
    {
        if ($this->expires_at === null) {
            return null;
        }

        if ($this->is_expired) {
            return 'Expired';
        }

        return $this->expires_at->diffForHumans();
    }

    /**
     * Extend the alias expiration by the given duration string.
     */
    public function extendByDuration(string $duration): void
    {
        $from = $this->expires_at?->isFuture() ? $this->expires_at : now();

        $this->expires_at = match ($duration) {
            '1h'  => $from->copy()->addHour(),
            '12h' => $from->copy()->addHours(12),
            '24h' => $from->copy()->addDay(),
            '7d'  => $from->copy()->addWeek(),
            '30d' => $from->copy()->addMonth(),
            default => throw new \InvalidArgumentException("Unknown duration: {$duration}"),
        };

        $this->save();
    }

    /**
     * Compute the expiration timestamp from a duration string.
     */
    public static function expiresAtFromDuration(string $duration): CarbonInterface
    {
        return match ($duration) {
            '1h'  => now()->addHour(),
            '12h' => now()->addHours(12),
            '24h' => now()->addDay(),
            '7d'  => now()->addWeek(),
            '30d' => now()->addMonth(),
            default => throw new \InvalidArgumentException("Unknown duration: {$duration}"),
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The Domain record this alias belongs to.
     * Null when the domain was deleted but the alias was kept (domain_id is null).
     * Use $alias->domain (string) for the actual domain name in all cases.
     */
    public function domainRecord(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain_id');
    }

    public function inboundEmails(): HasMany
    {
        return $this->hasMany(InboundEmail::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'auditable_id')
            ->where('auditable_type', self::class);
    }

    /**
     * AliasShare records — who has access to this alias.
     */
    public function shares(): HasMany
    {
        return $this->hasMany(AliasShare::class);
    }

    /**
     * Users this alias is shared with (read-only access).
     */
    public function sharedWith(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'alias_shares', 'alias_id', 'user_id')
            ->withPivot('shared_by_id')
            ->withTimestamps();
    }

    /**
     * Whether the alias is shared with at least one other user.
     */
    public function isShared(): bool
    {
        return $this->shares()->exists();
    }

    /**
     * Whether the given user has read access (owner or shared).
     */
    public function isAccessibleBy(User $user): bool
    {
        return $this->user_id === $user->id
            || $this->shares()->where('user_id', $user->id)->exists();
    }

    /**
     * Scope to only active (non-expired, non-deleted) aliases.
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope to only expired aliases.
     */
    public function scopeExpired(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }
}
