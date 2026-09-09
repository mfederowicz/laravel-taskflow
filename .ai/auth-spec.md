# TaskFlow — Auth Spec

## Overview

TaskFlow supports **three authentication strategies** behind one middleware so the whole API behaves the same regardless of how the client authenticates:

1. **Sanctum** (default) — personal access tokens (`X-Auth-Method: sanctum`)
2. **JWT** (tymon/jwt-auth) — signed bearer tokens (`X-Auth-Method: jwt`)
3. **Passport** (OAuth2 password grant) — access/refresh tokens (`X-Auth-Method: passport`)

The active strategy is declared on every authenticated request via the **`X-Auth-Method`** header. `backend/app/Http/Middleware/MultiAuth.php` maps `auth.multi` → the correct guard.

## Request contract

```
X-Auth-Method: sanctum|jwt|passport   // defaults to sanctum when absent
Authorization: Bearer <token>
Accept: application/json
```

Invalid/unknown method or an invalid token → `401 { "success": false, "message": "Unauthenticated." }`.

## Guards (`config/auth.php`)

| Guard     | Package      | Notes                         |
|-----------|--------------|-------------------------------|
| `sanctum` | laravel/sanctum | default for `auth.multi`    |
| `jwt`     | tymon/jwt-auth | stateless                     |
| `passport`| laravel/passport | OAuth2 token introspection  |

`MultiAuth` validates the method whitelist, checks the guard, and calls `auth()->shouldUse($method)` so downstream code (policies, `$request->user()`) resolves via the selected guard.

## Routes

| Method | Path                  | Auth      | Notes                                        |
|--------|-----------------------|-----------|----------------------------------------------|
| POST   | `/api/v1/register`       | none      | Creates user, returns Sanctum token (`201`)  |
| POST   | `/api/v1/login`          | none      | Sanctum login → `{ data: { user, token } }`  |
| POST   | `/api/v1/login/jwt`      | none      | JWT login → `{ data: { user, token } }`  |
| POST   | `/api/v1/sanctum/refresh`| none      | Rotates an expired Sanctum token (within 7-day window) |
| POST   | `/api/v1/jwt/refresh`    | none      | Rotates an expired JWT (within 7-day window) |
| GET    | `/api/v1/oauth/client` | none      | Dev helper — creates a password-grant client  |
| POST   | `/api/v1/oauth/token`  | none      | Passport OAuth2 token endpoint (built-in; password + refresh grant) |
| POST   | `/api/v1/logout`         | `auth.multi` | Revokes according to the method in use    |
| GET    | `/api/v1/user`           | `auth.multi` | Current user                              |
| REST   | `/api/v1/tasks*`, `/api/v1/projects*`, `/api/v1/tasks/{task}/comments*` | `auth.multi` | Protected resources |

## Token lifetimes & refresh policy (uniform)

All access tokens expire after **60 minutes**; all refresh windows are **7 days**
(configured in `.env`):

| Mechanism   | Access TTL | Refresh                                             | Env keys |
|-------------|-----------|-----------------------------------------------------|----------|
| Sanctum     | 60 min    | `POST /api/v1/sanctum/refresh` — rotate expired token (new token issued, old deleted); window based on original token `created_at`, slides on each refresh | `SANCTUM_EXPIRATION`, `SANCTUM_REFRESH_WINDOW` |
| JWT         | 60 min    | `POST /api/v1/jwt/refresh` — `JWTAuth::refresh()` with the expired token; window enforced by tymon `refresh_ttl` | `JWT_TTL`, `JWT_REFRESH_TTL` |
| Passport    | 60 min    | OAuth2 `grant_type=refresh_token` at `/api/v1/oauth/token` | `PASSPORT_TOKEN_EXPIRATION_MINUTES`, `PASSPORT_REFRESH_TOKEN_EXPIRATION_DAYS` |

The refresh endpoints are public (`throttle:10,1`) because they are called with an
already-expired token; the expired token *is* the credential. Sanctum's global
`expiration` check is based on the token `created_at` timestamp, so it also applies
retroactively to tokens issued before the value was set.

## Login flows (matching `frontend/app/pages/login.vue`)

### JWT
1. `POST /api/v1/login/jwt` with `{ email, password }`.
2. Response: `{ data: { user, token } }` — same shape as Sanctum login.
3. On `401`, `useApi` calls `POST /api/v1/jwt/refresh` with the expired token and
   retries the original request once (single-flight).

### Sanctum
1. `POST /api/v1/login` with `{ email, password }`.
2. Response: `{ data: { user, token } }` — plain text token.
3. On `401`, `useApi` calls `POST /api/v1/sanctum/refresh` with the expired token
   and retries the original request once (single-flight).

### Passport (OAuth2 password grant)
1. `GET /api/v1/oauth/client` → `{ client_id, client_secret }` (dev-only; creates a client on the fly).
2. `POST /api/v1/oauth/token` with `{ grant_type: "password", client_id, client_secret, username, password, scope: "" }`.
3. Response: `{ access_token, token_type, expires_in, refresh_token }`.

## Logout semantics (`AuthController::logout`)

- `sanctum`: delete the current access token.
- `jwt`: `auth('jwt')->logout()` (adds token to the blacklist).
- `passport`: revoke `currentAccessToken()`.
- Always returns `204`.

## Client-side handling (`frontend/app/composables/useAuth.ts`)

- Token stored in `localStorage` key `token`; method in key `auth-method`.
- `useApi.ts` adds `Accept: application/json`, `X-Auth-Method`, and `Authorization: Bearer` on every request.
- On `401`, `useApi` attempts a single-flight refresh: Passport via the OAuth2
  refresh grant, JWT/Sanctum via their `/refresh` endpoints; retries once, then
  clears auth and redirects to `/login` on a second `401`.
- `plugins/auth-header.ts` restores the stored method across requests.
- Logout always clears local state even if the API call fails.
- Nuxt SSR safety: `localStorage` is only touched inside `import.meta.client` guards.

## Failure matrix

| Condition                                  | Status | Body                                        |
|--------------------------------------------|--------|---------------------------------------------|
| Missing/invalid token                      | 401    | `{ success: false, message: "Unauthenticated." }` |
| Unknown `X-Auth-Method`                    | 401    | same, plus a server `warning` log           |
| Bad credentials (Sanctum `/api/v1/login`)     | 422    | `{ message, errors: { email: [...] } }`     |
| Bad credentials (JWT `/api/v1/login/jwt`)     | 401    | `{ success: false, message: "Invalid credentials." }` |
| Validation failure                         | 422    | field errors (localized — `en`, `pl`)       |
| Forbidden (policy)                         | 403    | policy denial                               |