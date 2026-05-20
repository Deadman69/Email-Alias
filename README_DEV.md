# EmailAlias — Guide de développement

Ce guide couvre tout ce qu'il faut pour faire tourner EmailAlias en local, **sans domaine réel, sans DNS, sans accès admin**.

---

## Prérequis

| Outil | Version min | Notes |
|---|---|---|
| Podman Desktop | 1.x | [podman-desktop.io](https://podman-desktop.io) |
| Git | — | |
| Un terminal PowerShell | — | Intégré à Windows |

> **Pas de PHP, Node, ni Composer requis en local.** Tout tourne dans des containers.

---

## 1. Premier démarrage

### 1.1 Activer la virtualisation (une seule fois, nécessite un admin)

```powershell
# En PowerShell administrateur :
dism.exe /online /enable-feature /featurename:VirtualMachinePlatform /all /norestart
# Redémarrer le PC
```

### 1.2 Initialiser la machine virtuelle Podman (une seule fois)

```powershell
podman machine init
podman machine start
```

Vérification :
```powershell
podman run --rm hello-world
# Doit afficher "Hello from Docker!"
```

---

## 2. Installation du projet

```powershell
# Cloner le repo
git clone <repo-url> Email-Alias
cd Email-Alias

# Copier la config dev
cp laravel\.env.example laravel\.env
```

Ouvrir `laravel\.env` et vérifier / ajuster ces valeurs minimum :

```env
APP_URL=http://localhost:8000
APP_DOMAIN=dev.local              # Domaine fictif, pas besoin de DNS
SMTP_INTERNAL_SECRET=dev-secret   # Doit correspondre à smtp-server
```

### 2.1 Installer les dépendances Laravel

```powershell
podman run --rm `
  -v "${PWD}\laravel:/app" `
  -w /app `
  composer:2 `
  composer install
```

### 2.2 Installer les dépendances Node (smtp-server)

```powershell
podman run --rm `
  -v "${PWD}\smtp-server:/app" `
  -w /app `
  node:22-alpine `
  npm install
```

---

## 3. Démarrer l'environnement

```powershell
# Depuis la racine du projet
podman compose -f docker-compose.yml -f docker-compose.dev.yml up -d
```

Les services disponibles :

| Service | URL locale | Description |
|---|---|---|
| App Laravel | http://localhost:8000 | Interface principale |
| PostgreSQL | localhost:5432 | BDD (user: emailalias / pw: dans .env) |
| Reverb WebSocket | localhost:8080 | Temps réel |
| SMTP receiver | localhost:2525 | Réception emails (port non-privilégié en dev) |

### Voir les logs en direct

```powershell
podman compose logs -f              # Tous les services
podman compose logs -f app          # Laravel uniquement
podman compose logs -f smtp-server  # SMTP receiver
```

---

## 4. Initialiser la base de données

```powershell
# Migrations
podman compose exec app php artisan migrate

# Migrations + données de démo (admin + développeur + aliases + emails)
podman compose exec app php artisan migrate --seed
```

Les comptes créés par le seeder :

| Email | Mot de passe | Rôle |
|---|---|---|
| admin@example.com | password | Admin |
| paul@example.com | password | Développeur |

### Créer un admin manuellement

```powershell
podman compose exec app php artisan admin:create
```

---

## 5. Compiler les assets frontend

```powershell
# Build une fois
podman compose exec app npm run build

# Ou mode watch (hot-reload)
podman compose exec app npm run dev
```

> Si vous voyez une erreur `Vite manifest not found`, lancez `npm run build`.

---

## 6. Tester la réception d'emails (sans DNS)

Pas besoin de domaine réel. Deux méthodes :

### Méthode A — Webhook direct (recommandé)

Créez d'abord une alias dans l'UI (http://localhost:8000/mailbox), notez l'adresse générée (ex: `xk3f9a2b@dev.local`), puis :

```powershell
curl -X POST http://localhost:8000/internal/inbound `
  -H "Content-Type: application/json" `
  -H "X-SMTP-Secret: dev-secret" `
  -d '{
    "to": ["xk3f9a2b@dev.local"],
    "from_address": "expediteur@example.com",
    "from_name": "Test Sender",
    "subject": "Mon premier email de test",
    "body_html": "<h1>Bonjour</h1><p>Ceci est un <strong>test</strong>.</p><img src=\"https://example.com/tracker.png\">",
    "body_text": "Bonjour. Ceci est un test.",
    "headers": {"message-id": "<test-123@example.com>"},
    "size_bytes": 1024
  }'
```

L'email apparaît en temps réel dans l'inbox sans rafraîchir la page.

### Méthode B — Via le SMTP receiver (test end-to-end)

```powershell
# Avec telnet (natif Windows)
telnet localhost 2525
```

```
EHLO dev.local
MAIL FROM:<expediteur@example.com>
RCPT TO:<xk3f9a2b@dev.local>
DATA
Subject: Test SMTP complet
From: expediteur@example.com
To: xk3f9a2b@dev.local

Corps du message de test.
.
QUIT
```

Ou avec **swaks** si installé (`scoop install swaks`) :

```powershell
swaks --to xk3f9a2b@dev.local `
      --from expediteur@example.com `
      --server localhost --port 2525 `
      --header "Subject: Test swaks" `
      --body "Bonjour depuis swaks"
```

---

## 7. Commandes utiles au quotidien

```powershell
# Artisan (migrations, cache, etc.)
podman compose exec app php artisan migrate
podman compose exec app php artisan migrate:fresh --seed
podman compose exec app php artisan route:list
podman compose exec app php artisan queue:work   # Si pas déjà démarré

# Tests Pest
podman compose exec app php artisan test --compact
podman compose exec app php artisan test --compact --filter=CreateAlias
podman compose exec app php artisan test --compact --filter=InboundEmail

# Pint (formatage du code)
podman compose exec app vendor/bin/pint --dirty

# Tinker (REPL PHP dans le contexte Laravel)
podman compose exec app php artisan tinker

# Vider les caches
podman compose exec app php artisan optimize:clear
```

---

## 8. Arrêter / relancer

```powershell
# Arrêter sans supprimer les données
podman compose down

# Arrêter ET supprimer la BDD (repart de zéro)
podman compose down -v

# Relancer après un reboot (la machine Podman s'arrête)
podman machine start
podman compose -f docker-compose.yml -f docker-compose.dev.yml up -d
```

---

## 9. Variables d'environnement importantes

Toutes dans `laravel/.env`. Les valeurs par défaut du `.env.example` fonctionnent en dev.

| Variable | Valeur dev recommandée | Effet |
|---|---|---|
| `APP_DOMAIN` | `dev.local` | Domaine des adresses générées |
| `SMTP_INTERNAL_SECRET` | `dev-secret` | Clé partagée SMTP ↔ Laravel |
| `ADMIN_CAN_READ_EMAILS` | `false` | Admins peuvent lire le contenu des mails |
| `ALIAS_MAX_PER_USER` | `20` | Limite d'aliases par utilisateur |
| `ALIAS_ALLOW_PERMANENT` | `true` | Autoriser les aliases sans expiration |
| `CLEANUP_EMAIL_RETENTION_DAYS` | `30` | Purge auto des emails supprimés |
| `BROADCAST_CONNECTION` | `reverb` | Temps réel via Reverb |

---

## 10. Structure du projet

```
Email-Alias/
├── docker-compose.yml         # Config production
├── docker-compose.dev.yml     # Overrides dev (ports exposés, volumes)
├── .env.example               # Variables globales (SMTP secret, domain...)
├── README.md                  # Spec fonctionnelle complète
├── README_DEV.md              # Ce fichier
│
├── smtp-server/               # Micro-service réception SMTP (Node.js)
│   └── src/index.js           # ~100 lignes, reçoit SMTP → POST /internal/inbound
│
└── laravel/                   # Application principale (Laravel 13)
    ├── app/
    │   ├── Enums/             # AliasType, AuditEvent
    │   ├── Events/            # EmailReceived (broadcast Reverb)
    │   ├── Http/
    │   │   ├── Controllers/Internal/  # Webhook SMTP (InboundEmailController)
    │   │   └── Middleware/    # EnsureUserIsAdmin, EnsureInternalRequest
    │   ├── Jobs/              # ProcessInboundEmail, CleanupExpiredAliases
    │   ├── Livewire/
    │   │   ├── Mailbox/       # Dashboard, Inbox, ViewEmail
    │   │   └── Admin/         # Dashboard, AuditLogViewer
    │   ├── Models/            # Alias, InboundEmail, AuditLog, User
    │   ├── Policies/          # AliasPolicy, InboundEmailPolicy
    │   └── Services/          # AliasService, AuditLogger
    ├── config/emailalias.php  # Config spécifique EmailAlias
    ├── database/
    │   ├── migrations/        # Schema BDD
    │   └── seeders/           # DemoSeeder (admin + dev + data)
    ├── resources/views/
    │   └── livewire/
    │       ├── mailbox/       # Vues mailbox
    │       └── admin/         # Vues admin
    ├── routes/
    │   ├── web.php            # Routes mailbox + admin
    │   └── internal.php       # Webhook SMTP (non exposé publiquement)
    └── tests/Feature/
        ├── Mailbox/           # CreateAliasTest, InboundEmailTest
        └── Admin/             # AdminAccessTest
```

---

## 11. Dépannage

**`podman: command not found`**
→ Podman Desktop est installé mais le CLI n'est pas dans le PATH. Ouvrir Podman Desktop, aller dans Settings → Resources → Podman Machine et vérifier que la machine est démarrée.

**`Error: no such container: email-alias-app-1`**
→ Les containers ne sont pas démarrés. Lancer `podman compose up -d`.

**`SQLSTATE[HY000]: unable to open database file`**
→ Si vous utilisez SQLite, le fichier `database/database.sqlite` n'existe pas. Lancer `podman compose exec app touch database/database.sqlite` puis `php artisan migrate`.

**L'email ne s'affiche pas en temps réel**
→ Vérifier que `BROADCAST_CONNECTION=reverb` dans `.env` et que le service `reverb` tourne (`podman compose ps`). Vérifier aussi les variables `VITE_REVERB_*` et relancer `npm run build`.

**`403 Unauthorized internal request` sur /internal/inbound**
→ Le header `X-SMTP-Secret` ne correspond pas à `SMTP_INTERNAL_SECRET` dans `.env`.

**Port 2525 déjà utilisé**
→ Changer `SMTP_RECEIVER_PORT` dans `.env` et le port dans `docker-compose.dev.yml`.
