# TaskFlow

TaskFlow is a full-stack task management application split across two independent apps in one repository, plus a small Filament/Livewire panel living inside the backend:

- `backend/` — Laravel 13 REST API (PHP 8.3+), also hosting a Filament v3/Livewire panel at `/filament`
- `frontend/` — Nuxt 4 / Vue 3 client, served under `/nuxt`

A static landing page at `/` links to both clients.

Infrastructure lives at the repo root:

- `docker/` — Docker Compose stack, images, nginx config
- `bin/` — helper scripts (`run.sh`, `artisan`)
- `.env` — shared environment (injected into the backend container via `env_file`)

## Repository layout

```
.
├── backend/                          # Laravel API-only application
│   ├── app/
│   │   ├── Http/Controllers/Api/     # AuthController, TaskController, ProjectController, OrganizationController, CommentController, UserController
│   │   ├── Http/Middleware/MultiAuth.php  # multi-auth guard (sanctum / jwt / passport)
│   │   ├── Http/Middleware/EnsureUserIsActive.php  # rejects locked accounts (403) on protected routes
│   │   ├── Http/Requests/            # Form Request validation
│   │   ├── Http/Resources/           # JSON resources
│   │   ├── Enums/                    # UserRole, UserStatus, OrganizationRole, ProjectMemberRole (backed string enums)
│   │   ├── Models/                   # User, Task, Project, Comment, Organization, OrganizationMember, ProjectMember
│   │   ├── Policies/                 # ProjectPolicy, TaskPolicy, CommentPolicy, UserPolicy, OrganizationPolicy, OrganizationMemberPolicy
│   │   ├── Filament/Resources/       # UserResource, TaskResource (Filament panel, Eloquent-backed)
│   │   ├── Filament/Widgets/         # TaskStatsOverview (dashboard stats)
│   │   └── Providers/Filament/       # AdminPanelProvider (panel config, id/path 'filament')
│   ├── config/                       # incl. jwt.php, passport.php, sanctum.php
│   ├── database/migrations/          # SQLite schema
│   ├── routes/api.php                # all API routes
│   ├── tests/Feature/                # PHPUnit API tests
│   ├── public/landing.html           # static landing page served at /
│   └── lang/                         # en default, pl validation messages
├── frontend/                         # Nuxt 4 / Vue 3 client (served under /nuxt, app.baseURL: '/nuxt/')
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
│   └── configs/nginx/default.conf    # routes /, /api, /filament, /livewire, /nuxt
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

- `nginx` — entry point on `${APP_PORT:-8080}`; routes `/api/*`, `/filament*`, and `/livewire/*` to the backend, `/nuxt/*` to the frontend, static Filament assets (`/css`, `/js`) directly from the backend's `public/`, and `/` to a static landing page (`backend/public/landing.html`) with links to `/nuxt` and `/filament`
- `backend` — PHP-FPM (Laravel), working dir `/var/www/html`; env vars are injected from the repo root `.env` via `env_file`; the container entrypoint also starts `php artisan schedule:work` in the background (in-app notification scheduler)
- `frontend` — Nuxt dev server on port 3000, working dir `/app`; `node_modules` is a named volume

Run artisan commands (executes inside the backend container):

```sh
./bin/artisan migrate --seed
./bin/artisan passport:keys
./bin/artisan jwt:secret
```

## Backend conventions

- The REST API remains API-only: routes live exclusively in `routes/api.php`, and `/api/*` never renders Blade — API controllers, Form Requests, and Resources are unaffected by the Filament panel below.
- Authentication: the `auth.multi` middleware (`MultiAuth`) resolves the guard from the `X-Auth-Method` request header — one of `sanctum` (default), `jwt`, or `passport`.
- Authenticated requests must send `X-Auth-Method: sanctum|jwt|passport` plus `Authorization: Bearer <token>`.
- Controllers are thin; keep business logic in models/policies, use Form Requests for validation, and Resources for JSON responses.
- Auth failures return `401 { "success": false, "message": "Unauthenticated." }`; validation failures return `422` with field errors.
- Token policy (uniform): access tokens live 60 minutes; refresh window is 7 days — JWT/Sanctum rotate via `POST /api/v1/jwt/refresh` / `POST /api/v1/sanctum/refresh` (public, throttled), Passport via the OAuth2 refresh grant. Lifetimes are env-driven (`SANCTUM_EXPIRATION`, `JWT_TTL`, `JWT_REFRESH_TTL`, `PASSPORT_TOKEN_EXPIRATION_MINUTES`, etc.).
- API resources: Projects, Tasks, Comments (nested under tasks), and Organizations (with nested members). Organization membership auto-grants access to the org's projects via `Project::effectiveRole()` (owner → explicit project-member role → org role → none); groups must never bypass `effectiveRole`.
- Database is SQLite (`database/database.sqlite`); schema lives in `database/migrations`.
- Validation messages are localized — Polish strings in `lang/pl/validation.php`.

## Filament panel (`/filament`)

- A Livewire-based admin panel, separate from the API and the Nuxt client, mounted by `AdminPanelProvider` (`id`/`path`: `filament`). Session/`web`-guard auth via Filament's own login page, not the API's multi-auth.
- Access: `User::canAccessPanel()` allows any active (non-locked) user in — it is not manager-only. Individual resources still enforce their own policy: `UserResource` (lock/unlock/change-role/reset-password) is effectively manager-only because `UserPolicy::viewAny` requires `isManager()`.
- Resources read/write through Eloquent directly (never the REST API) and reuse the same policies and visibility rules as the API — e.g. `TaskResource::getEloquentQuery()` and `TaskStatsOverview` both use `Task::visibleTo($user)`, the same scope backing the API's task index, so keep that scope as the one place task-visibility logic lives.
- When adding a resource/widget for a model that already has an API controller, check its Policy and the controller's scoping query first and reuse them — do not re-derive authorization or visibility rules from scratch.
- Do not modify `routes/api.php`, API controllers, Form Requests, or API Resources to support the panel; the panel is additive.

## Frontend conventions

- Nuxt 4 auto-imports components, composables, and pages; do not manually import `useApi`/`useAuth`.
- `useApi` (`app/composables/useApi.ts`) wraps `$fetch` and adds `Accept: application/json`, `X-Auth-Method`, and `Authorization: Bearer` automatically.
- `useAuth` stores the token and chosen auth method in `localStorage`; `app/plugins/auth-header.ts` restores the method across requests.
- All API calls go to `/api/*`, proxied by nginx to the backend.
- The app is served under `app.baseURL: '/nuxt/'` (`nuxt.config.ts`). `NuxtLink`/`navigateTo` handle this automatically, but Nuxt's global `$fetch` prefixes plain relative URLs with `app.baseURL` too — so every raw `$fetch('/api/...')` call must pass `baseURL: ''` to stop it from resolving to `/nuxt/api/...` (404). `useApi`'s `apiFetch`/`apiDownload` already do this; any new direct `$fetch` call to `/api/*` must do the same.

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
5. Reference the issue ID in commit messages with a bare `#ID` suffix, e.g. `fix: make oauth/client auth'd (#B9)` — never `(closes #ID)`.
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
- Create the feature branch before starting any work; use a separate branch per task/issue when multiple are done in one session.
- Do not push branches — the user handles pushing and PR creation.
- Reference the task/issue ID in commit messages with a bare `#ID` suffix (e.g. `fix: ... (#B41)`, `feat: ... (#R27)`); do not add `(closes #ID)` — a branch's last commit may be an unplanned fix, so the `closes` wording misleads and makes the log inconsistent.
- Do not commit generated/ignored files: `.env`, `backend/storage/oauth-*.key`, `node_modules`, `vendor`, `.nuxt`, `.output`.
- Keep `.env.example` in sync when adding environment variables.
- Follow the existing patterns when adding routes, controllers, requests, resources, and tests.