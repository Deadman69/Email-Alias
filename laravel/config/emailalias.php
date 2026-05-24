<?php

return [
    /*
     * The domain used for generated email addresses (legacy fallback).
     * When domains are configured in the database this value is superseded.
     * MX records must point to this server.
     */
    'domain' => env('APP_DOMAIN', 'example.com'),

    /*
     * Custom application logo.
     * Path relative to the 'public' storage disk (e.g. "logos/logo.png").
     * When empty the default built-in SVG icon is used.
     */
    'app_logo_path' => '',

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
     * Whether to allow custom local-part addresses (e.g. "my-alias@domain.com").
     * When disabled, only randomly-generated addresses are created.
     */
    'allow_custom' => (bool) env('ALIAS_ALLOW_CUSTOM', true),

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
     * How many days to keep audit log entries before automatically purging them.
     * 0 = keep indefinitely. Purge is performed daily by the CleanupAuditLogs job.
     */
    'audit_log_retention_days' => (int) env('AUDIT_LOG_RETENTION_DAYS', 365),

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
     * Application version — read from the VERSION file at the project root.
     * This value is never stored in the database; bump the VERSION file to
     * release a new version. Format: semantic versioning (MAJOR.MINOR.PATCH).
     *
     * Compatible with future automatic GitHub release checks:
     * compare this against the latest tag returned by the GitHub Releases API.
     */
    'version' => trim((string) @file_get_contents(base_path('VERSION'))) ?: '0.0.0',

    /*
     * Whether the admin panel should automatically check GitHub for a newer
     * release and display an update badge when one is available.
     * Can be toggled by a Super Admin in the Settings panel.
     */
    'version_check_enabled' => (bool) env('VERSION_CHECK_ENABLED', true),

    /*
     * Maximum total size of emails stored in a single mailbox (alias), in bytes.
     * New emails that would exceed this limit are silently dropped and the owner
     * is notified. 0 = unlimited.
     */
    'max_mailbox_size_bytes' => (int) env('ALIAS_MAX_MAILBOX_SIZE_BYTES', 0),

    /*
     * Maximum total size of emails stored across ALL mailboxes owned by a single
     * user, in bytes. 0 = unlimited.
     */
    'max_user_storage_bytes' => (int) env('ALIAS_MAX_USER_STORAGE_BYTES', 0),

    /*
     * Whether SSO login is enabled.
     */
    'sso_enabled' => (bool) env('SSO_ENABLED', false),

    /*
     * Active SSO provider: 'azure' | 'keycloak' | 'saml'
     *   'azure'    — Azure AD via Socialite (default)
     *   'keycloak' — generic OIDC discovery (Keycloak, Okta, Auth0, Dex, …)
     *   'saml'     — SAML 2.0 (requires composer require aacotroneo/laravel-saml2)
     */
    'sso_provider' => env('SSO_PROVIDER', 'azure'),

    // ── Generic OIDC — covers Keycloak and any OIDC-compliant IdP ────────────────
    'oidc_client_id'     => env('OIDC_CLIENT_ID', ''),
    'oidc_client_secret' => env('OIDC_CLIENT_SECRET', ''),
    // Issuer URL — e.g. https://keycloak.example.com/realms/myrealm
    // The /.well-known/openid-configuration endpoint is auto-discovered from this.
    'oidc_issuer_url'    => env('OIDC_ISSUER_URL', ''),

    // ── SAML 2.0 — requires aacotroneo/laravel-saml2 ─────────────────────────────
    'saml_idp_entity_id'   => env('SAML_IDP_ENTITY_ID', ''),
    'saml_idp_sso_url'     => env('SAML_IDP_SSO_URL', ''),
    'saml_idp_slo_url'     => env('SAML_IDP_SLO_URL', ''),     // optional
    'saml_idp_certificate' => env('SAML_IDP_CERTIFICATE', ''), // PEM — no header/footer
    'saml_sp_entity_id'    => env('SAML_SP_ENTITY_ID', ''),    // defaults to APP_URL
    // SP signing — when both cert + key are set, requests are signed
    'saml_sp_x509cert'     => '',   // PEM — no header/footer — override from DB
    'saml_sp_private_key'  => '',   // PEM — no header/footer — stored encrypted in DB
    // Attribute mapping — defaults cover most IdPs
    'saml_attr_email'      => '',   // attribute name for email (blank = use NameID)
    'saml_attr_name'       => '',   // attribute name for display name (blank = auto)

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
     * Health check visibility level.
     * Controls who can access /health and /api/v1/health.
     *   - 'public' : no authentication required (default)
     *   - 'auth'   : any authenticated user or valid API token
     *   - 'admin'  : only admins and super-admins
     */
    'health_check_visibility' => env('HEALTH_CHECK_VISIBILITY', 'public'),

    /*
     * Health check — SMTP server connection details.
     * Used to verify the SMTP receiver is reachable.
     */
    'health_smtp_host' => env('HEALTH_SMTP_HOST', 'smtp-server'),
    'health_smtp_port' => (int) env('HEALTH_SMTP_PORT', 25),

    /*
     * Health check — Reverb WebSocket server connection details.
     */
    'health_reverb_host' => env('HEALTH_REVERB_HOST', 'reverb'),
    'health_reverb_port' => (int) env('HEALTH_REVERB_PORT', 8080),

    /*
     * Prometheus metrics bearer token.
     * Set this to a strong random string to enable the /metrics endpoint.
     * Leave empty to disable (returns 503).
     */
    'metrics_bearer_token' => env('METRICS_BEARER_TOKEN', ''),

    /*
     * NOTE: All values above can be overridden at runtime by a Super Admin
     * via the Settings panel (/admin/settings). Overrides are stored in the
     * `settings` database table and applied on every request by the
     * BootstrapSettings middleware. The .env values serve as the fallback
     * when no DB override exists.
     */
];
