<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'is_primary'])]
class Domain extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    /**
     * All configured domain names, primary first.
     * Falls back to the platform .env domain when the table is empty.
     */
    public static function allNames(): array
    {
        try {
            $names = static::orderByDesc('is_primary')->orderBy('name')->pluck('name')->toArray();

            if (empty($names)) {
                $default = config('emailalias.domain', '');

                return $default ? [$default] : [];
            }

            return $names;
        } catch (\Throwable) {
            // Table does not exist yet (before migration).
            $default = config('emailalias.domain', '');

            return $default ? [$default] : [];
        }
    }

    /**
     * The domain to use by default for new aliases.
     * Returns the marked primary domain or, if none, the .env domain.
     */
    public static function primaryName(): string
    {
        try {
            return static::where('is_primary', true)->value('name')
                ?? config('emailalias.domain', 'example.com');
        } catch (\Throwable) {
            return config('emailalias.domain', 'example.com');
        }
    }

    /**
     * Probe the domain's MX records.
     * Returns true when at least one MX record was found.
     * Uses @ to suppress DNS warnings on unresolvable domains.
     */
    public function checkMx(): bool
    {
        $records = @dns_get_record($this->name, DNS_MX);

        return ! empty($records);
    }
}
