# EmailAlias — Security Model

---

## Roles

Three roles with a strict hierarchy (`user < admin < super_admin`):

| Role | Capabilities |
|---|---|
| `user` | Owns and manages their own aliases and mailboxes |
| `admin` | Read-only access to all aliases and emails (if enabled), audit log viewer, global dashboard |
| `super_admin` | Full platform configuration (SSO, 2FA, limits, retention, secrets) |

Role promotion is done via the CLI:
```bash
docker compose exec app php artisan admin:create --super-admin
```

Admins bypass all Laravel Policy checks via the `before()` hook — they are explicitly granted access to all resources. Super Admin access is gated by a dedicated middleware (`EnsureUserIsSuperAdmin`).

---

## Access control (IDOR prevention)

All resource access is enforced through Laravel Policies registered in `AuthServiceProvider`:

- **`AliasPolicy`** — controls view, update, delete, and share on each alias
- **`InboundEmailPolicy`** — controls view and delete on each email

Key rules:
- A user can only **view** an alias they own or one explicitly shared with them
- Only the **owner** can delete, extend, or share an alias
- Only the **owner** can delete emails — shared users are strictly read-only
- Email policies use `withTrashed()` when loading the parent alias to prevent a soft-deleted alias from returning `null` and causing a `500` instead of a `403`
- `markAllRead` in `Inbox.php` performs an explicit `$this->authorize('view', $alias)` even though the `$aliasId` is `#[Locked]` — defense in depth

---

## Shared mailboxes

An owner can share an alias with other users. Shares grant **read-only access** (view + mark as read). Shared users cannot:
- Delete emails
- Delete, extend, or rename the alias
- Share the alias further

Shares are stored in the `alias_shares` table. Access checks query this table directly (not via a cached relationship) to avoid stale permission grants.

---

## Encrypted secrets

Azure AD SSO credentials are stored in the `settings` database table. The `azure_client_secret` is automatically encrypted at rest using Laravel's `encrypt()` / `decrypt()` helpers (AES-256-CBC, signed with `APP_KEY`).

Encryption is handled transparently in `SettingService`. If a value was stored before encryption was enabled, the service decrypts gracefully with a try/catch and returns the raw value.

**`APP_KEY` must be rotated manually if compromised** — all encrypted secrets must be re-entered after a key rotation.

---

## SMTP internal endpoint

The `/internal/inbound` route accepts emails from the SMTP receiver service. It is protected by `EnsureInternalRequest` middleware, which validates a shared secret via the `X-SMTP-Secret` header:

```
X-SMTP-Secret: <value of SMTP_INTERNAL_SECRET in .env>
```

This route has **no CSRF, no session, no authentication** — it is only accessible over the internal Docker network. Never expose port 9000 (Laravel PHP-FPM) or the internal route to the public internet.

---

## HTML sanitization

All incoming HTML email bodies are processed by `HtmlSanitizer` (backed by `ezyang/htmlpurifier`) before rendering:

- All event handlers (`onerror`, `onload`, `onclick`…) are stripped
- Dangerous tags (`<script>`, `<object>`, `<embed>`…) are removed
- External images (`<img src="https://…">`) are replaced by a placeholder by default (user can opt in per-email)
- `background-image: url(https://…)` in `style` attributes is stripped
- All links have `target="_blank" rel="noopener noreferrer"` forced

Emails are rendered inside a sandboxed `<iframe sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox">` with `referrerpolicy="no-referrer"`.

---

## Admin email access

By default, admins **cannot read email bodies**. This is controlled by the `admin_can_read_emails` platform setting (defaults to `false`).

When disabled, the admin dashboard shows only metadata (sender, subject, alias, date). Enabling it gives admins full access to email content and should be treated as a sensitive configuration change. All access is logged in the audit log.

---

## Audit log

Every security-relevant action is recorded in the `audit_logs` table:

- Alias created, deleted, extended, shared, unshared
- Email read, deleted
- Admin actions (alias deleted by admin)
- Authentication events (login, logout, SSO)

Logs are **append-only** — there is no delete or edit action in the application. They store the acting user ID, event type, target resource, IP address, and a metadata payload.

---

## Reporting a vulnerability

If you discover a security vulnerability, please report it privately to the maintainers before disclosing it publicly. Do not open a GitHub issue for security vulnerabilities.
