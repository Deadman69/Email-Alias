# API tokens

`/api/v1/*` routes accept two kinds of bearer tokens:

- **User tokens** — Sanctum personal access tokens, created per-user with abilities from the `TokenAbility` enum (`aliases:read`, `aliases:create`, `aliases:delete`, `emails:read`, `emails:delete`, `attachments:read`, `admin:aliases`, `admin:users`, `admin:logs`). Admin abilities can only be granted to tokens belonging to a user with the Admin/Super Admin role.
- **App tokens** — platform-level machine tokens created by a Super Admin in Settings → API Tokens. Not bound to a user. Abilities are free-form strings checked against exact match or `*` (unrestricted).

A request is authenticated with either kind; whichever is presented in the `Authorization: Bearer <token>` header is used. Requests authenticated with an app token have no associated user — endpoints that scope data to "your own" resources (aliases, emails, attachments) are effectively unusable with an app token, since there is no owning user to scope to. App tokens are intended for the endpoints below.

## Required ability per route

| Method | Route | Required ability (app token) | Required ability (user token) |
|---|---|---|---|
| GET | `/api/v1/domains` | `read:domains` | any authenticated user |
| GET | `/api/v1/aliases` | — (user-scoped, not usable with an app token) | `aliases:read` |
| POST | `/api/v1/aliases` | — | `aliases:create` |
| GET | `/api/v1/aliases/{alias}` | — | `aliases:read` |
| DELETE | `/api/v1/aliases/{alias}` | — | `aliases:delete` |
| GET | `/api/v1/aliases/{alias}/emails` | — | `emails:read` |
| GET | `/api/v1/aliases/{alias}/emails/{email}` | — | `emails:read` |
| DELETE | `/api/v1/aliases/{alias}/emails/{email}` | — | `emails:delete` |
| GET | `/api/v1/emails/{email}/attachments` | — | `attachments:read` |
| GET | `/api/v1/attachments/{attachment}` | — | `attachments:read` |
| GET | `/api/v1/admin/aliases` | `admin:aliases` | `admin:aliases` (+ Admin role) |
| DELETE | `/api/v1/admin/aliases/{alias}` | `admin:aliases` | `admin:aliases` (+ Admin role) |
| GET | `/api/v1/admin/users` | `admin:users` | `admin:users` (+ Admin role) |
| PATCH | `/api/v1/admin/users/{user}` | `admin:users` | `admin:users` (+ Admin role) |
| GET | `/api/v1/admin/audit-logs` | `admin:logs` | `admin:logs` (+ Admin role) |

When creating an app token intended to fully replace a classic admin token, grant it `admin:aliases`, `admin:users`, and `admin:logs` (or `*` for unrestricted access to every ability-gated route).

## Notes

- App tokens carry no role: the ability itself is the only gate on `/api/v1/admin/*` routes.
- `GET /api/v1/domains` accepts any authenticated user token with no specific ability, or an app token with `read:domains`.
- Missing/invalid tokens return `401`. A valid token missing the required ability returns `403` with an error message naming the missing ability.
