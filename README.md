# EmailAlias

Self-hosted temporary email alias platform for developers. No external services. No subscriptions. Your infrastructure, your data.

Point a domain at it, plug in your SSO, ship it.

---

## Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.4 · Laravel 13 · Fortify |
| Frontend | Livewire 4 · Flux UI 2 · Tailwind CSS 4 |
| Real-time | Laravel Reverb (WebSocket) |
| Database | PostgreSQL 16 |
| SMTP receiver | Node.js 22 |
| Reverse proxy | Caddy (automatic HTTPS) |
| Runtime | Docker Compose |

---

## How it works

```
Internet
    │
    │  MX record → server IP : 25
    ▼
┌──────────────────────────────────────┐
│             Docker Compose           │
│                                      │
│  ┌───────────────┐                   │
│  │  smtp-server  │  port 25          │
│  │  (Node.js)    │  catch-all        │
│  └──────┬────────┘                   │
│         │ POST /internal/inbound     │
│         │ (internal network, secret) │
│         ▼                            │
│  ┌───────────────┐  ┌─────────────┐  │
│  │    Laravel    │◄►│  PostgreSQL │  │
│  │  (Livewire)   │  └─────────────┘  │
│  └──────┬────────┘                   │
│         │ broadcast                  │
│         ▼                            │
│  ┌───────────────┐                   │
│  │    Reverb     │  live inbox       │
│  └───────────────┘                   │
└──────────────────────────────────────┘
         ▲
         │  OAuth2 (optional)
     Azure AD SSO
```

Incoming email flow:
1. Email arrives at `anything@your-domain.com`
2. Node.js SMTP server accepts it (catch-all)
3. Parsed email is POSTed to Laravel over the internal Docker network (shared secret)
4. `ProcessInboundEmail` job finds the target alias in the database
5. If found: saves the email and broadcasts `EmailReceived` over the WebSocket channel

---

## Features

**Aliases**
- Three types: Session (deleted on logout), Duration (1h → 30 days), Permanent
- Random or custom address with real-time availability check
- Share a mailbox with other users (read-only)

**Inbox**
- Real-time updates via WebSocket (Reverb)
- HTML rendered in a sandboxed `<iframe>`, external images blocked by default
- Attachments with authenticated download

**Authentication**
- Email + password, Azure AD SSO (Socialite), Passkeys, TOTP 2FA
- Three roles: `user`, `admin`, `super_admin`

**Admin**
- Global dashboard, immutable audit log
- Super Admin panel: full platform configuration via UI — no server restart required
- Secrets stored encrypted in the database

---

## Quick start

```bash
git clone <repo> email-alias && cd email-alias
cp .env.example .env        # fill in APP_DOMAIN, DB_PASSWORD and secrets

docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan admin:create --super-admin
```

Open `https://<APP_URL>/admin/settings` to complete the initial configuration (SSO, 2FA, limits…).

---

## Documentation

| File | Content |
|---|---|
| [README_DEV.md](README_DEV.md) | Local setup, project structure, developer workflow |
| [README_DEPLOY.md](README_DEPLOY.md) | Production deployment: DNS, env vars, launch |
| [README_SECURITY.md](README_SECURITY.md) | Security model: roles, IDOR, encryption, audit |

---

## Requirements

- Linux server, **port 25 open** (inbound)
- Docker ≥ 24 with Compose
- A domain with DNS access (MX record)
