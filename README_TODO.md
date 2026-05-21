# EmailAlias — Implementation Checklist

---

## Infrastructure

- [x] `docker-compose.yml` — services: `app`, `smtp-server`, `reverb`, `worker`, `scheduler`, `db`, `caddy`
- [x] `docker-compose.dev.yml` — exposed ports, hot-reload volumes
- [x] `Dockerfile` Laravel (PHP 8.4-FPM, pgsql / pcntl / bcmath extensions)
- [x] `Dockerfile.dev` Laravel
- [x] `Dockerfile` smtp-server (Node.js 22 Alpine)
- [x] Caddy reverse proxy (automatic HTTPS via caddy-docker-proxy)
- [x] `artisan admin:create` command

---

## SMTP Receiver (Node.js)

- [x] SMTP server (`smtp-server` npm) — catch-all, no filters
- [x] STARTTLS support (optional)
- [x] Inbound mail parsing (`mailparser`)
- [x] POST to `/internal/inbound` with shared secret
- [x] Retry with exponential backoff (3 attempts)
- [x] Structured JSON logs
- [x] Graceful shutdown (SIGTERM → drain active connections)

---

## Database

- [x] `aliases` migration — ULID PK, type, duration, expires_at, soft deletes, webhook columns
- [x] `inbound_emails` migration — ULID PK, ULID FK to aliases, `is_truncated`, soft deletes
- [x] `email_attachments` migration — ULID PK, ULID FK to inbound_emails, path, checksum
- [x] `add_azure_id_to_users` migration — nullable unique `azure_id` column
- [x] `audit_logs` migration — varchar morph (compatible with ULID + int), immutable
- [x] `alias_shares` migration — ULID PK, alias_id + user_id + shared_by_id
- [x] `settings` migration — string PK key/value store
- [x] `personal_access_tokens` migration — base Sanctum table + `restricted_alias_ids` JSON column
- [x] `AliasFactory`, `InboundEmailFactory`
- [x] `DemoSeeder` — super_admin + user + 3 aliases + 5 realistic HTML emails + 1 share

---

## Models & Business Logic

- [x] `Alias` — `HasUlids`, `SoftDeletes`, `active()`/`expired()` scopes, `extendByDuration()`, webhook fields
- [x] `InboundEmail` — `HasUlids`, `SoftDeletes`, `markAsRead()`, `markAsUnread()`, `is_truncated`, `humanSize()`, `attachments()` relation
- [x] `Attachment` — `HasUlids`, `isImage()`, `humanSize()`, file deleted on model delete via `booted()` hook
- [x] `AuditLog` — immutable, polymorphic, `auditable_id` cast to string
- [x] `User` — `role` enum (user/admin/super_admin), `is_admin` accessor, 2FA, Passkeys, `azure_id`, `HasApiTokens`, per-user `locale`
- [x] `AliasShare` — ULID PK, read-only shared access, `user()`, `sharedBy()`
- [x] `Setting` — string PK key/value, managed by `SettingService`
- [x] `PersonalAccessToken` — extends Sanctum token, `restricted_alias_ids`, `canAccessAlias()`
- [x] `AliasType` enum — `Session | Duration | Permanent`
- [x] `Role` enum — `User | Admin | SuperAdmin`, hierarchical `isAtLeast()`
- [x] `AuditEvent` enum — 29 events including API, webhook, auth, profile and bulk events
- [x] `TokenAbility` enum — 9 scopes (user + admin), `label()`, `description()`, i18n-ready
- [x] `AliasService` — `create()`, `delete(actingUser)`, `extend()`, `suggestAlternative()`, `isAddressAvailable()`, `enforceRateLimit()`
- [x] `AuditLogger` — centralized `log()` service
- [x] `HtmlSanitizer` — HTMLPurifier: strip `on*`, injections, external images; regex fallback if package absent
- [x] `SettingService` — get/set/fill, cache, secret encryption, `CONFIG_MAP`; never caches empty result on fresh install
- [x] `ProcessInboundEmail` job — parse + truncation + attachments + broadcast + webhook dispatch
- [x] `DeliverWebhook` job — HMAC-SHA256 signed, deterministic JSON (`JSON_UNESCAPED_UNICODE|SLASHES`), `withBody()` for exact byte match, 3 retries with exponential backoff
- [x] `CleanupExpiredAliases` job — scheduled daily purge
- [x] `EmailReceived` event — broadcast on private channel `alias.{id}`
- [x] `MailboxSpamDetected` notification — stored in DB, shown in notification bell (1 notif/hour/alias max)
- [x] `MailboxQuotaExceeded` notification — stored in DB, shown in notification bell (1 notif/hour/alias/quota_type max)
- [x] `DeleteSessionAliasesOnLogout` listener
- [x] `BootstrapSettings` middleware — reads DB settings → `Config::set()` on every request
- [x] `SetLocale` middleware — user locale → platform default → `'en'` fallback
- [x] `config/emailalias.php` — defaults overridden at runtime by `SettingService`
- [x] Oversized emails truncated: body not stored, `is_truncated=true`, warning banner in UI
- [x] Attachments stored on private disk, authenticated download via `AttachmentController`
- [x] Per-email storage limit (`alias_max_email_size_bytes`) — body truncated, `is_truncated=true`
- [x] Per-mailbox storage quota (`alias_max_mailbox_size_bytes`) — drops email if quota exceeded, notifies owner
- [x] Per-user storage quota (`alias_max_user_storage_bytes`) — drops email if total across all mailboxes exceeded, notifies owner
- [x] `MailboxQuotaExceeded` notification — stored in DB, shown in notification bell (1 notif/hour/alias/quota_type max)
- [x] App version field — editable by Super Admin, stored in DB, visible in admin panel (semantic versioning enforced)
- [ ] NTH: Super Admin manual quota override per mailbox (column on `aliases` table: `custom_max_bytes`)
- [ ] NTH: Super Admin manual quota override per user (column on `users` table: `custom_max_storage_bytes`)

---

## Auth & Security

- [x] Local login/password (Fortify)
- [x] 2FA TOTP (Fortify)
- [x] Passkeys / WebAuthn (Chisel)
- [x] `admin` middleware (Admin + SuperAdmin)
- [x] `super_admin` middleware (SuperAdmin only — platform settings)
- [x] `internal` middleware — SMTP webhook accessible from internal network only
- [x] `AliasPolicy` — owner + shared users (read); `share` ability owner-only
- [x] `InboundEmailPolicy` — owner + shared users for `view`; owner-only for `delete`; null alias → 403 not 500
- [x] IDOR fix: `Inbox::markAllRead()` — explicit `authorize('view', $alias)`
- [x] Login rate limiting (Fortify)
- [x] SSO Azure AD (Socialite + `socialiteproviders/microsoft-azure`) — configurable from UI
- [x] Alias creation rate limiting (10/min/user via `RateLimiter`)
- [x] Per-alias inbound email rate limiting (10/min/alias) — drops over-limit emails, notifies owner
- [x] Enforced 2FA configurable (Super Admin panel)
- [x] API tokens — Sanctum personal access tokens with ability scopes and optional alias restriction
- [x] Token abilities server-side filtered by role (regular users cannot create admin-scoped tokens)
- [x] Super Admins cannot be modified via API; `super_admin` role cannot be assigned via API

---

## Controllers & Routes

- [x] `InboundEmailController` — `POST /internal/inbound` → dispatch job → 202
- [x] `AttachmentController` (web) — authenticated download via Gate
- [x] `SsoController` — Azure AD redirect + callback
- [x] `DocsController` removed — API docs served by Scramble at `/docs/api`
- [x] `Api/V1/AliasController` — index, store, show, destroy (IDOR protected, token alias check)
- [x] `Api/V1/EmailController` — index (brief), show (full + mark read), destroy
- [x] `Api/V1/AttachmentController` — index, download
- [x] `Api/V1/Admin/AliasController` — index (all), destroy (any alias)
- [x] `Api/V1/Admin/UserController` — index, update (role/status; no super_admin modification)
- [x] `Api/V1/Admin/AuditLogController` — index with filters, paginated up to 200
- [x] Admin API routes use `'admin'` middleware alias (not class reference)
- [x] Mailbox routes: `/mailbox`, `/mailbox/{alias}`, `/mailbox/emails/{email}`
- [x] Admin routes: `/admin`, `/admin/audit`, `/admin/settings`
- [ ] Dedicated user stats dashboard (currently redirects to mailbox)

---

## Livewire / UI

- [x] `Mailbox\Dashboard` — aliases (own + shared), create, delete/extend (owner), share modal, webhook modal
- [x] `Mailbox\Inbox` — email list, read/unread filter, real-time Reverb, mark read, delete (ULID routing)
- [x] `Mailbox\ViewEmail` — email detail, attachments, truncation banner, external images on demand
- [x] `Admin\Dashboard` — global alias view, stats, search, user filter, delete
- [x] `Admin\AuditLogViewer` — log consultation
- [x] `Admin\Settings` — 5 tabs (General, Auth, Security, Aliases, Email), saved to DB with cache-bust
- [x] `Settings\Profile` — name, email, per-user language preference
- [x] `Settings\ApiTokens` — create tokens (abilities, alias scope, expiry), revoke, plain-text shown once
- [x] Webhook modal — URL, signing secret with copy button, manual rotation with confirmation, PHP + Node.js verification examples
- [x] Settings navigation includes API Tokens link
- [x] HTML email rendered in `<iframe sandbox>`, external images blocked (anti-tracking), purified HTML
- [x] Copy alias address to clipboard (one-click button)
- [ ] Real user dashboard with personal stats (not just a redirect)
- [ ] Session alias: remove expiry countdown (they are deleted on logout, not by timer expiry)
- [ ] Real-time expiry countdown in sidebar/card
- [ ] Create mailbox for a specific user (admin panel)
- [ ] User management in admin panel:
    - [ ] Display user email in user list
    - [ ] Add exact datetime tooltips on all relative dates ("in 1h", "3 days ago"…)
    - [ ] Per-user timezone preference (e.g. Europe/Paris, America/New_York)
    - [ ] Timezone-aware display for all absolute dates
    - [ ] Search user by email for large user bases (10,000+)
    - [ ] Same search in audit log
    - [ ] Dedicated users page with role/status table

---

## API & Webhooks

- [x] Personal access tokens (Sanctum) — ability scopes + optional alias restriction
- [x] Admin tokens — `admin:aliases`, `admin:users`, `admin:logs`; settings excluded from API
- [x] Token expiry (`expires_at`)
- [x] Audit trail for all API actions
- [x] OpenAPI spec auto-generated by Scramble (no manual spec to maintain)
- [x] API docs gated behind `auth + verified` middleware
- [x] Per-alias webhooks — HMAC-SHA256, signed on raw body, JSON flags documented
- [x] Webhook secret generated once; manual rotation only (with confirmation dialog)
- [x] Webhook delivery: queued job, 3 attempts, exponential backoff (30s / 120s / 300s)
- [x] Failed deliveries logged as `WebhookFailed` in audit log

---

## i18n

- [x] `SetLocale` middleware — user locale → platform `app_locale` → `'en'` fallback
- [x] `lang/en.json` — full English translation file (~170 keys)
- [x] `lang/fr.json` — full French translation file
- [x] Per-user locale preference (profile settings)
- [x] Platform default language setting (Super Admin panel)
- [x] `TokenAbility` labels and descriptions translated via `__()`
- [ ] Carbon locale set for date formatting (diffForHumans in user language)

---

## Tests

- [x] `CreateAliasTest` — creation, custom, duplicates, limits, deletion, extension
- [x] `InboundEmailTest` — reception, authorization, mark read/unread
- [x] `AdminAccessTest` — access denied for non-admins
- [x] Fortify auth tests (login, 2FA, password reset, email verification)
- [ ] `AliasService` unit tests
- [ ] `CleanupExpiredAliases` job tests
- [ ] `ProcessInboundEmail` job tests
- [ ] API endpoint tests (ability enforcement, IDOR, alias restriction)
- [ ] Webhook delivery tests (signature, retry, failure logging)
