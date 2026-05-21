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

## API tokens

Users can issue personal access tokens (Sanctum) to interact with their mailboxes programmatically.

**Scope restrictions:**
- Each token carries a set of ability strings (`aliases:read`, `emails:read`, `emails:delete`, etc.)
- Tokens can optionally be restricted to a specific list of alias IDs (`restricted_alias_ids` column). A request for an alias not in that list is rejected with a `403` even if the policy would otherwise allow it.
- Admin abilities (`admin:aliases`, `admin:users`, `admin:logs`) are only assignable to users with `role >= admin`. The server strips any admin ability silently if the token creator is a regular user.

**Token lifecycle:**
- Tokens can have an optional expiry date (`expires_at`). Expired tokens are rejected.
- Tokens can be revoked from the Settings → API Tokens page. Revocation is logged in the audit log.
- The plain-text token value is shown **once** at creation and never stored (only the SHA-256 hash is persisted).

**Settings are never accessible via API.** Platform configuration is UI-only.

**Super Admins cannot be modified via the API.** The `admin:users` ability only allows changing `role` between `user` and `admin`, and only for non-super-admin accounts.

---

## Webhooks

Each alias can have a webhook URL configured. When an email is received, EmailAlias dispatches a signed HTTP POST to that URL.

**Payload signing (HMAC-SHA256):**

Every delivery includes an `X-Webhook-Signature` header:
```
X-Webhook-Signature: sha256=<hex_digest>
```

The digest is computed as:
```
HMAC-SHA256(webhook_secret, raw_json_body)
```

Recipients must verify this signature before processing the payload. An example in PHP:
```php
$expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
if (!hash_equals($expected, $request->header('X-Webhook-Signature'))) {
    abort(401);
}
```

**Secret rotation:** The webhook secret is automatically regenerated whenever the URL is changed, invalidating all previous signatures. This prevents a compromised secret from being reused if the endpoint URL is also rotated.

**Delivery reliability:** Webhooks are dispatched as queued jobs with 3 attempts and exponential backoff (30 s, 120 s, 300 s). Failures are logged as `WebhookFailed` in the audit log.

---

## Audit log

Every security-relevant action is recorded in the `audit_logs` table:

- Alias created, deleted, extended, shared, unshared
- Email read, deleted
- Admin actions (alias deleted by admin, user role changed)
- Authentication events (login, logout, SSO)
- API token created, revoked
- API actions: alias created/deleted, email read/deleted (via API)
- Webhook delivered, webhook failed

Logs are **append-only** — there is no delete or edit action in the application. They store the acting user ID, event type, target resource, IP address, and a metadata payload.

---

## Reporting a vulnerability

If you discover a security vulnerability, please report it privately to the maintainers before disclosing it publicly. Do not open a GitHub issue for security vulnerabilities.
