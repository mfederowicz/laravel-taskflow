# TaskFlow — Tech Stack

## Overview

| Layer | Technology | Version | Notes |
|-------|------------|---------|-------|
| Backend | PHP | 8.3+ (container: `php:8.5-fpm`) | API-only |
| Backend framework | Laravel | ^13.17 | REST API, no Blade views |
| Frontend | Node.js | 24 (alpine container) | |
| Frontend framework | Nuxt 4 / Vue 3 | nuxt ^4.5.2, vue ^3.5.42 | auto-imports |
| Database | SQLite | file at `backend/database/database.sqlite` | dev + tests |
| Web server | nginx | alpine | reverse proxy + `/api/*` → PHP-FPM |
| Containerization | Docker / Docker Compose | — | services: `nginx`, `backend`, `frontend` |
| Auth — Sanctum | laravel/sanctum | ^4.0 | personal access tokens (default) |
| Auth — JWT | tymon/jwt-auth | ^2.3 | bearer tokens via `X-Auth-Method: jwt` |
| Auth — OAuth2 | laravel/passport | ^13.0 | password grant |

## Rationale for key decisions

- **Three auth strategies in one app**: the repo is an exercise in supporting Sanctum, JWT, and Passport behind a single `auth.multi` middleware (`backend/app/Http/Middleware/MultiAuth.php`), selected by the `X-Auth-Method` request header.
- **SQLite instead of MySQL**: keeps local dev and CI lightweight; a MySQL container is intentionally not part of the Docker stack. `pdo_mysql` is still compiled into the backend image for future flexibility.
- **nginx in front of both apps**: one entry point (`${APP_PORT:-8080}`); `/api/*` is fastcgi-proxied to PHP-FPM, everything else goes to the Nuxt dev server.
- **Monorepo layout**: `backend/` (Laravel) and `frontend/` (Nuxt) as siblings at the repo root, with shared infra in `docker/`, `bin/`, and root `.env`.
- **PHP 8.5 image**: backend container is `php:8.5-fpm`; composer is injected from `composer:2`.

## Directory map (top level)

```
backend/    Laravel API (app/Http, app/Models, app/Policies, routes/api.php, tests/Feature)
frontend/   Nuxt 4 client (app/pages, app/composables, app/components, app/plugins, app/types)
docker/     docker-compose.yml, image-backend/, image-frontend/, configs/nginx/default.conf
bin/        run.sh (compose wrapper), artisan (php artisan in backend container)
.env        shared environment (mounted into the backend container)
```

## Tooling / quality

- Backend tests: PHPUnit feature tests (`backend/tests/Feature`) → `./bin/artisan test`.
- Backend style: Laravel Pint → `docker compose exec backend vendor/bin/pint --test`.
- Frontend package scripts: `dev`, `build`, `generate`, `preview` (Nuxt).

## Versioning of docs

If the stack changes (new major versions, new services), update this file and confirm `.env.example` stays in sync.