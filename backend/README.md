# TaskFlow — Backend API

Laravel 13 REST API for the TaskFlow task management application. It exposes JSON endpoints for authentication, projects, tasks, and comments behind a single multi-auth middleware.

## Stack

- PHP 8.3+ (container: `php:8.5-fpm`)
- Laravel 13
- Laravel Sanctum, tymon/jwt-auth, Laravel Passport
- SQLite (local dev + tests)

## Repository context

This app lives under `backend/` in the TaskFlow monorepo:

- `frontend/` — Nuxt 4 client
- `docker/` and `bin/` — Docker Compose stack and helper scripts (`./bin/run.sh`, `./bin/artisan`)
- `.env` at the repo root is shared and mounted into the backend container

See the root `AGENTS.md` for the full workflow; reference docs live in `.ai/`.

## Development

Everything runs inside Docker. Start the stack from the repo root:

```bash
./bin/run.sh start
```

Artisan commands run through the wrapper (executes inside the backend container):

```bash
./bin/artisan migrate --seed
./bin/artisan passport:keys
./bin/artisan jwt:secret
./bin/artisan tinker
```

`./bin/artisan` is a shortcut for `docker compose exec backend php artisan`.

## API

All routes are defined in `routes/api.php` and served under `/api/*` by nginx.

### Authentication

| Method | Path                | Notes                                    |
|--------|---------------------|------------------------------------------|
| POST   | `/api/v1/register`    | Create user, returns a Sanctum token     |
| POST   | `/api/v1/login`       | Sanctum login                            |
| POST   | `/api/v1/login/jwt`   | JWT login                                |
| GET    | `/api/v1/oauth/client` | Dev helper — creates a password-grant client |
| POST   | `/api/v1/oauth/token` | Passport OAuth2 password grant           |
| POST   | `/api/v1/logout`      | Auth required; revokes the active token  |
| GET    | `/api/v1/user`        | Auth required; current user              |

Authenticated requests must send `X-Auth-Method: sanctum|jwt|passport` plus `Authorization: Bearer <token>`. See `.ai/auth-spec.md` for details.

### Resources (all auth-protected)

- Tasks: `GET/POST /api/v1/tasks`, `GET/PUT/DELETE /api/v1/tasks/{task}`, `POST /api/v1/tasks/{task}/transfer`, `GET /api/v1/tasks/{task}?with=history`
- Projects: `GET/POST /api/v1/projects`, `GET/PUT/DELETE /api/v1/projects/{project}`
- Comments: `GET/POST /api/v1/tasks/{task}/comments`

Authorization is enforced via `app/Policies` (`ProjectPolicy`, `TaskPolicy` — owners only, managers may view/transfer any task). Validation lives in `app/Http/Requests`; responses use `app/Http/Resources`. Task transfers (manager → regular user, managers can never inherit) are recorded in `task_ownership_histories` and exposed via `?with=history`.

## Project layout

```
app/
├── Http/
│   ├── Controllers/Api/    # Auth, Task, Project, Comment controllers
│   ├── Middleware/MultiAuth.php  # selects guard from X-Auth-Method
│   ├── Requests/           # Form Request validation
│   └── Resources/          # JSON resources
├── Models/                 # User, Task, Project, Comment
├── Policies/               # ProjectPolicy, TaskPolicy
└── Providers/
database/migrations/        # SQLite schema
routes/api.php              # all API routes
tests/Feature/              # PHPUnit feature tests
lang/                       # en default, pl validation messages
```

## Testing

Tests run entirely inside the backend container (no host PHP needed) and use in-memory SQLite:

```bash
./bin/artisan test                                   # full suite
./bin/artisan test --filter=MultiAuthTest            # one class
./bin/artisan test --filter=test_logout_invalidates_jwt_token  # one method
./bin/artisan test tests/Feature/MultiAuthTest.php   # one file
```

Code style (Laravel Pint):

```bash
docker compose exec backend vendor/bin/pint --test   # check
docker compose exec backend vendor/bin/pint          # fix
```

## Conventions

- API-only — no Blade views; all routes in `routes/api.php`.
- Thin controllers; business logic in models/policies, validation in Form Requests, JSON in Resources.
- Auth failures return `401 { "success": false, "message": "Unauthenticated." }`; validation failures return `422` with field errors.
- Do not commit generated files or secrets (`.env`, `storage/oauth-*.key`, `vendor`).