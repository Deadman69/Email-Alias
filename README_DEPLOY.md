# EmailAlias — Production Deployment

Requirements: Linux host · Docker ≥ 24 with Compose · port 25 open inbound · a domain with DNS access.

> HTTPS is not handled by the stack itself. Terminate TLS upstream (Caddy, Traefik, Nginx + Certbot, or a cloud LB).

---

## Mandatory

### 1 — Environment

```bash
cp .env.example .env
```

Minimum values to change in `.env`:

```env
APP_ENV=production
APP_KEY=base64:<app_key_to_generate>
APP_DEBUG=false
APP_URL=https://mail.company.com

DB_PASSWORD=<strong_secret>

SMTP_INTERNAL_SECRET=<strong_secret>    # shared with smtp-server container

REVERB_APP_KEY=<random>
REVERB_APP_SECRET=<strong_secret>
```

To generate an application key (`APP_KEY`):
```bash
openssl rand -base64 32
```

Then set:

```env
APP_KEY=base64:GENERATED_VALUE
```

> All business settings (SSO, 2FA, quotas, retention…) are configured in the UI after first launch — not via env vars.

### 2 — DNS

Add an MX record pointing to your server's IP:

```
Type    Priority  Host              Value
MX      10        mail.company.com  <server-ip>
```

Port 25 must be reachable inbound. Some cloud providers block it by default — open it in your firewall/security group.

### 3 — Launch

```bash
docker compose up -d
docker compose exec app php artisan admin:create --super-admin
```

### 4 — First configuration

Log in as Super Admin → **Admin → Settings → Domains** and add your domain (e.g. `mail.company.com`). The SMTP receiver will pick it up within 5 minutes.

---

## Multi-node / scaling

By default all services run on a single host. For multi-node setups:

**Object storage (MinIO)** — replace the bundled MinIO with an external S3-compatible bucket (AWS S3, Scaleway, Hetzner Object Storage…):

```env
# laravel/.env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=eu-west-1
AWS_BUCKET=emailalias
AWS_ENDPOINT=https://s3.eu-west-1.amazonaws.com   # omit for AWS, set for S3-compatible
AWS_USE_PATH_STYLE_ENDPOINT=true                   # required for MinIO / Hetzner
```

Then remove the `minio` service from `docker-compose.yml`.

**Queue workers** — scale the `worker` service horizontally:
```bash
docker compose up -d --scale worker=4
```
Workers are stateless; all state is in PostgreSQL.

**WebSocket (Reverb)** — for >500 concurrent users, run multiple Reverb replicas behind a sticky-session load balancer. Set `REVERB_MAX_CONNECTIONS` to tune memory usage (~2 MB/connection).

**Database** — the `db` service is a single PostgreSQL container. For HA, replace it with an external managed PostgreSQL (RDS, Supabase, Neon…) and remove the `db` service.

---

## Updates

```bash
git pull
docker compose build app smtp-server
docker compose up -d
docker compose exec app php artisan migrate --force
```