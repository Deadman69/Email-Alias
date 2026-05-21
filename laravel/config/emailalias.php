<?php

return [
    /*
     * The domain used for generated email addresses.
     * MX records must point to this server.
     */
    'domain' => env('APP_DOMAIN', 'example.com'),

    /*
     * Shared secret between the SMTP receiver and Laravel.
     * Must match SMTP_INTERNAL_SECRET in the smtp-server environment.
     */
    'smtp_secret' => env('SMTP_INTERNAL_SECRET', ''),

    /*
     * Maximum number of aliases a single user can own at once.
     */
    'max_aliases_per_user' => (int) env('ALIAS_MAX_PER_USER', 20),

    /*
     * Default TTL (in hours) for session-type aliases.
     */
    'session_alias_ttl_hours' => 2,

    /*
     * Whether to allow permanent aliases (no expiration).
     */
    'allow_permanent' => (bool) env('ALIAS_ALLOW_PERMANENT', true),

    /*
     * Whether admins can read the body of received emails.
     */
    'admin_can_read_emails' => (bool) env('ADMIN_CAN_READ_EMAILS', false),

    /*
     * How many days to keep soft-deleted aliases and emails before permanently
     * removing them (0 = purge immediately on the next cleanup run).
     * This is the grace period; hard-purge happens via the CleanupExpiredAliases job.
     */
    'cleanup_retention_days' => (int) env('CLEANUP_RETENTION_DAYS', 7),

    /*
     * Maximum size of a single inbound email (in bytes).
     * Emails exceeding this limit are stored without body (headers + metadata only).
     * Default: 10 MB.
     */
    'max_email_size_bytes' => (int) env('ALIAS_MAX_EMAIL_SIZE_BYTES', 10 * 1024 * 1024),

    /*
     * Maximum size of a single attachment (in bytes).
     * Attachments exceeding this limit are silently skipped.
     * Default: 5 MB.
     */
    'max_attachment_size_bytes' => (int) env('ALIAS_MAX_ATTACHMENT_SIZE_BYTES', 5 * 1024 * 1024),

    /*
     * Whether SSO login is enabled.
     */
    'sso_enabled' => (bool) env('SSO_ENABLED', false),

    /*
     * Whether local login (email + password) is enabled.
     * Disable in production if SSO is the only authentication method.
     */
    'local_auth_enabled' => (bool) env('LOCAL_AUTH_ENABLED', true),

    /*
     * Whether self-registration is allowed.
     */
    'registration_enabled' => (bool) env('REGISTRATION_ENABLED', false),

    /*
     * Whether 2FA is required for all users.
     */
    'two_factor_required' => (bool) env('TWO_FACTOR_REQUIRED', false),

    /*
     * Default alias type for new aliases.
     */
    'alias_default_type' => env('ALIAS_DEFAULT_TYPE', 'session'),

    /*
     * NOTE: All values above can be overridden at runtime by a Super Admin
     * via the Settings panel (/admin/settings). Overrides are stored in the
     * `settings` database table and applied on every request by the
     * BootstrapSettings middleware. The .env values serve as the fallback
     * when no DB override exists.
     */
];
