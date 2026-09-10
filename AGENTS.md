# TaskFlow

TaskFlow is a full-stack task management application split across two independent apps in one repository:

- `backend/` — Laravel 13 REST API (PHP 8.3+)
- `frontend/` — Nuxt 4 / Vue 3 client

Infrastructure lives at the repo root:

- `docker/` — Docker Compose stack, images, nginx config
- `bin/` — helper scripts (`run.sh`, `artisan`)
- `.env` — shared environment (injected into the backend container via `env_file`)

## Repository layout

```
.
├── backend/                          # Laravel API-only application
│   ├── app/
│   │   ├── Http/Controllers/Api/     # AuthController, TaskController, ProjectController, CommentController, UserController
│   │   ├── Http/Middleware/MultiAuth.php  # multi-auth guard (sanctum / jwt / passport)
│   │   ├── Http/Middleware/EnsureUserIsActive.php  # rejects locked accounts (403) on protected routes
│   │   ├── Http/Requests/            # Form Request validation
│   │   ├── Http/Resources/           # JSON resources
│   │   ├── Enums/                    # UserRole, UserStatus (backed string enums)
│   │   ├── Models/                   # User, Task, Project, Comment
│   │   └── Policies/                 # ProjectPolicy, TaskPolicy, CommentPolicy, UserPolicy
│   ├── config/                       # incl. jwt.php, passport.php, sanctum.php
│   ├── database/migrations/          # SQLite schema
│   ├── routes/api.php                # all API routes
│   ├── tests/Feature/                # PHPUnit API tests
│   └── lang/                         # en default, pl validation messages
├── frontend/                         # Nuxt 4 / Vue 3 client
│   └── app/
│       ├── pages/                    # index, login, tasks, projects
│       ├── components/               # AppNav.vue
│       ├── composables/              # useAuth.ts, useApi.ts
│       ├── plugins/                  # auth-header.ts
│       ├── types/                    # task.ts, project.ts
│       └── assets/css/               # main.css
├── docker/
│   ├── docker-compose.yml
│   ├── image-backend/Dockerfile      # php:8.5-fpm
│   ├── image-frontend/Dockerfile     # node:24-alpine
│   └── configs/nginx/default.conf
└── bin/
    ├── run.sh      # docker compose wrapper (start/build/stop/restart/logs/status)
    └── artisan     # docker compose exec backend php artisan <args>
```

## Development environment

Everything runs through Docker Compose. Start the stack from the repo root:

```sh
./bin/run.sh start        # start services
./bin/run.sh build        # start and rebuild images
```

Services:

- `nginx` — entry point on `${APP_PORT:-8080}`; proxies `/api/*` to the backend and everything else to the frontend
- `backend` — PHP-FPM (Laravel), working dir `/var/www/html`; env vars are injected from the repo root `.env` via `env_file`
- `frontend` — Nuxt dev server on port 3000, working dir `/app`; `node_modules` is a named volume

Run artisan commands (executes inside the backend container):

```sh
./bin/artisan migrate --seed
./bin/artisan passport:keys
./bin/artisan jwt:secret
```

## Backend conventions

- API-only application: routes live exclusively in `routes/api.php`; no Blade views.
- Authentication: the `auth.multi` middleware (`MultiAuth`) resolves the guard from the `X-Auth-Method` request header — one of `sanctum` (default), `jwt`, or `passport`.
- Authenticated requests must send `X-Auth-Method: sanctum|jwt|passport` plus `Authorization: Bearer <token>`.
- Controllers are thin; keep business logic in models/policies, use Form Requests for validation, and Resources for JSON responses.
- Auth failures return `401 { "success": false, "message": "Unauthenticated." }`; validation failures return `422` with field errors.
- Token policy (uniform): access tokens live 60 minutes; refresh window is 7 days — JWT/Sanctum rotate via `POST /api/v1/jwt/refresh` / `POST /api/v1/sanctum/refresh` (public, throttled), Passport via the OAuth2 refresh grant. Lifetimes are env-driven (`SANCTUM_EXPIRATION`, `JWT_TTL`, `JWT_REFRESH_TTL`, `PASSPORT_TOKEN_EXPIRATION_MINUTES`, etc.).
- API resources: Tasks, Projects, and Comments (nested under tasks).
- Database is SQLite (`database/database.sqlite`); schema lives in `database/migrations`.
- Validation messages are localized — Polish strings in `lang/pl/validation.php`.

## Frontend conventions

- Nuxt 4 auto-imports components, composables, and pages; do not manually import `useApi`/`useAuth`.
- `useApi` (`app/composables/useApi.ts`) wraps `$fetch` and adds `Accept: application/json`, `X-Auth-Method`, and `Authorization: Bearer` automatically.
- `useAuth` stores the token and chosen auth method in `localStorage`; `app/plugins/auth-header.ts` restores the method across requests.
- All API calls go to `/api/*`, proxied by nginx to the backend.

## Testing and quality

Everything below runs inside the Docker containers — PHP, Composer, and Node are not expected on the host. Start the stack once (`./bin/run.sh start`) and use the helpers.

Backend tests (PHPUnit feature tests in `backend/tests/Feature`). They use in-memory SQLite (`DB_DATABASE=:memory:`), so no DB migration/setup is required:

```sh
./bin/artisan test                            # full suite (php artisan test in the container)
./bin/artisan test --filter=MultiAuthTest                # one test class
./bin/artisan test --filter=test_logout_invalidates_jwt_token  # one test method
./bin/artisan test tests/Feature/MultiAuthTest.php       # one file
```

Direct container equivalents (same thing, without the `bin/artisan` wrapper):

```sh
docker compose exec backend php artisan test
docker compose exec backend php artisan test --filter=MultiAuthTest
docker compose exec backend vendor/bin/phpunit tests/Feature/MultiAuthTest.php
```

Backend code style (Laravel Pint, inside the backend container):

```sh
docker compose exec backend vendor/bin/pint --test   # dry-run (check only)
docker compose exec backend vendor/bin/pint          # actually fix files
```

Frontend: no automated test suite is configured — validate with a production build inside the frontend container:

```sh
docker compose exec frontend npm run build
```

## Reference docs (`.ai/`)

- `.ai/prd.md` — product requirements and user stories.
- `.ai/tech-stack.md` — stack, versions, and rationale for key decisions.
- `.ai/db-plan.md` — schema, migrations, relationships, seed data.
- `.ai/auth-spec.md` — the multi-auth (Sanctum/JWT/Passport) contract and login flows.

## Issues ledger

The issue/fix tracker lives **outside the repo** at `~/.config/taskflow/issues.md`
(kept out of version control on purpose). It is the single source of truth for
known issues and fixes; update it in place and do not recreate or duplicate it
inside the repo.

If the file is ever lost, recreate it from the template below. Only this recipe is
tracked; the live file itself must always live at `~/.config/taskflow/issues.md`.

```markdown
# TaskFlow — Issues Ledger

Single source of truth for every known issue and fix in this repo.

**How to use it (the ritual — do this in order, every session):**
1. Read this file first — the baseline; do not rediscover issues from code.
2. `git log --oneline -25` — see what landed since your last visit.
3. For anything you are working on, verify against current `main` code, not memory.
4. Update status, never delete history:
   - `OPEN` — confirmed present in current `main`.
   - `FIXED` — fix committed *and merged into main* (state the commit/PR).
   - `VERIFIED` — a test or manual repro confirms the fix actually holds.
5. Reference the issue ID in commit messages, e.g. `fix: B9 make oauth/client auth'd (closes #B9)`.
6. `main` is the only truth; a fix counts as done only once merged into `main`.

## Legend
- **Area:** BE = backend, FE = frontend, INFRA = docker/nginx/scripts, DOCS.
- **Status:** OPEN / FIXED / VERIFIED.

## Fixed & merged
### <Category>
| ID | Area | Issue | Status | Fix / commit | Notes |
|----|------|-------|--------|--------------|-------|

## Open
### Priority
### Moderate
### Hygiene / low
| ID | Area | Issue | Status | First-seen | Notes |
|----|------|-------|--------|-----------|-------|

## Status change log
- YYYY-MM-DD — (dated bullets preserving every status transition)
```

Adding issues: continue the active series (`B29…` for the next bug sweep, or a new
letter for a new category); append an `OPEN` row to the matching Open table with
`First-seen`, add a dated Status-change-log bullet, and flip to `FIXED` then
`VERIFIED` on resolution. Never reuse an ID.

## Rules

- Create feature branches with the `{task-id}-{branch-slug}` format, e.g. `B41-token-expiration` or `R9-task-search`; the slug is a short kebab-case summary of the work.
- Reference the task/issue ID in commit messages (`fix: ... (closes #B41)`, `feat: ... (#R9)`), matching the ledger convention.
- Do not commit generated/ignored files: `.env`, `backend/storage/oauth-*.key`, `node_modules`, `vendor`, `.nuxt`, `.output`.
- Keep `.env.example` in sync when adding environment variables.
- Follow the existing patterns when adding routes, controllers, requests, resources, and tests.