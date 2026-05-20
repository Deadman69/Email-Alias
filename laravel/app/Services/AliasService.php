<?php

namespace App\Services;

use App\Enums\AliasType;
use App\Enums\AuditEvent;
use App\Models\Alias;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AliasService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Create a new alias for the given user.
     *
     * @throws ValidationException
     */
    public function create(
        User $user,
        AliasType $type,
        ?string $localPart = null,
        ?string $duration = null,
        ?string $label = null,
    ): Alias {
        $this->ensureUserCanCreateAlias($user);

        $localPart = $localPart ? $this->normalizeLocalPart($localPart) : $this->generateUniqueLocalPart();
        $domain = config('emailalias.domain');
        $address = "{$localPart}@{$domain}";

        $this->ensureAddressAvailable($address);

        $expiresAt = match ($type) {
            AliasType::Session   => now()->addHours(config('emailalias.session_alias_ttl_hours', 2)),
            AliasType::Duration  => Alias::expiresAtFromDuration($duration ?? '24h'),
            AliasType::Permanent => null,
        };

        if ($type === AliasType::Permanent && ! config('emailalias.allow_permanent')) {
            throw ValidationException::withMessages([
                'type' => ['Permanent aliases are disabled.'],
            ]);
        }

        $alias = Alias::create([
            'address'    => $address,
            'local_part' => $localPart,
            'type'       => $type,
            'duration'   => $duration,
            'user_id'    => $user->id,
            'label'      => $label,
            'expires_at' => $expiresAt,
        ]);

        $this->auditLogger->log(AuditEvent::AliasCreated, $alias, [
            'address' => $address,
            'type'    => $type->value,
        ]);

        return $alias;
    }

    /**
     * Delete an alias and all its emails.
     */
    public function delete(Alias $alias, bool $byAdmin = false): void
    {
        $event = $byAdmin ? AuditEvent::AdminAliasDeleted : AuditEvent::AliasDeleted;

        $this->auditLogger->log($event, $alias, [
            'address' => $alias->address,
        ]);

        $alias->delete();
    }

    /**
     * Extend an alias's expiration.
     *
     * @throws ValidationException
     */
    public function extend(Alias $alias, string $duration): void
    {
        if ($alias->type === AliasType::Permanent) {
            throw ValidationException::withMessages([
                'duration' => ['Permanent aliases do not expire.'],
            ]);
        }

        $alias->extendByDuration($duration);

        $this->auditLogger->log(AuditEvent::AliasExtended, $alias, [
            'duration'   => $duration,
            'expires_at' => $alias->expires_at->toIso8601String(),
        ]);
    }

    /**
     * Suggest an alternative local part if the desired one is taken.
     */
    public function suggestAlternative(string $localPart): string
    {
        $domain = config('emailalias.domain');
        $candidate = $this->normalizeLocalPart($localPart);
        $i = 2;

        while (Alias::withTrashed()->where('address', "{$candidate}@{$domain}")->exists()) {
            $candidate = $this->normalizeLocalPart($localPart) . '-' . $i;
            $i++;
        }

        return $candidate;
    }

    /**
     * Check if a local part / address is available (not taken, not soft-deleted).
     */
    public function isAddressAvailable(string $localPart): bool
    {
        $domain = config('emailalias.domain');
        $address = $this->normalizeLocalPart($localPart) . "@{$domain}";

        return ! Alias::withTrashed()->where('address', $address)->exists();
    }

    private function ensureUserCanCreateAlias(User $user): void
    {
        $count = Alias::where('user_id', $user->id)->count();
        $max = config('emailalias.max_aliases_per_user', 20);

        if ($count >= $max) {
            throw ValidationException::withMessages([
                'address' => ["You have reached the maximum of {$max} aliases."],
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function ensureAddressAvailable(string $address): void
    {
        if (Alias::withTrashed()->where('address', $address)->exists()) {
            throw ValidationException::withMessages([
                'local_part' => ['This address is already taken.'],
            ]);
        }
    }

    private function generateUniqueLocalPart(): string
    {
        $domain = config('emailalias.domain');

        do {
            $localPart = Str::lower(Str::random(8));
        } while (Alias::withTrashed()->where('address', "{$localPart}@{$domain}")->exists());

        return $localPart;
    }

    private function normalizeLocalPart(string $localPart): string
    {
        return Str::lower(preg_replace('/[^a-z0-9\-_\.]/i', '-', $localPart));
    }
}
