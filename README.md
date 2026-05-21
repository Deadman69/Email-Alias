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
- [Variables d'environnement](#variables-denvironnement)
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
├── .env.example                    # Variables infra uniquement (voir ci-dessous)
├── README.md                       # Ce fichier
├── README_DEV.md                   # Guide développeur (démarrage, tests, commandes)
├── README_DEPLOY.md                # Guide de déploiement production (bref, pour techs)
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
    │   │   ├── AttachmentController.php        # Téléchargement authentifié (Gate)
    │   │   ├── Auth/
    │   │   │   └── SsoController.php           # SSO Azure AD (redirect + callback)
    │   │   └── Internal/
    │   │       └── InboundEmailController.php  # Webhook SMTP → dispatch job
    │   ├── Jobs/
    │   │   ├── ProcessInboundEmail.php         # Parse + truncation + pièces jointes + broadcast
    │   │   └── CleanupExpiredAliases.php       # Purge scheduler quotidien
    │   ├── Listeners/
    │   │   └── DeleteSessionAliasesOnLogout.php  # Supprime aliases Session à la déco
    │   ├── Livewire/
    │   │   ├── Mailbox/
    │   │   │   ├── Dashboard.php   # Gestion des aliases
    │   │   │   ├── Inbox.php       # Liste des emails (IDs ULID)
    │   │   │   └── ViewEmail.php   # Détail email + pièces jointes + bannière tronqué
    │   │   └── Admin/
    │   │       ├── Dashboard.php   # Vue globale admin
    │   │       ├── AuditLogViewer.php
    │   │       └── Settings.php    # Panel config plateforme (Super Admin uniquement)
    │   ├── Models/
    │   │   ├── User.php            # role (user/admin/super_admin), 2FA, Passkeys, azure_id
    │   │   ├── Alias.php           # ULID PK, HasUlids, SoftDeletes, shares()
    │   │   ├── AliasShare.php      # ULID PK, accès lecture seule partagé
    │   │   ├── InboundEmail.php    # ULID PK, HasUlids, SoftDeletes, is_truncated
    │   │   ├── Attachment.php      # ULID PK, HasUlids, isImage(), humanSize()
    │   │   ├── Setting.php         # Clé/valeur DB, PK string
    │   │   └── AuditLog.php        # Immutable (no updated_at), MorphTo
    │   ├── Policies/               # AliasPolicy (owner+shared), InboundEmailPolicy
    │   └── Services/
    │       ├── AliasService.php    # create, delete, extend, suggestAlternative, enforceRateLimit
    │       ├── AuditLogger.php     # log() centralisé
    │       ├── HtmlSanitizer.php   # HTMLPurifier : strip on*, injections, images externes
    │       └── SettingService.php  # get/set/fill, cache, chiffrement secrets, Config::set()
    ├── config/
    │   └── emailalias.php          # Valeurs par défaut — surchargées par SettingService au runtime
    ├── database/
    │   ├── migrations/             # aliases, inbound_emails, email_attachments, settings, alias_shares…
    │   ├── factories/              # AliasFactory, InboundEmailFactory
    │   └── seeders/                # DemoSeeder (super_admin + user + 3 aliases + 5 emails + 1 share)
    ├── resources/views/livewire/
    │   ├── mailbox/                # dashboard (avec share modal), inbox, view-email
    │   └── admin/                  # dashboard, audit-log-viewer, settings
    ├── routes/
    │   ├── web.php                 # Routes UI (mailbox + admin + super_admin + SSO + attachments)
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
| Login / Mot de passe | Activable/désactivable via le panel Super Admin |
| SSO via Azure AD | Login via compte d'entreprise (configurable dans l'UI) |
| Passkeys | Authentification sans mot de passe (WebAuthn) |
| 2FA TOTP | Optionnel ou forcé pour tous (configurable) |
| Rôles | `user` · `admin` · `super_admin` (enum, hiérarchique) |

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

### Gestion des boîtes partagées

Le propriétaire d'un alias peut l'**inviter à partager** avec d'autres utilisateurs :
- Invitation par adresse email (doit être un compte existant)
- L'invité accède à la boîte en **lecture seule** : il peut lire et marquer les emails, mais ne peut pas supprimer les emails ni modifier l'alias
- Badge "Shared" visible dans le dashboard
- Révocation possible à tout moment par le propriétaire
- Les alias partagés avec moi apparaissent dans mon dashboard avec le badge "Shared by X"

---

### Panel Administrateur

Deux niveaux de rôle admin :

| Rôle | Accès |
|---|---|
| **Admin** | Vue globale des aliases, utilisateurs, audit log, statistiques |
| **Super Admin** | Tout ce qu'un Admin peut faire + **configuration de la plateforme** |

**Super Admin — `/admin/settings`** :

| Groupe | Paramètres configurables |
|---|---|
| Général | Nom de l'application |
| Auth | SSO (Azure AD client ID/secret/tenant), login local, inscription libre |
| Sécurité | 2FA obligatoire pour tous |
| Aliases | Nombre max par user, types autorisés, type par défaut |
| Email | Taille max email, taille max pièce jointe, rétention, accès admin aux corps |

> Les secrets Azure sont **chiffrés en base de données** (Laravel `encrypt/decrypt`).

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

## Variables d'environnement

Le `.env` ne contient que les variables **d'infrastructure**. Tout le reste (SSO, 2FA, limites, rétention…) est configuré directement dans l'application par un Super Admin via `/admin/settings`.

```env
# ── Application ───────────────────────────────────────────────
APP_KEY=                               # php artisan key:generate
APP_URL=https://emailalias.company.com
APP_DOMAIN=emailalias.company.com      # Domaine MX des adresses générées

# ── Base de données ───────────────────────────────────────────
DB_PASSWORD=<secret>

# ── SMTP Receiver ─────────────────────────────────────────────
SMTP_INTERNAL_SECRET=<secret>          # Secret partagé smtp-server ↔ Laravel

# ── WebSocket (Reverb) ────────────────────────────────────────
REVERB_APP_KEY=<secret>
REVERB_APP_SECRET=<secret>
```

> **Paramètres métier** : SSO Azure (client ID/secret/tenant), auth locale, 2FA, limites, taille des mails… → tout se configure via `/admin/settings`. Les secrets sensibles sont **chiffrés en base de données**.

> **Déploiement** : voir [README_DEPLOY.md](README_DEPLOY.md) pour le guide complet.

---

## Checklist d'implémentation

### Infrastructure

- [x] `docker-compose.yml` — services `app`, `smtp-server`, `reverb`, `worker`, `scheduler`, `db`, `caddy`
- [x] `docker-compose.dev.yml` — ports exposés, volumes hot-reload
- [x] `Dockerfile` Laravel (PHP 8.4-FPM, extensions pgsql / pcntl / bcmath)
- [x] `Dockerfile.dev` Laravel
- [x] `Dockerfile` smtp-server (Node.js 22 Alpine)
- [x] Reverse proxy Caddy (HTTPS automatique via caddy-docker-proxy)
- [x] Commande `artisan admin:create`

### SMTP Receiver (Node.js)

- [x] Serveur SMTP (`smtp-server` npm) — catch-all, 0 filtre
- [x] STARTTLS supporté (optionnel)
- [x] Parse du mail entrant (`mailparser`)
- [x] POST vers `/internal/inbound` avec secret partagé
- [x] Retry avec backoff exponentiel (3 tentatives)
- [x] Logs structurés JSON
- [x] Graceful shutdown (SIGTERM → fermeture propre, connexions en cours terminées)

### Laravel — Base de données

- [x] Migration `aliases` — ULID PK, type, duration, expires_at, soft deletes
- [x] Migration `inbound_emails` — ULID PK, FK ULID vers aliases, `is_truncated`, soft deletes
- [x] Migration `email_attachments` — ULID PK, FK ULID vers inbound_emails, path, checksum
- [x] Migration `add_azure_id_to_users` — colonne `azure_id` nullable unique
- [x] Migration `audit_logs` — morph varchar (compatible ULID + int), immutable
- [x] Factories `AliasFactory`, `InboundEmailFactory`
- [x] Seeder de démo (`DemoSeeder`) — admin + dev + 3 aliases + 5 emails HTML réalistes

### Laravel — Modèles & Métier

- [x] `Alias` — `HasUlids`, `SoftDeletes`, scopes `active()`/`expired()`, `extendByDuration()`
- [x] `InboundEmail` — `HasUlids`, `SoftDeletes`, `markAsRead()`, `markAsUnread()`, `is_truncated`, `humanSize()`, relation `attachments()`
- [x] `Attachment` — `HasUlids`, `isImage()`, `humanSize()`, suppression fichier sur delete via hook `booted()`
- [x] `AuditLog` — immutable, polymorphe, cast `auditable_id` en string
- [x] `User` — `role` enum (user/admin/super_admin), accessor `is_admin` rétro-compat, 2FA, Passkeys, `azure_id`
- [x] `AliasShare` — ULID PK, accès lecture seule partagé, `user()`, `sharedBy()`
- [x] `Setting` — clé/valeur DB, PK string, géré par `SettingService`
- [x] `AliasType` enum — `Session | Duration | Permanent`
- [x] `Role` enum — `User | Admin | SuperAdmin`, hiérarchie `isAtLeast()`
- [x] `AuditEvent` enum — 15 événements (+ `alias.shared`, `alias.unshared`)
- [x] `AliasService` — `create()`, `delete()`, `extend()`, `suggestAlternative()`, `isAddressAvailable()`, `enforceRateLimit()`
- [x] `AuditLogger` — service centralisé `log()`
- [x] `HtmlSanitizer` — purification HTML via HTMLPurifier (`ezyang/htmlpurifier`), strip `on*`, iframe, script, CSS injection ; `blockExternalImages`, fallback regex si package absent
- [x] `ProcessInboundEmail` job — parse + truncation si > taille max + pièces jointes + broadcast
- [x] `CleanupExpiredAliases` job — purge scheduler
- [x] `EmailReceived` event — broadcast sur canal privé `alias.{id}`
- [x] `DeleteSessionAliasesOnLogout` listener — supprime les aliases `Session` à la déconnexion
- [x] `SettingService` — get/set/fill, cache `Cache::rememberForever`, chiffrement secrets, `CONFIG_MAP`
- [x] `BootstrapSettings` middleware — lit les settings DB → `Config::set()` à chaque requête (try/catch si table absente)
- [x] `config/emailalias.php` — valeurs par défaut, surchargées au runtime par `SettingService`
- [x] Emails trop lourds tronqués : body non stocké, `is_truncated=true`, bannière d'avertissement UI ; seuil configurable via `ALIAS_MAX_EMAIL_SIZE_BYTES`
- [x] Pièces jointes — taille par fichier configurable (`ALIAS_MAX_ATTACHMENT_SIZE_BYTES`), stockage disque privé, téléchargement authentifié
    - [ ] Quota total par utilisateur (ex. 500 Mo pour tous les mails confondus)
- [x] Fix aliases "session" non détruits à la déconnexion — `DeleteSessionAliasesOnLogout` listener
    - [ ] Supprimer le timer affiché sur les aliases session (ils sont supprimés à la déconnexion, plus à expiration)
- [x] HTML sanitization stricte via HTMLPurifier — strip `on*`, `<script>`, injections CSS, `onerror=""`, etc.
- [x] ULID pour `InboundEmail` et `Attachment` (en plus de `Alias`)

### Laravel — Auth & Sécurité

- [x] Auth locale login/password (Fortify)
- [x] 2FA TOTP (Fortify)
- [x] Passkeys / WebAuthn (Fortify)
- [x] Middleware `admin` (Admin + SuperAdmin)
- [x] Middleware `super_admin` (SuperAdmin uniquement — settings plateforme)
- [x] Middleware `internal` — webhook accessible réseau interne uniquement
- [x] `AliasPolicy` — owner ET utilisateurs avec accès partagé (lecture) ; ability `share` owner-only
- [x] `InboundEmailPolicy` — owner ET utilisateurs partagés pour `view` ; owner-only pour `delete` ; fix null alias → 403 au lieu de 500
- [x] IDOR fix : `Inbox::markAllRead()` — `authorize('view', $alias)` explicite ajouté
- [x] Rate limiting sur login (natif Fortify)
- [x] SSO Azure AD via Socialite (`laravel/socialite` + `socialiteproviders/microsoft-azure`) — configurable via UI
- [x] Rate limiting sur création d'aliases (10 créations/min/utilisateur via `RateLimiter`)
- [x] 2FA obligatoire configurable (via panel Super Admin)

### Laravel — Contrôleurs & Routes

- [x] `InboundEmailController` — `POST /internal/inbound` → dispatch job → 202
- [x] `AttachmentController` — `GET /attachments/{attachment}` → téléchargement authentifié + Gate
- [x] `SsoController` — `GET /auth/sso/redirect` + `GET /auth/sso/callback` (Azure AD via Socialite)
- [x] Route mailbox dashboard `/mailbox`
- [x] Route inbox `/mailbox/{alias}` (résolution par ULID)
- [x] Route email detail `/mailbox/emails/{email}`
- [x] Routes admin `/admin`, `/admin/audit`
- [x] Route super_admin `/admin/settings`

### Laravel — Livewire / UI

- [ ] Vrai dashboard pour le user qui reprends ses stats et pas juste redirection vers Dashboard mailbox
- [x] `Mailbox\Dashboard` — liste aliases (propres + partagés), création, delete/extend (owner), share modal
- [x] `Mailbox\Inbox` — liste emails, filtre lu/non-lu, temps réel Reverb, marquer lu/suppression (IDs ULID)
- [x] `Mailbox\ViewEmail` — détail email, pièces jointes, bannière email tronqué, images externes à la demande
- [x] `Admin\Dashboard` — vue globale aliases, stats, recherche, filtre user, delete
- [x] `Admin\AuditLogViewer` — consultation logs
- [x] `Admin\Settings` — panel Super Admin : 5 onglets (Général, Auth, Sécurité, Aliases, Email), sauvegarde en DB avec cache-bust
- [x] Pages settings (profil, sécurité, 2FA, apparence) — fournies par Fortify
- [x] Vues email — `<iframe sandbox>`, blocage images externes (anti-tracking), liens `noopener`, HTML purifié
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
    - [ ] Pouvoir voir tous les utilisateurs et leurs rangs (admin/user) dans une page/tableau dédiée

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

### Rôles utilisateur

Trois rôles hiérarchiques via l'enum `App\Enums\Role` :

| Rôle | DB value | Accès |
|---|---|---|
| `User` | `user` | Mailbox personnel + boîtes partagées |
| `Admin` | `admin` | + panel admin (aliases, logs, stats) |
| `SuperAdmin` | `super_admin` | + configuration plateforme (`/admin/settings`) |

L'accessor `$user->is_admin` (rétro-compat) retourne `true` pour Admin et SuperAdmin.  
Promotion via `php artisan admin:create [--super-admin]`.

### Settings plateforme (SettingService)

`App\Services\SettingService` stocke les paramètres en DB (`settings` table, PK string).  
Le middleware `BootstrapSettings` (prepend web) lit les settings → `Config::set()` à chaque requête, avec try/catch pour survivre aux fresh installs.

**Secrets chiffrés** : `azure_client_secret` est systématiquement chiffré/déchiffré via `encrypt()`/`decrypt()`.

Ajouter une clé chiffrée : étendre la constante `SettingService::ENCRYPTED_KEYS`.

### Boîtes partagées (AliasShare)

- Table `alias_shares` : ULID PK, `alias_id` + `user_id` + `shared_by_id`
- `AliasPolicy::view()` : owner **OU** entrée en `alias_shares` — pas de N+1 (contrainte unique par paire)
- Les utilisateurs partagés ne peuvent pas supprimer emails ni modifier l'alias (`delete` et `update` = owner uniquement)
- `AliasPolicy::share()` : owner uniquement

### ULID sur tous les modèles principaux

`Alias`, `InboundEmail`, `Attachment` et `AliasShare` utilisent `HasUlids` (Laravel natif). Les ULIDs sont non-devinables, non-séquentiels, triables chronologiquement.

Les FK sont de type `foreignUlid()`. Les colonnes morph dans `audit_logs` sont `varchar(26)` pour accepter à la fois les ULIDs et les entiers (User).

### HTML sanitization (HtmlSanitizer)

`App\Services\HtmlSanitizer` utilise `ezyang/htmlpurifier` pour filtrer strictement le HTML entrant :
- Allowlist de tags et attributs (pas de `<script>`, `<iframe>`, `<form>`, `<object>`)
- Strip de **tous** les attributs `on*` (`onerror`, `onclick`, etc.)
- CSS inline : propriétés autorisées explicitement
- `data:` URI autorisés pour les images base64
- Liens forcés `target="_blank" rel="noopener noreferrer"`
- En l'absence du package, fallback regex minimal

L'installer si ce n'est pas déjà fait :
```bash
podman compose exec app composer require ezyang/htmlpurifier
```

### Isolation des emails HTML

Tout contenu HTML est rendu dans une `<iframe>` avec attribut `sandbox` et `referrerpolicy="no-referrer"`. Les images externes sont remplacées par un placeholder (anti-tracking). Un bouton *"Afficher les images"* recharge avec les URLs réelles.

### Pièces jointes

Les fichiers sont stockés sur le disque `local` (privé, hors `public/`) dans `storage/app/attachments/{email_id}/{filename}`. Le nom de fichier est sanitisé via `Str::slug()`. Le téléchargement passe par `AttachmentController` qui vérifie l'ownership via Gate. À la suppression d'un `Attachment`, le fichier est supprimé du disque via le hook `booted()` du modèle.

### Packages ajoutés

```bash
# Déjà dans composer.json, à installer si besoin :
podman compose exec app composer install
```

| Package | Usage |
|---|---|
| `ezyang/htmlpurifier ^4.17` | Sanitization HTML emails |
| `laravel/socialite ^5.17` | SSO OAuth2 |
| `socialiteproviders/microsoft-azure ^5.3` | Driver Azure AD |

### Migration migrate:fresh requise

Si vous avez déjà une DB avec `inbound_emails` en clé entière (avant l'introduction des ULIDs), un `migrate:fresh` est nécessaire. Incompatibilité de type de PK (`bigint` → `char(26)`).

### Podman vs Docker

Ce projet fonctionne avec Podman et Podman Compose. Si vous utilisez Docker, remplacez `podman compose` par `docker compose`. Aucune modification des fichiers n'est nécessaire.
