# EmailAlias — Developer Guide

Everything you need to run EmailAlias locally — no real domain, no DNS, no server required.

---

## Prerequisites

| Tool | Min version |
|---|---|
| Docker Desktop | 4.x |
| Git | — |

> **No PHP, Node, or Composer needed locally.** Everything runs inside containers.

---

## Setup

```bash
git clone <repo-url> email-alias
cd email-alias
cp .env.example .env
```

Minimum `.env` values for local development:

```env
APP_URL=http://localhost:8000
APP_DOMAIN=dev.local              # Fictional domain — no DNS needed
SMTP_INTERNAL_SECRET=dev-secret   # Must match between smtp-server and Laravel
DB_PASSWORD=localpassword
```

Install dependencies:

```bash
# Laravel
docker run --rm -v "$PWD/laravel:/app" -w /app composer:2 composer install

# SMTP server
docker run --rm -v "$PWD/smtp-server:/app" -w /app node:22-alpine npm install
```

---

## Start

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d
```

| Service | URL | Description |
|---|---|---|
| Laravel app | http://localhost:8000 | Main UI |
| PostgreSQL | localhost:5432 | Database |
| Reverb WebSocket | localhost:8080 | Real-time |
| SMTP receiver | localhost:2525 | Email ingestion (non-privileged port in dev) |

---

## Database

```bash
# Migrations only
docker compose exec app php artisan migrate

# Migrations + demo data
docker compose exec app php artisan migrate --seed
```

Demo accounts created by the seeder:

| Email | Password | Role |
|---|---|---|
| admin@example.com | password | Super Admin |
| paul@example.com | password | User |

---

## Frontend assets

```bash
# One-time build
docker compose exec app npm run build

# Watch mode (hot-reload)
docker compose exec app npm run dev
```

> If you see `Vite manifest not found`, run `npm run build` first.

---

## Testing email ingestion

No real domain needed. Two approaches:

### Option A — Direct webhook (recommended)

Create an alias in the UI (`http://localhost:8000/mailbox`), then POST directly to the internal endpoint:

```bash
curl -X POST http://localhost:8000/internal/inbound \
  -H "Content-Type: application/json" \
  -H "X-SMTP-Secret: dev-secret" \
  -d '{
    "to": ["xk3f9a2b@dev.local"],
    "from_address": "sender@example.com",
    "from_name": "Test Sender",
    "subject": "Hello from curl",
    "body_html": "<h1>Test</h1><p>External image: <img src=\"https://example.com/tracker.png\"></p>",
    "body_text": "Test email.",
    "headers": {},
    "size_bytes": 512
  }'
```

The email appears in real time without a page refresh.

### Option B — SMTP end-to-end

```bash
# Using swaks (install with: brew install swaks / scoop install swaks)
swaks --to xk3f9a2b@dev.local \
      --from sender@example.com \
      --server localhost --port 2525 \
      --header "Subject: SMTP test" \
      --body "Hello from swaks"
```

---

## Daily commands

```bash
# Artisan
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan route:list
docker compose exec app php artisan tinker

# Tests (Pest)
docker compose exec app php artisan test --compact
docker compose exec app php artisan test --compact --filter=AliasService

# Code style (Pint)
docker compose exec app vendor/bin/pint --dirty

# Clear all caches
docker compose exec app php artisan optimize:clear

# Logs
docker compose logs -f app
docker compose logs -f smtp-server
```

---

## Project structure

```
email-alias/
├── docker-compose.yml          # Production services
├── docker-compose.dev.yml      # Dev overrides (exposed ports, volumes)
├── .env.example                # Infrastructure variables template
│
├── smtp-server/                # SMTP micro-service (Node.js)
│   └── src/index.js            # Receives SMTP → POST /internal/inbound
│
└── laravel/                    # Main application (Laravel 13)
    ├── app/
    │   ├── Console/Commands/   # admin:create
    │   ├── Enums/              # AliasType, AuditEvent, Role
    │   ├── Events/             # EmailReceived (Reverb broadcast)
    │   ├── Http/
    │   │   ├── Controllers/    # Internal webhook, AttachmentController, SsoController
    │   │   └── Middleware/     # EnsureUserIsAdmin, EnsureUserIsSuperAdmin,
    │   │                       # EnsureInternalRequest, BootstrapSettings, SetLocale
    │   ├── Jobs/               # ProcessInboundEmail, CleanupExpiredAliases
    │   ├── Livewire/
    │   │   ├── Mailbox/        # Dashboard, Inbox, ViewEmail
    │   │   ├── Admin/          # Dashboard, AuditLogViewer, Settings
    │   │   └── Settings/       # Profile, Security, Appearance
    │   ├── Models/             # User, Alias, AliasShare, InboundEmail,
    │   │                       # Attachment, AuditLog, Setting
    │   ├── Policies/           # AliasPolicy, InboundEmailPolicy
    │   └── Services/           # AliasService, AuditLogger, SettingService,
    │                           # HtmlSanitizer
    ├── config/emailalias.php   # EmailAlias-specific config (overridden by SettingService)
    ├── database/
    │   ├── migrations/         # Database schema
    │   └── seeders/            # DemoSeeder
    ├── lang/
    │   ├── en.json             # English translations
    │   └── fr.json             # French translations
    ├── resources/views/livewire/
    │   ├── mailbox/            # Mailbox views
    │   └── admin/              # Admin views
    └── routes/
        ├── web.php             # Mailbox + admin routes
        └── internal.php        # SMTP webhook (not publicly routable)
```

---

## Troubleshooting

**`Error: no such container: email-alias-app-1`**
→ Containers are not running. Run `docker compose up -d`.

**`Vite manifest not found`**
→ Assets not compiled. Run `docker compose exec app npm run build`.

**Email doesn't appear in real time**
→ Check `BROADCAST_CONNECTION=reverb` in `.env`. Verify the `reverb` service is running (`docker compose ps`). Check `VITE_REVERB_*` variables and rebuild assets.

**`403 Unauthorized internal request` on `/internal/inbound`**
→ The `X-SMTP-Secret` header doesn't match `SMTP_INTERNAL_SECRET` in `.env`.

**Port 2525 already in use**
→ Change `SMTP_RECEIVER_PORT` in `.env` and the port mapping in `docker-compose.dev.yml`.
