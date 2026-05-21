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
- [x] Health page for all services (`/health` web + `/api/v1/health` JSON, configurable visibility: public/auth/admin)

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
- [x] `EnsureHealthCheckAccess` middleware — public/auth/admin visibility for health endpoints
- [x] `HealthController` — checks DB, cache, storage, SMTP, Reverb; returns HTML or JSON; 200/503
- [x] `config/emailalias.php` — defaults overridden at runtime by `SettingService`
- [x] Oversized emails truncated: body not stored, `is_truncated=true`, warning banner in UI
- [x] Attachments stored on private disk, authenticated download via `AttachmentController`
- [x] Per-email storage limit (`alias_max_email_size_bytes`) — body truncated, `is_truncated=true`
- [x] Per-mailbox storage quota (`alias_max_mailbox_size_bytes`) — drops email if quota exceeded, notifies owner
- [x] Per-user storage quota (`alias_max_user_storage_bytes`) — drops email if total across all mailboxes exceeded, notifies owner
- [x] `MailboxQuotaExceeded` notification — stored in DB, shown in notification bell (1 notif/hour/alias/quota_type max)
- [x] App version — hardcoded in `VERSION` file, read-only in admin panel, `version_check_enabled` toggle for future GitHub auto-check
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
- [x] Multi-provider SSO — Azure AD, Keycloak/Generic OIDC (zero extra packages, OIDC discovery), SAML 2.0 stubs (requires `aacotroneo/laravel-saml2`)
- [x] `is_active` flag on `users` — SCIM deprovision blocks login (Fortify + SSO callback)
- [x] `external_id` on `users` — `"{provider}:{sub}"` for provider-scoped SSO identity; backward compat with `azure_id`
- [x] `SsoProvider` enum — `azure | keycloak | saml`; configurable from Super Admin settings panel

---

## Controllers & Routes

- [x] `InboundEmailController` — `POST /internal/inbound` → dispatch job → 202
- [x] `AttachmentController` (web) — authenticated download via Gate
- [x] `SsoController` — provider-agnostic redirect + callback (Azure / OIDC / SAML)
- [x] `SamlController` — SAML metadata, login, ACS (CSRF-exempt), SLS; stubs ready for `aacotroneo/laravel-saml2`
- [x] `DocsController` removed — API docs served by Scramble at `/docs/api`
- [x] `Api/V1/AliasController` — index, store, show, destroy (IDOR protected, token alias check)
- [x] `Api/V1/EmailController` — index (brief), show (full + mark read), destroy
- [x] `Api/V1/AttachmentController` — index, download
- [x] `Api/V1/Admin/AliasController` — index (all), destroy (any alias)
- [x] `Api/V1/Admin/UserController` — index, update (role/status; no super_admin modification)
- [x] `Api/V1/Admin/AuditLogController` — index with filters, paginated up to 200
- [x] Admin API routes use `'admin'` middleware alias (not class reference)
- [x] Mailbox routes: `/mailbox`, `/mailbox/{alias}`, `/mailbox/emails/{email}`
- [x] Admin routes: `/admin`, `/admin/users`, `/admin/audit`, `/admin/settings`
- [x] Health endpoints: `GET /health` (HTML/JSON) + `GET /api/v1/health` (JSON) — visibility driven by `health_check_visibility` setting
- [x] Dedicated user stats dashboard at `/dashboard` — aliases, emails, unread, storage used, recent emails

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
- [x] `UserDashboard` — personal stats (active aliases, shared with me, emails, unread, storage), recent emails list
- [x] Session alias: shows "Ends on logout" instead of countdown in dashboard cards and inbox header
- [x] Real-time expiry countdown in dashboard cards and inbox header (Alpine.js `setInterval`)
- [x] Create mailbox for a specific user (admin users page — per-row action button + modal)
- [x] User management in admin panel (`/admin/users`):
    - [x] Dedicated users page with name, email, role badge/selector, alias count, joined date
    - [x] Display user email in user list
    - [x] Role update inline (dropdown, SuperAdmin protected)
    - [x] Search by name or email (live, paginated, scales to large user bases)
    - [x] Exact datetime tooltips on all relative dates (`title` with `isoFormat('LLL')`)
    - [x] Per-user timezone preference — `timezone` column on `users`, selector in profile settings
    - [x] Timezone-aware display — `SetLocale` middleware applies `date_default_timezone_set()`
- [x] Admin sidebar navigation — Dashboard, Users, Audit Log, Settings links
- [x] Search by user name/email in audit log

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
- [x] `lang/en.json` — full English translation file (~195 keys)
- [x] `lang/fr.json` — full French translation file
- [x] Per-user locale preference (profile settings)
- [x] Platform default language setting (Super Admin panel)
- [x] `TokenAbility` labels and descriptions translated via `__()`
- [x] Carbon locale set for `diffForHumans()` — `SetLocale` middleware calls `Carbon::setLocale($locale)`
- [x] Per-user timezone — `SetLocale` calls `date_default_timezone_set($timezone)` for all Carbon output
- [x] `isoFormat('LLL')` used in tooltips for locale-aware absolute dates

---

## Enterprise / Scale

### 🔴 P0 — Bloquants production

- [x] Object storage (MinIO / S3) — `ATTACHMENT_DISK=s3`, driver S3 path-style, MinIO service dans docker-compose
- [x] Full-text search dans les e-mails — `tsvector` PostgreSQL, GIN index, scope `search()`, UI + API
- [x] Tests IDOR & isolation utilisateurs — user A ne peut pas accéder aux ressources de user B
- [x] SCIM 2.0 provisioning (Azure AD) — endpoints Users CRUD, bearer token, déprovision via `active=false`

### 🟡 P1 — Avant généralisation

- [x] Export audit logs — téléchargement CSV / JSON depuis le panel admin
- [x] Reverb scaling — config sticky-session Caddy, `REVERB_MAX_CONNECTIONS`, doc horizontale
- [x] Backup & restore — service pg_dump schedulé dans docker-compose + runbook BACKUP.md

### 🟢 P2 — Itératif

- [x] Métriques Prometheus — endpoint `/metrics` (format texte Prometheus), scraper dans docker-compose
- [x] Gestion des sessions actives — liste sessions en cours + révocation individuelle / globale

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


---

# Fix bugs

- [ ] Quand un admin créer une boite mail pour un user, dans les logs c'est l'utilisateur lui même qui créer le mail et pas l'admin
- [ ] Quand un super-admin modifie les settings on ne voit pas ce qu'il a changé. Bien entendu il faut masquer les valeurs secrètes.
- [ ] Les limites de création de boite mail considèrent également les soft-deleted, donc ça bloque même si on supprime
- [ ] Le refresh temp réel de la boite mail ne fonctionne pas.
    - [ ] Il faudrait également ajouter un bouton "refresh" manuel et avoir un timer pour le refresh automatique éventuellement.
- [ ] Filtrage des resources mails dans le service HtmlSanitizer.php, si on ne met pas la ligne `$config->set('URI.DisableResources', true);` on a l'erreur suivante, mais si on la met les images ne chargent plus jamais (même en les activant)
```
vendor\ezyang\htmlpurifier\library\HTMLPurifier\URIScheme\data.php:90

tempnam(): file created in the system's temporary directory 
```
- [ ] Quand les alias Permanent sont désactivés dans la config par un super-admin, il faut masquer l'option (ou la désactiver clairement avec une tooltip) pour les utilisateurs qui créent leur boite mail
- [ ] Il faut pouvoir désactiver les mail custom comme les mails permanent
- [ ] Les webhooks des emails ne sont pas envoyés (peut-être un problème SQLite ? `(PDOException(code: HY000): SQLSTATE[HY000]: General error: 1 no such function: to_tsvector at F:/Projets Perso/Email-Alias/laravel/vendor/laravel/framework/src/Illuminate/Database/Connection.php:581)` mais pas sûr)
- [ ] Il n'y a aucune option pour delete des mails reçu
- [ ] La doc API n'est pas accessible via `/docs/api` (erreur 404)
- [ ] Dans la partie settings de l'admin, dans "authentication", quand on clique sur "Enable SSO" il faut sauvegarder la page avant de pouvoir modifier. Il faut débloquer/bloquer les champs instantanément si on active/désactive la checkbox
    - [ ] "SCIM bearer token " ne devrait pas être désactivé  si on utilise pas le SSO ?
- [ ] Socialite Azure, en local j'ai cette erreur `InvalidArgumentException : vendor\laravel\framework\src\Illuminate\Support\Manager.php:108  -   Driver [azure] not supported.`
- [ ] Socialite Keycloak / OIDC :  `Type of App\Services\Sso\OidcProvider::$scopeSeparator must not be defined (as in class Laravel\Socialite\Two\AbstractProvider) `
- [ ] Socialite SAML : `Driver [saml] not supported. ` --> Il faut installer un package ? 
- [ ] Super-admin config catégorie "Email" : il faut mieux indiquer pour les mailbox size & user storage la dépendance entre les deux. Actuellement on dirait que c'est complètement décorrellé
- [ ] "Allow admins to read email bodies" n'a l'air de ne rien faire
- [ ] Certaines popup sont trop petites, par exemple "Create API token" dans le profile
    - [ ] Inviter un utilisateur à la mailbox, trop petit aussi
    - [ ] Gestion des webhooks d'une mailbox, trop petit aussi
- [ ] Les confirmations de delete utilisent des éléments HTML/JS natifs, il faudrait utiliser des éléments custom FluxUI pour valider le delete
- [ ] Dans la sidebar, les notifications ont un gros bouton vide sans texte, il faudrait améliorer un peu ça
- [ ] Dans le "audit-log-viewer", l'icone "magnifying-glass" est décalée, probablement à cause du flex sur l'élément parent.
- [ ] Les super-admin n'ont aucun moyens de créer des tokens d'API pour l'appli elle même et pas pour un user (pour des exports vers d'autres services par exemple)
- [ ] Toutes les tooltips doivent utiliser les tooltips FluxUI
- [ ] Dans la config super-admin, pouvoir changer le logo de l'application (comme pour le nom), principalement pour la sidebar & page de login (app-logo.blade.php)