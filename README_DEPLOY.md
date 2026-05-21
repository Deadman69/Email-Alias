# EmailAlias — Déploiement

> Guide technique pour mettre l'application en production. Court. Pas de blabla.

---

## Prérequis

| Élément | Détail |
|---|---|
| Serveur | Linux, port **25 ouvert** (entrant), 512 Mo RAM min |
| Containerisation | Docker ≥ 24 avec Compose |
| Domaine | Accès à la config DNS (enregistrement MX) |
| HTTPS | Caddy gère les certificats automatiquement (Let's Encrypt) |

---

## 1 — Variables d'environnement

Copier et remplir `.env` (infrastructure uniquement — tout le reste se configure via l'app) :

```env
# ── Application ───────────────────────────────────────────────────────────────
APP_KEY=                         # généré par : php artisan key:generate
APP_URL=https://mail.company.com
APP_DOMAIN=mail.company.com      # domaine des adresses générées (doit correspondre au MX)

# ── Base de données ───────────────────────────────────────────────────────────
DB_PASSWORD=<secret_fort>

# ── SMTP receiver ─────────────────────────────────────────────────────────────
SMTP_INTERNAL_SECRET=<secret_fort>   # partagé entre smtp-server et Laravel

# ── WebSocket (Reverb) ────────────────────────────────────────────────────────
REVERB_APP_KEY=<secret_fort>
REVERB_APP_SECRET=<secret_fort>
```

> Les variables business (SSO, limites, 2FA, rétention…) sont gérées **uniquement** via `/admin/settings` après le premier démarrage. Elles sont stockées chiffrées (secrets) ou en clair en base de données.

---

## 2 — DNS

Ajouter un enregistrement MX sur le domaine :

```
Type     MX
Nom      @   (ou alias.company.com)
Valeur   <IP ou hostname du serveur>
TTL      300
Priorité 10
```

Vérification : `nslookup -type=MX mail.company.com`

---

## 3 — Lancement

```bash
# Démarrer tous les services
podman compose up -d

# Migrations + clé (première fois)
podman compose exec app php artisan key:generate
podman compose exec app php artisan migrate --force

# Créer le premier Super Admin
podman compose exec app php artisan admin:create --super-admin
```

---

## 4 — Configuration initiale (UI)

Se connecter sur `https://<APP_URL>/admin/settings` avec le compte Super Admin créé.

Configurer dans l'ordre :
1. **Général** — nom de l'application
2. **Auth** — activer/configurer SSO Azure AD si nécessaire, désactiver le login local si SSO exclusif
3. **Sécurité** — activer 2FA obligatoire si requis
4. **Aliases / Email** — ajuster les limites selon les besoins

---

## 5 — Vérification

```bash
# Statut des containers
podman compose ps

# Logs en temps réel
podman compose logs -f app smtp-server

# Test réception SMTP (remplacer l'adresse par un alias créé via l'UI)
curl -s -X POST http://localhost:9000/internal/inbound \
  -H "Content-Type: application/json" \
  -H "X-SMTP-Secret: <SMTP_INTERNAL_SECRET>" \
  -d '{"from":"test@example.com","from_address":"test@example.com","from_name":"Test","to":["alias@mail.company.com"],"subject":"Test","body_text":"Hello","body_html":null,"headers":{},"size_bytes":5,"attachments":[]}'
```

---

## Mise à jour

```bash
git pull
podman compose build app smtp-server
podman compose up -d
podman compose exec app php artisan migrate --force
```

---

## Services & ports internes

| Service | Rôle | Port interne |
|---|---|---|
| `app` | Laravel PHP-FPM | 9000 |
| `smtp-server` | Réception SMTP | 25 |
| `reverb` | WebSocket | 8080 |
| `worker` | Queue jobs | — |
| `scheduler` | Cron (cleanup) | — |
| `db` | PostgreSQL 16 | 5432 |
| `caddy` | Reverse proxy HTTPS | 80/443 |

Seuls les ports 80, 443 et 25 sont exposés publiquement. Tout le reste reste sur le réseau interne Docker.
