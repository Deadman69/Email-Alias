# EmailAlias — Self-hosted Temporary Email Platform

Plateforme interne d'adresses email temporaires pour développeurs. 100% self-hosted, sans dépendance à un service externe. Branchez un domaine, branchez votre SSO, c'est parti.

---

## Table des matières

- [Vue d'ensemble](#vue-densemble)
- [Architecture](#architecture)
- [Structure du projet](#structure-du-projet)
- [Fonctionnalités](#fonctionnalités)
- [Installation](#installation)
- [Configuration (.env)](#configuration-env)
- [TODO / Checklist d'implémentation](#todo--checklist-dimplémentation)

---

## Vue d'ensemble

EmailAlias permet aux développeurs de créer des adresses email temporaires ou permanentes sur un domaine interne, de recevoir des emails en temps réel et de gérer leurs boîtes sans passer par des services tiers (Mailgun, Yopmail, etc.).

**Ce que vous devez fournir :**
- Un serveur avec le port 25 ouvert (réception SMTP)
- Un enregistrement MX sur votre domaine pointant vers ce serveur
- Une application Azure AD (ou autre OIDC) pour le SSO *(optionnel)*

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
│          ▼                                  │
│  ┌────────────────┐    ┌─────────────────┐  │
│  │    Laravel 12  │◄──►│   PostgreSQL    │  │
│  │  (API + UI)    │    └─────────────────┘  │
│  └───────┬────────┘                         │
│          │ broadcast                        │
│          ▼                                  │
│  ┌────────────────┐                         │
│  │ Laravel Reverb │  WebSocket              │
│  │  (temps réel)  │  (inbox live)           │
│  └────────────────┘                         │
└─────────────────────────────────────────────┘
          ▲
          │  OIDC / OAuth2
      Azure AD (optionnel)
```

**Flux d'un email entrant :**
1. Un email arrive sur `anything@votre-domaine.com`
2. Le serveur SMTP Node.js l'accepte (catch-all, aucun filtre)
3. Il POST le mail brut (RFC 822) à Laravel via réseau Docker interne
4. Laravel vérifie que l'adresse destinataire existe en base
5. Si oui : sauvegarde le mail + broadcast WebSocket vers l'inbox concernée
6. L'audit log enregistre la réception (sans contenu)

---

## Structure du projet

```
EmailAlias/
├── docker-compose.yml          # Orchestration principale
├── docker-compose.dev.yml      # Overrides pour le développement
├── .env.example                # Toutes les variables à configurer
├── README.md                   # Ce fichier
│
├── smtp-server/                # Micro-service réception SMTP
│   ├── Dockerfile
│   ├── package.json
│   └── src/
│       └── index.js            # ~100 lignes, reçoit et forward à Laravel
│
└── laravel/                    # Application principale
    ├── Dockerfile
    ├── app/
    │   ├── Http/Controllers/
    │   │   ├── Auth/           # Login/password + SSO + 2FA
    │   │   ├── AliasController.php
    │   │   ├── MailboxController.php
    │   │   └── Admin/
    │   │       └── AdminController.php
    │   ├── Models/
    │   │   ├── User.php
    │   │   ├── Alias.php       # Adresse email
    │   │   ├── Email.php       # Mail reçu
    │   │   └── AuditLog.php
    │   ├── Jobs/
    │   │   └── ProcessInboundEmail.php
    │   └── Events/
    │       └── EmailReceived.php
    ├── resources/
    │   └── js/                 # Vue 3 + Inertia.js
    └── ...
```

---

## Fonctionnalités

### Authentification

| Fonctionnalité | Détail |
|---|---|
| Login / Mot de passe | Pour les environnements de dev sans SSO |
| SSO via OIDC (Azure AD) | Login via compte d'entreprise |
| 2FA TOTP | Optionnel, activable par utilisateur (app type Authy/Google Authenticator) |
| Rôles | `user`, `admin` |

---

### Gestion des adresses email (Aliases)

#### Types de durée

| Type | Comportement | Default |
|---|---|---|
| **Session** | Perdue au refresh de la page (stockée uniquement en `sessionStorage`) | ✅ Oui |
| **Durée** | Expiration automatique au délai choisi, extension manuelle possible | — |
| **Permanente** | Pas d'expiration, liée au compte utilisateur | — |

Délais disponibles pour le type **Durée** : `1h`, `12h`, `24h`, `7 jours`, `30 jours`

- L'expiration est affichée clairement dans l'UI (ex : *"Expire dans 2h 34min"*, compte à rebours)
- Un email de notification peut être envoyé avant expiration *(optionnel, configurable)*
- Extension manuelle possible depuis l'interface

#### Format de l'adresse

| Mode | Exemple | Default |
|---|---|---|
| **Aléatoire** | `a3f9b2c1@domain.tld` | ✅ Oui |
| **Custom** | `test-paul-projet@domain.tld` | — |

- Le mode custom vérifie la disponibilité en temps réel
- En cas de conflit, une alternative est proposée automatiquement (`test-paul-projet-2@domain.tld`)
- Caractères autorisés : lettres, chiffres, tirets, underscores

#### Actions sur les adresses

- Créer une adresse (authentifié ou anonyme pour les adresses session)
- Supprimer une adresse (et tous ses emails associés)
- Étendre la durée d'une adresse à durée limitée
- Copier l'adresse en un clic

---

### Consultation des emails

#### Vue inbox

- Liste des emails avec expéditeur, sujet, date, badge lu/non lu
- Switch rapide entre plusieurs adresses
- Nombre de mails non lus par adresse
- Mise à jour en temps réel via WebSocket (Laravel Reverb) — pas besoin de rafraîchir

#### Vue email

| Fonctionnalité | Comportement |
|---|---|
| **Images externes** | Bloquées par défaut (anti-tracking pixel). Bouton *"Afficher les images"* pour charger à la demande, comme Outlook |
| **Images encodées** | Affichées directement (base64 inline, pas de requête externe) |
| **Liens** | Forcément `target="_blank" rel="noopener noreferrer"`. Les liens sans attribut cible sont réécrits automatiquement |
| **HTML** | Rendu dans un `<iframe sandbox>` pour isoler le contenu |
| **Texte brut** | Fallback si pas de version HTML |

#### Actions sur les emails

- Marquer comme lu / non lu
- Supprimer un email
- Voir les headers bruts (mode développeur)

---

### Interface utilisateur

- Dashboard avec toutes les adresses actives et leur statut
- Création rapide depuis un bouton flottant
- Compteur d'expiration en temps réel (countdown)
- Indicateur visuel du type d'adresse (session / durée / permanente)
- Copie de l'adresse en un clic
- Responsive (mobile-friendly)

---

### Panel Administrateur

Accessible uniquement aux utilisateurs avec le rôle `admin`.

| Fonctionnalité | Détail |
|---|---|
| **Vue globale des adresses** | Liste toutes les adresses de tous les utilisateurs |
| **Vue des emails reçus** | Contrôlée par `ADMIN_CAN_READ_EMAILS=true/false` dans le `.env` |
| **Créer une adresse pour un utilisateur** | L'admin peut créer des adresses au nom d'un utilisateur |
| **Supprimer une adresse** | L'admin peut supprimer n'importe quelle adresse |
| **Gestion des utilisateurs** | Promouvoir/rétrograder admin, désactiver un compte |
| **Vue de l'audit log** | Consultation complète des logs avec filtres |
| **Statistiques** | Nombre d'adresses actives, emails reçus, adresses expirées |

---

### Audit Log

Toutes les actions sont tracées, **sans stocker le contenu des emails** par défaut.

| Événement | Données loguées |
|---|---|
| Adresse créée | Qui, quelle adresse, quel type, timestamp |
| Adresse supprimée | Qui, quelle adresse, timestamp |
| Adresse expirée | Quelle adresse, timestamp |
| Email reçu | Adresse destinataire, expéditeur, sujet, timestamp (pas le body) |
| Email supprimé | Qui, adresse concernée, sujet, timestamp |
| Connexion utilisateur | Qui, méthode (password/SSO), IP, timestamp |
| Action admin | Qui (admin), quelle action, sur qui/quoi, timestamp |
| 2FA activé/désactivé | Qui, timestamp |

---

## Installation

### Prérequis

- Podman (ou Docker) + Podman Compose (ou Docker Compose)
- Port 25 ouvert sur le serveur
- Un domaine avec accès à la configuration DNS

### Étapes

```bash
# 1. Cloner le projet
git clone <repo> emailalias
cd emailalias

# 2. Copier et remplir la configuration
cp .env.example .env
# Éditer .env avec vos valeurs

# 3. Démarrer les conteneurs
podman compose up -d

# 4. Initialiser la base de données
podman compose exec app php artisan migrate --seed

# 5. Créer le premier admin
podman compose exec app php artisan admin:create
```

### DNS — Enregistrement MX

Ajouter un enregistrement MX sur votre domaine :

```
Type : MX
Nom  : @ (ou votre sous-domaine)
Valeur : votre-serveur.com
Priorité : 10
```

Vérification :
```bash
nslookup -type=MX votre-domaine.com
```

---

## Configuration (.env)

```env
# ─── Application ──────────────────────────────────────────────
APP_NAME="EmailAlias"
APP_URL=https://emailalias.votre-domaine.com
APP_DOMAIN=votre-domaine.com          # Domaine utilisé pour les adresses email

# ─── Base de données ──────────────────────────────────────────
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=emailalias
DB_USERNAME=emailalias
DB_PASSWORD=changeme

# ─── SSO Azure AD (optionnel) ─────────────────────────────────
SSO_ENABLED=true
AZURE_CLIENT_ID=
AZURE_CLIENT_SECRET=
AZURE_TENANT_ID=
AZURE_REDIRECT_URI="${APP_URL}/auth/sso/callback"

# ─── Authentification locale ──────────────────────────────────
LOCAL_AUTH_ENABLED=true              # Désactiver en prod si SSO obligatoire
REGISTRATION_ENABLED=false           # true = inscription libre, false = invitation only

# ─── 2FA ──────────────────────────────────────────────────────
TWO_FACTOR_ENABLED=true              # Activer la fonctionnalité 2FA
TWO_FACTOR_REQUIRED=false            # Forcer le 2FA pour tous les utilisateurs

# ─── Adresses email ───────────────────────────────────────────
ALIAS_MAX_PER_USER=20                # Nombre max d'adresses par utilisateur
ALIAS_DEFAULT_TYPE=session           # session | duration | permanent
ALIAS_ALLOW_PERMANENT=true           # Autoriser les adresses permanentes

# ─── Admin ────────────────────────────────────────────────────
ADMIN_CAN_READ_EMAILS=false          # Autoriser les admins à lire le contenu des mails

# ─── SMTP Receiver ────────────────────────────────────────────
SMTP_RECEIVER_PORT=25
SMTP_INTERNAL_SECRET=changeme        # Clé partagée entre smtp-server et Laravel

# ─── WebSocket (Reverb) ───────────────────────────────────────
REVERB_APP_ID=emailalias
REVERB_APP_KEY=changeme
REVERB_APP_SECRET=changeme
REVERB_HOST=reverb
REVERB_PORT=8080

# ─── Nettoyage automatique ────────────────────────────────────
CLEANUP_EXPIRED_ALIASES=true         # Supprimer auto les adresses expirées
CLEANUP_EMAIL_RETENTION_DAYS=30      # Supprimer les emails après N jours (0 = jamais)
```

---

## TODO / Checklist d'implémentation

### Infrastructure

- [ ] `docker-compose.yml` avec services : `app`, `smtp-server`, `reverb`, `db`, `caddy`
- [ ] `docker-compose.dev.yml` avec hot-reload et outils de dev
- [ ] `Dockerfile` Laravel (PHP 8.4-FPM + extensions)
- [ ] `Dockerfile` smtp-server (Node.js Alpine)
- [ ] Reverse proxy Caddy avec HTTPS automatique
- [ ] Script `artisan admin:create` pour le premier admin

### SMTP Receiver (Node.js)

- [ ] Serveur SMTP avec `smtp-server` (npm)
- [ ] Accepter toutes les connexions (catch-all, 0 filtre)
- [ ] STARTTLS supporté
- [ ] Parser le mail reçu (expéditeur, destinataires, headers)
- [ ] POST vers `http://app/internal/inbound` avec auth par secret partagé
- [ ] Retry en cas d'échec du POST (avec backoff)
- [ ] Logs structurés (JSON)

### Laravel — Backend

- [ ] Migrations : `users`, `aliases`, `emails`, `audit_logs`, `admin_actions`
- [ ] Auth locale (login/password) avec Laravel Fortify
- [ ] Auth SSO Azure AD via Laravel Socialite
- [ ] 2FA TOTP (package `pragmarx/google2fa-laravel`)
- [ ] Middleware rôle `admin`
- [ ] `AliasController` : CRUD adresses, check disponibilité, extension durée
- [ ] `MailboxController` : liste emails, marquer lu/non lu, supprimer
- [ ] `InboundController` : endpoint `/internal/inbound` (IP interne uniquement)
- [ ] `AdminController` : vue globale, actions admin
- [ ] Job `ProcessInboundEmail` : parse, sauvegarde, broadcast
- [ ] Job `CleanupExpiredAliases` : scheduler quotidien
- [ ] Event `EmailReceived` + broadcast Reverb
- [ ] Audit log automatique via Observer/Listener
- [ ] Commande `admin:create`
- [ ] Tests unitaires et feature (Pest)

### Laravel — Frontend (Vue 3 + Inertia.js)

- [ ] Layout principal avec sidebar des adresses actives
- [ ] Page login (formulaire + bouton SSO)
- [ ] Page 2FA (saisie TOTP)
- [ ] Composant création d'adresse (modal, aléatoire/custom, type, durée)
- [ ] Composant inbox (liste emails, badge non-lu, temps réel via Echo)
- [ ] Composant vue email (iframe sandbox, blocage images, réécriture liens)
- [ ] Countdown d'expiration (composant temps réel)
- [ ] Switch rapide entre adresses (sidebar ou tabs)
- [ ] Panel Admin (tableau de bord, liste adresses, audit log, stats)
- [ ] Page profil (activer/désactiver 2FA, changer mot de passe)

### Sécurité

- [ ] Validation que `/internal/inbound` n'est accessible qu'en réseau interne Docker
- [ ] `iframe sandbox` pour l'affichage des emails HTML
- [ ] Blocage images externes par défaut (Content Security Policy)
- [ ] Réécriture des liens (`target="_blank" rel="noopener noreferrer"`)
- [ ] Rate limiting sur la création d'adresses
- [ ] Rate limiting sur le login
- [ ] CSRF sur tous les formulaires (natif Laravel)
- [ ] Logs d'audit pour toutes les actions sensibles

---

## Notes de développement

### Adresses "session"

Les adresses de type session ne sont **pas stockées en base**. Elles existent uniquement :
- En `sessionStorage` côté navigateur (clé + adresse générée)
- Dans la mémoire du processus smtp-server (cache court terme)

À chaque email entrant, le smtp-server interroge Laravel pour savoir si l'adresse existe (base OU cache session actif). Le cache session a un TTL de 2h sans activité.

### Isolation des emails HTML

Tout contenu HTML est rendu dans une `<iframe>` avec les attributs :
```html
<iframe sandbox="allow-same-origin" referrerpolicy="no-referrer" ...>
```
Les images `src` externes sont remplacées par un placeholder avant injection. Un bouton "Afficher les images" déclenche le rechargement avec les vraies URLs.

### Podman vs Docker

Ce projet fonctionne avec Podman et Podman Compose. Si vous utilisez Docker, remplacez `podman compose` par `docker compose` dans toutes les commandes. Aucune modification des fichiers n'est nécessaire.
