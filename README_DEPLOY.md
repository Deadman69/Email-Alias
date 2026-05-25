# EmailAlias — Deployment

Production deployment guide.

## Requirements

| Item | Detail |
|---|---|
| Server | Linux, **port 25 open** (inbound), 512 MB RAM min |
| Runtime | Docker ≥ 24 with Compose |
| Domain | DNS access for an MX record |
| HTTPS | Handled automatically by Caddy (Let's Encrypt) |

---

## 1 — Environment variables

### Docker-compose variables
Copy `.env.example` to `.env` and fill in the required values, all the others values are not required to be changed by default:

```env
APP_NAME="EmailAlias"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://emailalias.your_domain.com

DB_PASSWORD=changeme_db_password 

REVERB_APP_KEY=changeme_reverb_key
REVERB_APP_SECRET=changeme_reverb_secret

MINIO_ROOT_PASSWORD=changeme_minio_password
```

> All business settings (SSO, 2FA, limits, retention…) are managed exclusively via `/admin/settings` after first launch. Sensitive secrets (e.g. Azure client secret) are stored encrypted in the database.

### Laravel variables

Copy `./laravel/.env.example` to `./laravel/.env` and fill the required values, remember that they are overwritten by the docker-compose variables.
Therefore you only need to generate the Laravel key using the command : `docker compose exec app php artisan key:generate`

---

## 2 — DNS

Add an MX record on your domain:

```
Type      MX
Name      @   (or mail.company.com)
Value     <server IP or hostname>
TTL       300
Priority  10
```

Verify: `nslookup -type=MX mail.company.com`

---

## 3 — Launch

```bash
# Start all services
docker compose up -d

# Create the first Super Admin
docker compose exec app php artisan admin:create --super-admin
```

---

## 4 — Initial configuration (UI)

Log in at `https://<APP_URL>/admin/settings` with the Super Admin account and configure the application.

---

## Updates

```bash
git pull
docker compose build app smtp-server
docker compose up -d
docker compose exec app php artisan migrate --force
```

---

## Internal services & ports

| Service | Role | Internal port |
|---|---|---|
| `app` | Laravel (PHP-FPM) | 9000 |
| `smtp-server` | SMTP ingestion | 25 |
| `reverb` | WebSocket | 8080 |
| `worker` | Queue jobs | — |
| `scheduler` | Cron (cleanup) | — |
| `db` | PostgreSQL 16 | 5432 |
| `nginx` | HTTP reverse proxy | 80 |

Only ports 80 and 25 are exposed publicly. Everything else stays on the internal Docker network.
