# EmailAlias — Self-hosted Temporary Email Platform

Plateforme interne d'adresses email temporaires pour développeurs. 100% self-hosted, sans dépendance à un service externe. Branchez un domaine, branchez votre SSO, c'est parti.

---

## Table des matières

- [Vue d'ensemble](#vue-densemble)
- [Stack technique](#stack-technique)
- [Architecture](#architecture)
- [Structure du projet](#structure-du-projet)
- [Fonctionnalités](#fonctionnalités)
- [Installation](#installation)
- [Configuration (.env)](#configuration-env)
- [Checklist d'implémentation](#checklist-dimplémentation)
- [Notes de développement](#notes-de-développement)

---

## Vue d'ensemble

EmailAlias permet aux développeurs de créer des adresses email temporaires ou permanentes sur un domaine interne, de recevoir des emails en temps réel et de gérer leurs boîtes sans passer par des services tiers (Mailgun, Yopmail, etc.).

**Ce que vous devez fournir :**
- Un serveur avec le port 25 ouvert (réception SMTP)
- Un enregistrement MX sur votre domaine pointant vers ce serveur
- Une application Azure AD (ou autre OIDC) pour le SSO *(optionnel)*

---

## Stack technique

| Couche | Technologie |
|---|---|
| **Backend** | PHP 8.4 · Laravel 13 · Laravel Fortify |
| **Frontend** | Livewire 4 · Flux UI 2 · Tailwind CSS 4 |
| **Temps réel** | Laravel Reverb (WebSocket natif) |
| **Base de données** | PostgreSQL 16 |
| **SMTP receiver** | Node.js 22 · `smtp-server` npm |
| **Reverse proxy** | Caddy (HTTPS automatique) |
| **Containerisation** | Podman / Docker Compose |
| **Tests** | Pest 4 · PHPUnit 12 |

---

## Architecture

```
Internet
    │
    │  MX record → IP serveur : 25
    ▼
┌─────────────────────────────────────────────┐
│              Docker Compose                 │
│                                             │
│  ┌────────────────┐                         │
│  │  smtp-server   │  Écoute port 25         │
│  │  (Node.js)     │  Reçoit TOUS les mails  │
│  └───────┬────────┘                         │
│          │ POST /internal/inbound           │
│          │ (réseau interne, secret partagé) │
│          ▼                                  │
│  ┌────────────────┐    ┌─────────────────┐  │
│  │    Laravel 13  │◄──►│   PostgreSQL    │  │
│  │  Livewire + UI │    └─────────────────┘  │
│  └───────┬────────┘                         │
│          │ broadcast (WebSocket)            │
│          ▼                                  │
│  ┌────────────────┐                         │
│  │ Laravel Reverb │  ws:// inbox live       │
│  └────────────────┘                         │
└─────────────────────────────────────────────┘
          ▲
          │  OIDC / OAuth2
      Azure AD (optionnel)
```

**Flux d'un email entrant :**
1. Un email arrive sur `anything@votre-domaine.com`
2. Le serveur SMTP Node.js l'accepte (catch-all, aucun filtre)
3. Il POST le mail parsé à Laravel via réseau Docker interne (secret partagé)
4. Job `ProcessInboundEmail` cherche l'alias destinataire en base
5. Si trouvé : sauvegarde le mail + broadcast `EmailReceived` sur le canal WebSocket privé
6. L'`AuditLog` enregistre la réception (métadonnées uniquement, pas le contenu)

---

## Structure du projet

```
Email-Alias/
├── docker-compose.yml              # Orchestration principale (prod)
├── docker-compose.dev.yml          # Overrides dev (ports exposés, volumes)
├── .env.example                    # Toutes les variables à configurer
├── README.md                       # Ce fichier
├── README_DEV.md                   # Guide développeur (démarrage, tests, commandes)
├── scripts/
│   └── init.sh                     # Bootstrap en une commande
│
├── smtp-server/                    # Micro-service réception SMTP
│   ├── Dockerfile
│   ├── package.json                # smtp-server, mailparser, node-fetch
│   └── src/
│       └── index.js                # ~120 lignes : reçoit SMTP → POST /internal/inbound
│
└── laravel/                        # Application principale (Laravel 13)
    ├── Dockerfile                  # PHP 8.4-FPM + extensions pgsql, pcntl, bcmath
    ├── Dockerfile.dev
    ├── CLAUDE.md                   # Conventions de code du projet
    ├── app/
    │   ├── Enums/
    │   │   ├── AliasType.php       # Session | Duration | Permanent
    │   │   └── AuditEvent.php      # 13 événements tracés
    │   ├── Events/
    │   │   └── EmailReceived.php   # Broadcast WebSocket sur canal alias.{id}
    │   ├── Http/Controllers/
    │   │   └── Internal/
    │   │       └── InboundEmailController.php  # Webhook SMTP → dispatch job
    │   ├── Jobs/
    │   │   ├── ProcessInboundEmail.php         # Parse + sauvegarde + broadcast
    │   │   └── CleanupExpiredAliases.php       # Purge scheduler quotidien
    │   ├── Livewire/
    │   │   ├── Mailbox/
    │   │   │   ├── Dashboard.php   # Gestion des aliases
    │   │   │   ├── Inbox.php       # Liste des emails
    │   │   │   └── ViewEmail.php   # Détail d'un email
    │   │   └── Admin/
    │   │       ├── Dashboard.php   # Vue globale admin
    │   │       └── AuditLogViewer.php
    │   ├── Models/
    │   │   ├── User.php            # is_admin, 2FA, Passkeys
    │   │   ├── Alias.php           # ULID PK, HasUlids, SoftDeletes
    │   │   ├── InboundEmail.php    # SoftDeletes, markAsRead/Unread
    │   │   └── AuditLog.php        # Immutable (no updated_at), MorphTo
    │   ├── Policies/               # AliasPolicy, InboundEmailPolicy
    │   └── Services/
    │       ├── AliasService.php    # create, delete, extend, suggestAlternative
    │       └── AuditLogger.php     # log() centralisé
    ├── config/
    │   └── emailalias.php          # domain, smtp_secret, limites, flags
    ├── database/
    │   ├── migrations/             # aliases, inbound_emails, audit_logs
    │   ├── factories/              # AliasFactory, InboundEmailFactory
    │   └── seeders/                # DemoSeeder (admin + dev + données)
    ├── resources/views/livewire/
    │   ├── mailbox/                # dashboard, inbox, view-email
    │   └── admin/                  # dashboard, audit-log-viewer
    ├── routes/
    │   ├── web.php                 # Routes UI (mailbox + admin)
    │   └── internal.php            # POST /internal/inbound (réseau interne)
    └── tests/Feature/
        ├── Mailbox/                # CreateAliasTest, InboundEmailTest
        └── Admin/                  # AdminAccessTest
```

---

## Fonctionnalités

### Authentification

| Fonctionnalité | Détail |
|---|---|
| Login / Mot de passe | Pour les environnements de dev sans SSO |
| SSO via OIDC (Azure AD) | Login via compte d'entreprise |
| Passkeys | Authentification sans mot de passe (WebAuthn) |
| 2FA TOTP | Optionnel, activable par utilisateur (Authy, Google Authenticator…) |
| Rôles | `user`, `admin` (champ `is_admin` sur `users`) |

---

### Gestion des adresses email (Aliases)

#### Types de durée

| Type | Comportement | Default |
|---|---|---|
| **Session** | Perdue au refresh de la page | ✅ Oui |
| **Durée** | Expiration automatique, extension manuelle possible | — |
| **Permanente** | Pas d'expiration, liée au compte utilisateur | — |

Délais disponibles pour le type **Durée** : `1h`, `12h`, `24h`, `7 jours`, `30 jours`

- L'expiration est affichée clairement dans l'UI (compte à rebours humain)
- Extension manuelle possible depuis l'interface
- Purge automatique via scheduler (`CleanupExpiredAliases`)

#### Format de l'adresse

| Mode | Exemple | Default |
|---|---|---|
| **Aléatoire** | `01jvxxxxxx@domain.tld` (ULID-based) | ✅ Oui |
| **Custom** | `test-paul-projet@domain.tld` | — |

- Le mode custom vérifie la disponibilité en temps réel
- En cas de conflit, une alternative est proposée automatiquement (`taken-2`)
- Caractères autorisés : lettres, chiffres, tirets, underscores

#### Clé primaire

Les aliases utilisent un **ULID** comme clé primaire (`HasUlids`). Non-devinable, non-séquentiel, tri chronologique natif.

---

### Consultation des emails

#### Vue inbox

- Liste des emails avec expéditeur, sujet, date, badge lu/non lu
- Filtres : Tous / Non lus / Lus
- Switch rapide entre plusieurs adresses
- Mise à jour en temps réel via WebSocket (Reverb) — aucun refresh nécessaire

#### Vue email

| Fonctionnalité | Comportement |
|---|---|
| **Images externes** | Bloquées par défaut (anti-tracking). Bouton *"Afficher les images"* à la demande |
| **Images encodées** | Affichées directement (base64 inline, aucune requête externe) |
| **Liens** | Forcément `target="_blank" rel="noopener noreferrer"` |
| **HTML** | Rendu dans un `<iframe sandbox>` pour isoler le contenu |
| **Texte brut** | Fallback si pas de version HTML |
| **Headers bruts** | Accessibles (mode développeur) |

#### Actions sur les emails

- Marquer comme lu / non lu
- Marquer tout comme lu
- Supprimer un email (soft delete)

---

### Panel Administrateur

Accessible uniquement aux utilisateurs avec `is_admin = true`.

| Fonctionnalité | Détail |
|---|---|
| **Vue globale des adresses** | Liste toutes les adresses de tous les utilisateurs, avec recherche et filtre par user |
| **Vue des emails reçus** | Contrôlée par `ADMIN_CAN_READ_EMAILS=true/false` dans le `.env` |
| **Supprimer une adresse** | L'admin peut supprimer n'importe quelle adresse |
| **Vue de l'audit log** | Consultation complète des logs avec filtres |
| **Statistiques** | Nombre d'adresses actives, emails reçus, adresses expirées |

---

### Audit Log

Toutes les actions sont tracées. **Le contenu des emails n'est jamais stocké dans les logs.**

| Événement tracé |
|---|
| `alias_created` — `alias_deleted` — `alias_expired` — `alias_extended` |
| `email_received` — `email_read` — `email_deleted` |
| `user_login` — `user_logout` |
| `two_factor_enabled` — `two_factor_disabled` |
| `admin_alias_created` — `admin_alias_deleted` — `admin_viewed_email` — `admin_user_updated` |

Chaque entrée contient : qui, quoi, sur quoi (morph), IP, user-agent, timestamp.

---

## Installation

### Prérequis

- Podman Desktop (ou Docker) avec Compose
- Port 25 ouvert sur le serveur de production
- Un domaine avec accès à la configuration DNS

### Démarrage rapide

```bash
# 1. Cloner le projet
git clone <repo> Email-Alias
cd Email-Alias

# 2. Copier et remplir la configuration
cp .env.example .env
# Éditer .env — au minimum : APP_DOMAIN et SMTP_INTERNAL_SECRET

# 3. Démarrer les containers
podman compose up -d

# 4. Migrations + données de démo
podman compose exec app php artisan migrate --seed

# 5. Créer le premier admin
podman compose exec app php artisan admin:create
```

> **Dev local** : voir [README_DEV.md](README_DEV.md) pour le guide complet (tests sans DNS, hot-reload, commandes utiles).

### DNS — Enregistrement MX (production)

```
Type     : MX
Nom      : @  (ou sous-domaine dédié)
Valeur   : votre-serveur.com
Priorité : 10
```

Vérification : `nslookup -type=MX votre-domaine.com`

---

## Configuration (.env)

```env
# ─── Application ──────────────────────────────────────────────
APP_NAME="EmailAlias"
APP_URL=https://emailalias.votre-domaine.com
APP_DOMAIN=votre-domaine.com          # Domaine des adresses générées

# ─── Base de données ──────────────────────────────────────────
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=emailalias
DB_USERNAME=emailalias
DB_PASSWORD=changeme

# ─── SSO Azure AD (optionnel) ─────────────────────────────────
SSO_ENABLED=false
AZURE_CLIENT_ID=
AZURE_CLIENT_SECRET=
AZURE_TENANT_ID=
AZURE_REDIRECT_URI="${APP_URL}/auth/sso/callback"

# ─── Authentification locale ──────────────────────────────────
LOCAL_AUTH_ENABLED=true              # false = SSO obligatoire
REGISTRATION_ENABLED=false           # true = inscription libre

# ─── 2FA ──────────────────────────────────────────────────────
TWO_FACTOR_ENABLED=true
TWO_FACTOR_REQUIRED=false            # true = 2FA forcé pour tous

# ─── Adresses email ───────────────────────────────────────────
ALIAS_MAX_PER_USER=20
ALIAS_DEFAULT_TYPE=session           # session | duration | permanent
ALIAS_ALLOW_PERMANENT=true

# ─── Admin ────────────────────────────────────────────────────
ADMIN_CAN_READ_EMAILS=false

# ─── SMTP Receiver ────────────────────────────────────────────
SMTP_RECEIVER_PORT=25
SMTP_INTERNAL_SECRET=changeme        # Clé partagée smtp-server ↔ Laravel

# ─── WebSocket (Reverb) ───────────────────────────────────────
REVERB_APP_ID=emailalias
REVERB_APP_KEY=changeme
REVERB_APP_SECRET=changeme
REVERB_HOST=0.0.0.0
REVERB_PORT=8080

# ─── Nettoyage automatique ────────────────────────────────────
CLEANUP_EXPIRED_ALIASES=true
CLEANUP_EMAIL_RETENTION_DAYS=30      # 0 = conservation indéfinie
```

---

## Checklist d'implémentation

### Infrastructure

- [x] `docker-compose.yml` — services `app`, `smtp-server`, `reverb`, `worker`, `scheduler`, `db`, `caddy`
- [x] `docker-compose.dev.yml` — ports exposés, volumes hot-reload
- [x] `Dockerfile` Laravel (PHP 8.4-FPM, extensions pgsql / pcntl / bcmath)
- [x] `Dockerfile.dev` Laravel
- [x] `Dockerfile` smtp-server (Node.js 22 Alpine)
- [x] Reverse proxy Caddy (HTTPS automatique via caddy-docker-proxy)
- [ ] Commande `artisan admin:create`

### SMTP Receiver (Node.js)

- [x] Serveur SMTP (`smtp-server` npm) — catch-all, 0 filtre
- [x] STARTTLS supporté (optionnel)
- [x] Parse du mail entrant (`mailparser`)
- [x] POST vers `/internal/inbound` avec secret partagé
- [x] Retry avec backoff exponentiel (3 tentatives)
- [x] Logs structurés JSON
- [ ] Graceful shutdown (SIGTERM géré, à valider en prod)

### Laravel — Base de données

- [x] Migration `aliases` — ULID PK, type, duration, expires_at, soft deletes
- [x] Migration `inbound_emails` — FK ULID vers aliases, soft deletes
- [x] Migration `audit_logs` — morph varchar (compatible ULID + int), immutable
- [x] Factories `AliasFactory`, `InboundEmailFactory`
- [ ] Seeder de démo (`DemoSeeder`) — admin + user + aliases + emails

### Laravel — Modèles & Métier

- [x] `Alias` — `HasUlids`, `SoftDeletes`, scopes `active()`/`expired()`, `extendByDuration()`
- [x] `InboundEmail` — `SoftDeletes`, `markAsRead()`, `markAsUnread()`, scopes
- [x] `AuditLog` — immutable, polymorphe, cast `auditable_id` en string
- [x] `User` — `is_admin`, 2FA, Passkeys (Fortify)
- [x] `AliasType` enum — `Session | Duration | Permanent`
- [x] `AuditEvent` enum — 13 événements
- [x] `AliasService` — `create()`, `delete()`, `extend()`, `suggestAlternative()`, `isAddressAvailable()`
- [x] `AuditLogger` — service centralisé `log()`
- [x] `ProcessInboundEmail` job — parse + sauvegarde + broadcast
- [x] `CleanupExpiredAliases` job — purge scheduler
- [x] `EmailReceived` event — broadcast sur canal privé `alias.{id}`
- [x] `config/emailalias.php` — toutes les options métier
- [ ] Bloquer les mails trop lourds (iamges intégrées par exemple) avec un avertissement à l'utilisateur que le mail a été bloqué (on ne garde que les informations essentielles : sender, objet...)
- [ ] Gérer les pièces jointe (dans une limite de taille configurable)
    - [ ] Taille max disponible soit par mail (5mo de pièce jointe par mail par exemple) ou par utilisateur (un utilisateur ne peut avoir que 500mb de pièces jointe pour tous les mails confondus)
- [ ] Fix le fait que les mailbox "session" ne sont pas détruite à la déconnexion
    - [ ] Supprimer le timer sur les mailbox session puisqu'elles sont delete après la déconnexion et pas après un délai
- [ ] Dans le viewEmail, trouver un moyen d'éviter l'évasion de détection & l'injection de JS (via onerror="" par exemple) en utilisant une vraie librairie PHP pour filtrer

### Laravel — Auth & Sécurité

- [x] Auth locale login/password (Fortify)
- [x] 2FA TOTP (Fortify)
- [x] Passkeys / WebAuthn (Fortify)
- [x] Middleware `admin` (vérifie `is_admin`)
- [x] Middleware `internal` — webhook accessible réseau interne uniquement
- [x] `AliasPolicy`, `InboundEmailPolicy` — contrôle d'accès par ownership
- [x] Rate limiting sur login (natif Fortify)
- [ ] SSO Azure AD via Socialite — *à configurer*
- [ ] Rate limiting sur création d'aliases
- [ ] 2FA obligatoire configurable (`TWO_FACTOR_REQUIRED`)

### Laravel — Contrôleurs & Routes

- [x] `InboundEmailController` — `POST /internal/inbound` → dispatch job → 202
- [x] Route mailbox dashboard `/mailbox`
- [x] Route inbox `/mailbox/{alias}` (résolution par ULID)
- [x] Route email detail `/mailbox/emails/{email}`
- [x] Routes admin `/admin`, `/admin/audit`

### Laravel — Livewire / UI

- [ ] Vrai dashboard pour le user qui reprends ses stats et pas juste redirection vers Dashboard mailbox
- [x] `Mailbox\Dashboard` — liste aliases, création (random/custom/type/durée), delete, extend
- [x] `Mailbox\Inbox` — liste emails, filtre lu/non-lu, temps réel Reverb, marquer lu/suppression
- [x] `Mailbox\ViewEmail` — détail email
- [x] `Admin\Dashboard` — vue globale aliases, stats, recherche, filtre user, delete
- [x] `Admin\AuditLogViewer` — consultation logs
- [x] Pages settings (profil, sécurité, 2FA, apparence) — fournies par Fortify
- [ ] Vues email — `iframe sandbox`, blocage images externes, réécriture liens
- [ ] Countdown d'expiration temps réel dans la sidebar
- [ ] Copie adresse en un clic
- [ ] Temps réel Reverb branché dans l'inbox (listeners JS)
- [ ] Création de mailbox pour un utilisateur (panel admin)
- [ ] Gestion utilisateurs admin (promouvoir admin, désactiver)
    - [ ] Afficher le mail de l'utilisateur dans la liste des users sur le panel admin
    - [ ] Ajouter des tooltips sur les dates "dans 1h" avec la date exacte
    - [ ] Spécifier les timezone pour toutes les dates complètes
    - [ ] Gérer dans les settings utilisateur sa timezone souhaitée pour l'affichage (UTC+1, +2, Europe/Paris, America/New-York...)
    - [ ] Pouvoir chercher un utilisateur par email si on a une très grande liste (+10000 utilisateurs)
        - [ ] Idem dans les audit log

### Tests

- [x] `CreateAliasTest` — création, custom, doublons, limites, suppression, extension
- [x] `InboundEmailTest` — réception, autorisation, marquage lu/non-lu
- [x] `AdminAccessTest` — accès refusé aux non-admins
- [x] Tests Auth Fortify (login, 2FA, reset password, vérification email)
- [ ] Tests `AliasService` (unitaires)
- [ ] Tests `CleanupExpiredAliases` job
- [ ] Tests `ProcessInboundEmail` job

---

## Notes de développement

### Clé primaire ULID sur Alias

`Alias` utilise `HasUlids` (Laravel natif). Le ULID est généré automatiquement à la création. Les FK dans `inbound_emails` sont de type `char(26)`. Les colonnes morph dans `audit_logs` sont des `varchar` pour accepter à la fois les ULIDs (Alias) et les entiers (User, InboundEmail).

### Isolation des emails HTML

Tout contenu HTML est rendu dans une `<iframe>` avec :
```html
<iframe sandbox="allow-same-origin" referrerpolicy="no-referrer" ...>
```
Les attributs `src` d'images externes sont remplacés par un placeholder avant injection. Un bouton *"Afficher les images"* recharge avec les URLs réelles.

### Podman vs Docker

Ce projet fonctionne avec Podman et Podman Compose. Si vous utilisez Docker, remplacez `podman compose` par `docker compose`. Aucune modification des fichiers n'est nécessaire.
