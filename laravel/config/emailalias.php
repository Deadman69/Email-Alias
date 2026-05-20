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
     * Automatically delete emails older than this many days (0 = never).
     */
    'email_retention_days' => (int) env('CLEANUP_EMAIL_RETENTION_DAYS', 30),
];
