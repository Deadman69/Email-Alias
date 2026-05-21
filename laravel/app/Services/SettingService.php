<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Centralized service for reading and writing platform settings stored in the
 * database. Values are cached with a single cache key and busted on every write.
 *
 * Sensitive keys (Azure secrets, etc.) are automatically encrypted at rest
 * using Laravel's encrypt/decrypt helpers.
 *
 * Settings that map to a Laravel config key are pushed to Config::set() by
 * the BootstrapSettings middleware on every request so existing code that
 * reads config('emailalias.*') continues to work transparently.
 */
class SettingService
{
    private const CACHE_KEY = 'emailalias_settings';

    /**
     * Keys whose values are stored encrypted.
     */
    private const ENCRYPTED_KEYS = [
        'azure_client_secret',
    ];

    /**
     * Map setting key → Laravel config key.
     * Used by BootstrapSettings middleware to override config at runtime.
     */
    public const CONFIG_MAP = [
        'app_name'                         => 'app.name',
        'app_locale'                       => 'app.locale',
        'sso_enabled'                      => 'emailalias.sso_enabled',
        'local_auth_enabled'               => 'emailalias.local_auth_enabled',
        'registration_enabled'             => 'emailalias.registration_enabled',
        'two_factor_required'              => 'emailalias.two_factor_required',
        'alias_max_per_user'               => 'emailalias.max_aliases_per_user',
        'alias_allow_permanent'            => 'emailalias.allow_permanent',
        'alias_default_type'               => 'emailalias.alias_default_type',
        'alias_max_email_size_bytes'       => 'emailalias.max_email_size_bytes',
        'alias_max_attachment_size_bytes'  => 'emailalias.max_attachment_size_bytes',
        'cleanup_retention_days'           => 'emailalias.cleanup_retention_days',
        'admin_can_read_emails'            => 'emailalias.admin_can_read_emails',
        'alias_max_mailbox_size_bytes'     => 'emailalias.max_mailbox_size_bytes',
        'alias_max_user_storage_bytes'     => 'emailalias.max_user_storage_bytes',
        'version_check_enabled'            => 'emailalias.version_check_enabled',
        'health_check_visibility'          => 'emailalias.health_check_visibility',
        // Azure — services.azure.*
        'azure_client_id'                  => 'services.azure.client_id',
        'azure_client_secret'              => 'services.azure.client_secret',
        'azure_tenant_id'                  => 'services.azure.tenant_id',
    ];

    /**
     * Settings with their group, cast type, and default value.
     * Used to build the admin settings panel and seed defaults.
     */
    public const DEFINITIONS = [
        // ── app ──────────────────────────────────────────────────────────────────
        'app_name'                        => ['group' => 'app',      'cast' => 'string',  'default' => 'EmailAlias'],
        'app_locale'                      => ['group' => 'app',      'cast' => 'string',  'default' => 'en'],
        'version_check_enabled'           => ['group' => 'app',      'cast' => 'bool',    'default' => true],
        'health_check_visibility'         => ['group' => 'app',      'cast' => 'string',  'default' => 'public'],

        // ── auth ─────────────────────────────────────────────────────────────────
        'sso_enabled'                     => ['group' => 'auth',     'cast' => 'bool',    'default' => false],
        'azure_client_id'                 => ['group' => 'auth',     'cast' => 'string',  'default' => ''],
        'azure_client_secret'             => ['group' => 'auth',     'cast' => 'string',  'default' => ''],
        'azure_tenant_id'                 => ['group' => 'auth',     'cast' => 'string',  'default' => ''],
        'local_auth_enabled'              => ['group' => 'auth',     'cast' => 'bool',    'default' => true],
        'registration_enabled'            => ['group' => 'auth',     'cast' => 'bool',    'default' => false],

        // ── security ─────────────────────────────────────────────────────────────
        'two_factor_required'             => ['group' => 'security', 'cast' => 'bool',    'default' => false],

        // ── aliases ──────────────────────────────────────────────────────────────
        'alias_max_per_user'              => ['group' => 'aliases',  'cast' => 'int',     'default' => 20],
        'alias_allow_permanent'           => ['group' => 'aliases',  'cast' => 'bool',    'default' => true],
        'alias_default_type'              => ['group' => 'aliases',  'cast' => 'string',  'default' => 'session'],

        // ── email ────────────────────────────────────────────────────────────────
        'alias_max_email_size_bytes'      => ['group' => 'email',    'cast' => 'int',     'default' => 10485760],
        'alias_max_attachment_size_bytes' => ['group' => 'email',    'cast' => 'int',     'default' => 5242880],
        'cleanup_retention_days'          => ['group' => 'email',    'cast' => 'int',     'default' => 7],
        'admin_can_read_emails'           => ['group' => 'email',    'cast' => 'bool',    'default' => false],
        'alias_max_mailbox_size_bytes'    => ['group' => 'email',    'cast' => 'int',     'default' => 0],
        'alias_max_user_storage_bytes'    => ['group' => 'email',    'cast' => 'int',     'default' => 0],
    ];

    // ── Public API ────────────────────────────────────────────────────────────────

    /**
     * Get a setting value, falling back to the defined default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        if (array_key_exists($key, $all)) {
            return $this->cast($key, $all[$key]);
        }

        return $default ?? (self::DEFINITIONS[$key]['default'] ?? null);
    }

    /**
     * Set a single setting value.
     */
    public function set(string $key, mixed $value): void
    {
        $group = self::DEFINITIONS[$key]['group'] ?? 'app';

        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $this->encode($key, $value), 'group' => $group],
        );

        $this->flush();
    }

    /**
     * Bulk-set settings from an associative array.
     */
    public function fill(array $data): void
    {
        foreach ($data as $key => $value) {
            if (array_key_exists($key, self::DEFINITIONS)) {
                $group = self::DEFINITIONS[$key]['group'] ?? 'app';

                Setting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $this->encode($key, $value), 'group' => $group],
                );
            }
        }

        $this->flush();
    }

    /**
     * All settings as a raw key → value array (string values, before casting).
     * Results are cached for performance.
     */
    public function all(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached !== null) {
            return $cached;
        }

        try {
            $settings = Setting::all()->pluck('value', 'key')->toArray();
            Cache::forever(self::CACHE_KEY, $settings);

            return $settings;
        } catch (\Throwable) {
            // Settings table does not exist yet (fresh install before migrations).
            // Return defaults without caching so the next request retries the DB.
            return [];
        }
    }

    /**
     * Invalidate the settings cache.
     */
    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    // ── Internals ─────────────────────────────────────────────────────────────────

    private function encode(string $key, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $encoded = is_bool($value) ? ($value ? '1' : '0') : (string) $value;

        if (in_array($key, self::ENCRYPTED_KEYS, true)) {
            return encrypt($encoded);
        }

        return $encoded;
    }

    private function cast(string $key, ?string $raw): mixed
    {
        if ($raw === null) {
            return self::DEFINITIONS[$key]['default'] ?? null;
        }

        // Decrypt if needed
        if (in_array($key, self::ENCRYPTED_KEYS, true)) {
            try {
                $raw = decrypt($raw);
            } catch (\Throwable) {
                // Value was stored before encryption was enabled — use as-is
            }
        }

        $type = self::DEFINITIONS[$key]['cast'] ?? 'string';

        return match ($type) {
            'bool'   => in_array($raw, ['1', 'true', 'yes', 'on'], true),
            'int'    => (int) $raw,
            'float'  => (float) $raw,
            default  => $raw,
        };
    }
}
