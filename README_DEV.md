# EmailAlias — Developer Guide

No PHP, Node, or Composer needed locally — everything runs inside containers.

---

## Setup

```bash
git clone <repo-url> email-alias && cd email-alias
cp .env.example .env
```

Minimum `.env` values for local development (edit `laravel/.env`):

```env
APP_URL=http://localhost:8000
SMTP_INTERNAL_SECRET=changeme
DB_PASSWORD=localpassword
```

Install dependencies inside containers:

```bash
docker run --rm -v "$PWD/laravel:/app" -w /app composer:2 composer install
docker run --rm -v "$PWD/smtp-server:/app" -w /app node:22-alpine npm install
```

---

## Start

```bash
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed   # includes demo data
```

| Service | URL |
|---|---|
| App | http://localhost:8000 |
| PostgreSQL | localhost:5432 |
| Reverb | localhost:8080 |
| SMTP receiver | localhost:2525 |

Demo accounts (from `DemoSeeder`):

| Email | Password | Role |
|---|---|---|
| admin@example.com | password | Super Admin |
| dev@example.com | password | User |

---

## Daily commands

```bash
# Artisan
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan tinker

# Tests (Pest)
docker compose exec app php artisan test --compact
docker compose exec app php artisan test --filter=AliasService

# Code style (Pint)
docker compose exec app vendor/bin/pint --dirty

# Assets
docker compose exec app npm run dev     # watch mode
docker compose exec app npm run build   # one-time

# Logs
docker compose logs -f app
docker compose logs -f smtp-server
```

---

## Testing email ingestion

**Option A — curl directly to the internal endpoint (fastest)**

Create an alias in the UI first, then:

```bash
curl -X POST http://localhost:8000/internal/inbound \
  -H "Content-Type: application/json" \
  -H "X-SMTP-Secret: changeme_smtp_secret" \
  -d '{
    "to": ["xk3f9a2b@dev.local"],
    "from_address": "sender@example.com",
    "from_name": "Test Sender",
    "subject": "Hello, this is a test !",
    "body_html": "<h1>Hello this is a test email</h1><img src='https://upload.wikimedia.org/wikipedia/commons/thumb/b/b6/SIPI_Jelly_Beans_4.1.07.tiff/lossy-page1-250px-SIPI_Jelly_Beans_4.1.07.tiff.jpg' /><p>Did you see the file above ?</p>",
    "body_text": "<h1>Hello this is a test email</h1><img src='https://upload.wikimedia.org/wikipedia/commons/thumb/b/b6/SIPI_Jelly_Beans_4.1.07.tiff/lossy-page1-250px-SIPI_Jelly_Beans_4.1.07.tiff.jpg' /><p>Did you see the file above ?</p>",
    "headers": {},
    "size_bytes": 100
  }'
```

**Option B — full SMTP end-to-end**

```bash
cat > /tmp/mail.html <<'EOF'
<h1>Hello this is a test email</h1>

<img src="https://upload.wikimedia.org/wikipedia/commons/thumb/b/b6/SIPI_Jelly_Beans_4.1.07.tiff/lossy-page1-250px-SIPI_Jelly_Beans_4.1.07.tiff.jpg" />

<p>Did you see the file above ?</p>
EOF

echo "test attachment" > /tmp/test.txt

swaks --to alias@domain.com \
      --from sender@example.com \
      --server localhost --port 25 \
      --header "Subject: SMTP test" \
      --header "Content-Type: text/html" \
      --body @/tmp/mail.html \
      --add-header "Content-Type: text/html; charset=UTF-8" \
      --attach @/tmp/test.txt \
      --attach @"/tmp/test 2.pdf"
```

> Port 2525 is non-privileged; the SMTP container remaps it from port 25 internally.

---

## Tests

The `test` service uses a dedicated Docker stage that includes dev dependencies (Pest, Mockery, etc.). It is **never started by `docker compose up`** — only when explicitly invoked.

```bash
# Build the test image (once, or after changing composer.json or updating tests)
docker compose build test

# Run all tests
docker compose run --rm test
# Run all tests & export results
docker compose run --rm test 2>&1 | tee /tmp/pest-output.txt

# Specific file
docker compose run --rm test tests/Feature/Admin/UsersTest.php

# Filter by test name
docker compose run --rm test --filter "shared user cannot delete"

# Stop at first failure
docker compose run --rm test --stop-on-failure
```

```bash
# Demo seeder (against the running app)
docker compose exec app php artisan db:seed --class=DemoSeeder
```


---

## API

The REST API is at `/api/v1`, authenticated with Sanctum Bearer tokens. Swagger UI is at `/api/docs`.

```bash
# Create a personal token via Tinker or via the User Interface.
docker compose exec app php artisan tinker
>>> $user = \App\Models\User::first();
>>> echo $user->createToken('dev', ['*'])->plainTextToken;

# Use it
curl http://localhost:8000/api/v1/aliases \
  -H "Authorization: Bearer <token>" \
  -H "Accept: application/json"
```

App-level tokens (for machine-to-machine, e.g. SMTP receiver): **Admin → Settings → API Tokens**.

---

## Project structure

```
email-alias/
├── docker-compose.yml          # All services (app, smtp, nginx, db, minio, reverb, worker, scheduler)
├── .env.example                # Docker-level env vars template
├── nginx/                      # Nginx reverse-proxy config
├── smtp-server/
│   └── src/index.js            # SMTP receiver → POST /internal/inbound
└── laravel/
    ├── app/
    │   ├── Http/Controllers/
    │   │   ├── Api/V1/         # REST endpoints (aliases, emails, attachments, domains)
    │   │   ├── Auth/           # SSO (OAuth2, SAML)
    │   │   └── Internal/       # InboundEmailController, DomainsController
    │   ├── Jobs/               # ProcessInboundEmail, CleanupExpiredAliases, DeliverWebhook
    │   ├── Livewire/
    │   │   ├── Mailbox/        # Dashboard, Inbox, ViewEmail
    │   │   ├── Admin/          # Dashboard, Users, AuditLogViewer, Settings
    │   │   └── Settings/       # Profile, Security, Appearance, ApiTokens
    │   ├── Models/             # User, Alias, Domain, AppToken, InboundEmail, Attachment…
    │   └── Services/           # AliasService, AuditLogger, SettingService, HtmlSanitizer
    ├── config/emailalias.php   # Platform config (overridden at runtime by SettingService)
    ├── database/migrations/
    └── routes/
        ├── web.php             # UI routes
        ├── api.php             # REST API v1
        ├── internal.php        # SMTP webhook (internal network only)
        └── settings.php        # User settings routes
```

---

## Troubleshooting

**`Vite manifest not found`** → `docker compose exec app npm run build`

**Email not appearing in real time** → check `BROADCAST_CONNECTION=reverb` in `.env`, verify `reverb` is running, rebuild assets.

**`403` on `/internal/inbound`** → `X-SMTP-Secret` header doesn't match `SMTP_INTERNAL_SECRET`.

**SMTP rejecting all mail** → no domain configured yet. Add one in **Settings → Domains** (the SMTP server starts closed by default).

**Port 2525 in use** → change `SMTP_RECEIVER_PORT` in `.env`.
