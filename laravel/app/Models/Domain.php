<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'is_primary'])]
class Domain extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    // ── Relationships ─────────────────────────────────────────────────────────────

    public function aliases(): HasMany
    {
        return $this->hasMany(Alias::class);
    }

    // ── Static helpers ────────────────────────────────────────────────────────────

    /**
     * All domain names the SMTP receiver must accept, ordered primary-first.
     *
     * Returns the union of:
     *   1. Active domains from the `domains` table (primary first)
     *   2. Distinct domain-name strings from aliases whose domain record was deleted
     *      (domain_id IS NULL) — ensures orphaned aliases keep receiving mail.
     *
     * Never falls back to .env — if no domains are configured, returns [].
     */
    public static function allNames(): array
    {
        try {
            $active = static::orderByDesc('is_primary')
                ->orderBy('name')
                ->pluck('name')
                ->toArray();

            // Domains referenced by active aliases whose FK was nulled (orphaned).
            $orphaned = Alias::whereNull('domain_id')
                ->whereNotNull('domain')
                ->active()
                ->distinct()
                ->pluck('domain')
                ->toArray();

            return array_values(array_unique(array_merge($active, $orphaned)));
        } catch (\Throwable) {
            // Table does not exist yet (before first migration run).
            return [];
        }
    }

    /**
     * The primary domain for new aliases, or null when none is configured.
     *
     * Callers MUST handle the null case and surface an actionable error to the
     * user ("No domain is configured — please ask an administrator.").
     */
    public static function primaryName(): ?string
    {
        try {
            return static::where('is_primary', true)->value('name');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Probe the domain's MX records.
     * Returns true when at least one MX record was found.
     */
    public function checkMx(): bool
    {
        $records = @dns_get_record($this->name, DNS_MX);

        return ! empty($records);
    }
}
