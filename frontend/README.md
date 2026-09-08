# TaskFlow — Frontend

Nuxt 4 / Vue 3 client for the TaskFlow task management application. It talks to the Laravel API (in `backend/`) through `/api/*`, proxied by nginx.

## Stack

- Nuxt 4 (Vue 3)
- TypeScript
- No UI/library — plain components and global CSS in `app/assets/css/main.css`

## Repository context

This app lives under `frontend/` in the TaskFlow monorepo:

- `backend/` — Laravel REST API
- `docker/` and `bin/` — Docker Compose stack and helper scripts
- Root `AGENTS.md` — full workflow; reference docs in `.ai/`

## Development

Everything runs inside Docker. Start the stack from the repo root:

```bash
./bin/run.sh start
```

The frontend container runs the Nuxt dev server on port 3000; nginx serves it at `http://localhost:${APP_PORT}` (default `8080`).

Validate the client with a production build (no test suite is configured):

```bash
docker compose exec frontend npm run build
```

## What's here

```
app/
├── app.vue               # root component
├── pages/                # index, login, tasks, projects
├── components/AppNav.vue # shared navigation
├── composables/
│   ├── useAuth.ts        # token + auth method persisted in localStorage
│   └── useApi.ts         # $fetch wrapper adding the auth headers
├── plugins/auth-header.ts# restores the stored auth method across requests
├── types/                # task.ts, project.ts
└── assets/css/main.css   # global styling
```

Nuxt 4 auto-imports pages, components, and composables — `useApi`/`useAuth` are used without explicit imports.

## Authentication

The login page (`app/pages/login.vue`) lets the user pick one of three methods, all supported by the API:

- **Sanctum** — `POST /api/v1/login`
- **JWT** — `POST /api/v1/login/jwt`
- **Passport** — OAuth2 password grant via `GET /api/v1/oauth/client` + `POST /api/v1/oauth/token`

The selected token and method are stored in `localStorage` and attached to every request:

- `Accept: application/json`
- `X-Auth-Method: sanctum|jwt|passport`
- `Authorization: Bearer <token>`

See `.ai/auth-spec.md` for the full contract.

## API calls

All requests go through `useApi()` (`app/composables/useApi.ts`), which wraps `$fetch` and injects the headers above. Deep links pages:

- `/` — landing
- `/login` — sign in
- `/tasks` — task list/management
- `/projects` — project list/management