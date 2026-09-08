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
| POST   | `/api/register`       | none      | Creates user, returns Sanctum token (`201`)  |
| POST   | `/api/login`          | none      | Sanctum login → `{ data: { user, token } }`  |
| POST   | `/api/login/jwt`      | none      | JWT login → `{ success, token, token_type }` |
| GET    | `/api/oauth/client`   | none      | Dev helper — creates a password-grant client  |
| POST   | `/api/oauth/token`    | none      | Passport OAuth2 token endpoint (built-in)     |
| POST   | `/api/logout`         | `auth.multi` | Revokes according to the method in use    |
| GET    | `/api/user`           | `auth.multi` | Current user                              |
| REST   | `/api/tasks*`, `/api/projects*`, `/api/tasks/{task}/comments*` | `auth.multi` | Protected resources |

## Login flows (matching `frontend/app/pages/login.vue`)

### Sanctum
1. `POST /api/login` with `{ email, password }`.
2. Response: `{ data: { user, token } }` — token is a plain text token.

### JWT
1. `POST /api/login/jwt` with `{ email, password }`.
2. Response: `{ success, token, token_type: "Bearer" }`.

### Passport (OAuth2 password grant)
1. `GET /api/oauth/client` → `{ client_id, client_secret }` (dev-only; creates a client on the fly).
2. `POST /api/oauth/token` with `{ grant_type: "password", client_id, client_secret, username, password, scope: "" }`.
3. Response: `{ access_token, token_type, expires_in, refresh_token }`.

## Logout semantics (`AuthController::logout`)

- `sanctum`: delete the current access token.
- `jwt`: `auth('jwt')->logout()` (adds token to the blacklist).
- `passport`: revoke `currentAccessToken()`.
- Always returns `204`.

## Client-side handling (`frontend/app/composables/useAuth.ts`)

- Token stored in `localStorage` key `token`; method in key `auth-method`.
- `useApi.ts` adds `Accept: application/json`, `X-Auth-Method`, and `Authorization: Bearer` on every request.
- `plugins/auth-header.ts` restores the stored method across requests.
- Logout always clears local state even if the API call fails.
- Nuxt SSR safety: `localStorage` is only touched inside `import.meta.client` guards.

## Failure matrix

| Condition                                  | Status | Body                                        |
|--------------------------------------------|--------|---------------------------------------------|
| Missing/invalid token                      | 401    | `{ success: false, message: "Unauthenticated." }` |
| Unknown `X-Auth-Method`                    | 401    | same, plus a server `warning` log           |
| Bad credentials (Sanctum `/api/login`)     | 422    | `{ message, errors: { email: [...] } }`     |
| Bad credentials (JWT `/api/login/jwt`)     | 401    | `{ success: false, message: "Invalid credentials." }` |
| Validation failure                         | 422    | field errors (localized — `en`, `pl`)       |
| Forbidden (policy)                         | 403    | policy denial                               |