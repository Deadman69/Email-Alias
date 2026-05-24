<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Machine / application-level API token.
 *
 * Unlike personal_access_tokens these are not bound to a user.
 * They are managed exclusively by Super Admins and intended for
 * server-to-server integrations (e.g. the SMTP receiver querying
 * /api/v1/domains).
 *
 * The plain token is generated once and never stored; only the SHA-256
 * hash is persisted. The plain token must be shown to the admin immediately
 * after creation and cannot be recovered afterwards.
 */
class AppToken extends Model
{
    use HasUlids;

    protected $fillable = ['name', 'token', 'abilities', 'expires_at'];

    protected function casts(): array
    {
        return [
            'abilities'   => 'array',
            'last_used_at' => 'datetime',
            'expires_at'  => 'datetime',
        ];
    }

    // ── Factory helpers ───────────────────────────────────────────────────────────

    /**
     * Generate a new plain token and return both the model (unsaved) and the
     * plain-text value.
     *
     * @return array{token: AppToken, plain: string}
     */
    public static function make(string $name, ?array $abilities = null, ?\DateTimeInterface $expiresAt = null): array
    {
        $plain = Str::random(40);
        $hash  = hash('sha256', $plain);

        $token = new self([
            'name'       => $name,
            'token'      => $hash,
            'abilities'  => $abilities,
            'expires_at' => $expiresAt,
        ]);

        return ['token' => $token, 'plain' => $plain];
    }

    // ── Lookup ────────────────────────────────────────────────────────────────────

    /**
     * Find a token record by its plain-text value.
     * Updates last_used_at in the same query.
     */
    public static function findByPlain(string $plain): ?self
    {
        $hash  = hash('sha256', $plain);
        $token = self::where('token', $hash)->first();

        if (! $token) {
            return null;
        }

        // Expired?
        if ($token->expires_at && $token->expires_at->isPast()) {
            return null;
        }

        $token->update(['last_used_at' => now()]);

        return $token;
    }

    // ── Abilities ─────────────────────────────────────────────────────────────────

    /**
     * Returns true when the token has the given ability (or has no restrictions).
     */
    public function can(string $ability): bool
    {
        if (empty($this->abilities)) {
            return true; // unrestricted token
        }

        return in_array($ability, $this->abilities, true)
            || in_array('*', $this->abilities, true);
    }
}
