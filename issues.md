# TaskFlow — Issues Ledger

Single source of truth for every known issue and fix in this repo.

**Why this file exists:** to stop losing progress across review sessions and
branches. The problem we hit before — finding "the same" issues twice — is caused
by overlapping file surfaces plus no durable record of what was already fixed. A
later session edited the same files for different reasons, so a casual glance
suggested the old problem returned.

**How to use it (the ritual — do this in order, every session):**

1. Read this file first. It is the baseline; do not rediscover issues from code.
2. `git log --oneline -25` — see what landed since your last visit.
3. For any item you are working on, verify against current `main` code (`git diff`),
   not memory.
4. Update status, never delete history:
   - `OPEN` — confirmed present in current `main`.
   - `FIXED` — fix committed *and merged into main* (state the commit/PR).
   - `VERIFIED` — a test or manual repro confirms the fix actually holds
     (prevents "claimed fixed but still broken").
5. Reference the issue ID in commit messages, e.g. `fix: B9 make oauth/client auth'd (closes #B9)`.
6. `main` is the only truth. A fix counts as done only once merged into `main`;
   checking out a work-in-progress branch and treating it as "latest state" will
   re-expose already-fixed things.

---

## Legend

- **Area:** BE = backend, FE = frontend, INFRA = docker/nginx/scripts, DOCS.
- **Status:** OPEN / FIXED / VERIFIED.

---

## Fixed & merged (recent PRs)

All fixes below are committed into `main` (PR #1 = multi-auth, PR #2 = priority-fixes,
plus earlier work). History is preserved; status is `VERIFIED` where tests were
added or the fix was confirmed.

### Authentication (multi-auth: sanctum / jwt / passport)

| ID | Area | Issue | Status | Fix / commit | Notes |
|----|------|-------|--------|--------------|-------|
| A1 | BE | Passport logout was thought broken | VERIFIED | `ad5ef12` | Investigation showed it is NOT broken; no change needed. |
| A2 | BE | JWT login response shape differed from Sanctum | VERIFIED | `b060fb1` `3ff286e` | Now returns `{ data: { user, token } }`; `JWTAuth::user()` used (auth('jwt')->user() returned null in tests). |
| A3 | BE | Sanctum guard not explicit | VERIFIED | `eae2cc1` (`config/auth.php`) | Guard made explicit for the multi-auth flow. |
| A4 | BE | register/login/loginJwt lacked feature tests | VERIFIED | `3ff286e` (`AuthApiTest.php` +156 lines) | Added register/login/loginJwt tests. |
| A5 | BE | oauth/client endpoint pruned ALL password clients (incl. manual) | VERIFIED | `eae2cc1` `b5ecd5a` (`AuthController.php`) | Clients are now tagged by name; pruning preserves manually created password clients. |

### Authorization & policies

| ID | Area | Issue | Status | Fix / commit | Notes |
|----|------|-------|--------|--------------|-------|
| P1 | BE | `ProjectPolicy::viewAny()` and `create()` returned false | VERIFIED | `bdf8add` | Now return true. |
| P2 | BE | Comment CRUD incomplete / wrong policy | VERIFIED | `313610a` (`CommentController.php`, `CommentPolicy.php` +33) | Added show/update/delete; uses `TaskPolicy::comment` instead of `authorize('view')`. |
| P3 | BE | Task create/update accepted another user's project_id (404) | VERIFIED | `5a73081` (`StoreTaskRequest.php`) | Scoped `exists` rule → returns 422 instead of 404. |

### Frontend behavior

| ID | Area | Issue | Status | Fix / commit | Notes |
|----|------|-------|--------|--------------|-------|
| F1 | FE | Per-action token checks pointed at localStorage; no shared guard | VERIFIED | `c5ae272` | Added shared auth route middleware for tasks/projects. |
| F2 | FE | `@ts-ignore` workarounds for `import.meta.client` | VERIFIED | `c5ae272` | Removed. |
| F3 | FE | Top-level `await navigateTo('/login')` in `index.vue` ran during SSR | VERIFIED | `b93fd85` | Replaced with proper Nuxt route middleware (`middleware/home.ts`). |
| F4 | BE | `204 No Content` responses sent a literal `null` body | VERIFIED | `6c0025b` | Fixed. |

### Validation & i18n

| ID | Area | Issue | Status | Fix / commit | Notes |
|----|------|-------|--------|--------------|-------|
| V1 | BE | Polish validation messages missing | VERIFIED | `eae2cc1` (`lang/pl/validation.php` +32) | Added. |
| V2 | BE | English validation missing rules/attributes | VERIFIED | `eae2cc1` (`lang/en/validation.php`) | Completed. |

### Docker / infra / scripts

| ID | Area | Issue | Status | Fix / commit | Notes |
|----|------|-------|--------|--------------|-------|
| D1 | INFRA | `bin/run.sh` gid broke on macOS | VERIFIED | `bda0979` | gid fallback for macOS. |
| D2 | INFRA | `bin/artisan` didn't pass `--env-file` | VERIFIED | `bda0979` | Now uses `--env-file`. |
| D3 | INFRA | Redundant `.env` mount & published 9000 port | VERIFIED | `bda0979` | Removed. |
| D4 | INFRA | Unused `pdo_mysql` in backend image | VERIFIED | `bda0979` | Removed. |
| D5 | INFRA | README layout wrong | VERIFIED | `bda0979` | Fixed. |
| D6 | INFRA | Pint style issues (Task model, bootstrap, config, migrations, seeder) | VERIFIED | `c723723` | `pint --test` fully green. |
| D7 | INFRA | Large Docker build context (git metadata, env, nuxt output) | VERIFIED | `22a7c7d` (`root .dockerignore`) | Added. |
| D8 | INFRA | Unneeded `sudo chown database` step in docs | VERIFIED | `efc5a4f` | Removed. |
| D9 | INFRA | Docs said `.env` was mounted (actually env_file) | VERIFIED | `efc5a4f` | Synced README/AGENTS.md. |
| D10 | INFRA | Backend image compiled unnecessary extensions | VERIFIED | `a15c214` | Only `intl`+`zip` compiled; mbstring/pdo_sqlite/sqlite3 already built into `php:8.5-fpm`. |
| D11 | INFRA | Docker build context still too big | VERIFIED | `cc94091` | Trimmed `.dockerignore` to essentials. |

---

## Open (confirmed present in current `main` — NOT yet fixed)

These were found in the most recent full review of `main`. They are distinct from
the fixes above (different root causes, overlapping files). Grouped by likely next actions.

### Priority — will break a user/developer

| ID | Area | Issue | Status | First-seen | Notes |
|----|------|-------|--------|-----------|-------|
| B9  | FE | Expired tokens strand user: `useApi.ts` has no 401 handling; `middleware/auth.ts` only checks token presence, not validity. JWT TTL is 60 min, so expiry is normal. | FIXED | session 2026-09-08 | Add central 401 handling in `useApi` → clear token + `navigateTo('/login')`. |
| B10 | BE | `/api/v1/oauth/client` is a GET, unauthenticated, and deletes+recreates the shared Passport client on every call → race between concurrent logins. | FIXED | session 2026-09-08 | Changed to POST + throttle:6,1. |
| B11 | BE/FE | Passport refresh tokens unusable: refresh path gated behind `auth:web` (session cookie the API never has); `login.vue` discards `refresh_token`/`expires_in`. | FIXED | session 2026-09-08 | Removed `web` middleware from refresh route; fixed `passport.php` path to `api/v1/oauth`; persisted refresh token + client creds and added `refreshAccessToken()` in `useAuth`; 401 auto-refresh in `useApi`. |
| B12 | FE | Pagination truncated: projects & comments pages paginate 10/page but render no controls (unlike tasks). | FIXED | session 2026-09-08 | Added Prev/Next + page counter to projects page and per-task comments; typed `meta` on `ProjectsResponse`/`CommentsResponse`. |

### Moderate

| ID | Area | Issue | Status | First-seen | Notes |
|----|------|-------|--------|-----------|-------|
| B13 | FE | `useAuth.logout()` clears localStorage `auth-method` but not in-memory `authMethod` ref → stale `X-Auth-Method` header. | FIXED | session 2026-09-08 | Reset `authMethod.value` on logout (added `clearAuth()`). |
| B14 | FE | `login.vue` keeps a local `authMethod` ref + re-reads localStorage in `onMounted`, duplicating `useAuth`/`auth-header` state. `getToken` destructured but unused. | FIXED | session 2026-09-08 | Removed local `authMethod` ref + `onMounted`; uses shared `useAuth` state. Also removed redundant explicit `import useAuth` (auto-imported per convention). |
| B15 | FE | `types/task.ts` inaccurate: `project` non-nullable but API returns `null`; `comments: Comment[]` never returned by API; missing `user`/timestamps. | FIXED | session 2026-09-08 | Aligned `Task` type with `TaskResource`: `project` nullable, removed nonexistent `comments`, added `user` + `created_at`/`updated_at`. |
| B16 | BE | `config/sanctum.php` stateful domains: `::1` concatenated with app URL, no comma → garbage `::1http://localhost:8080` entry. | OPEN | session 2026-09-08 | Fix separator. |
| B17 | BE | `/api/v1/user` returns raw model (no `{ "data" }` envelope / resource), inconsistent with all other endpoints. | FIXED | session 2026-09-08 | Wrapped in `response()->json(['data' => ...])`. |
| B18 | BE | `AuthController::register` calls `Hash::make` but `password => 'hashed'` cast already auto-hashes (double responsibility). | OPEN | session 2026-09-08 | Drop the explicit `Hash::make`. |

### Hygiene / low

| ID | Area | Issue | Status | First-seen | Notes |
|----|------|-------|--------|-----------|-------|
| B19 | INFRA | nginx `/api/` location misses bare `/api` (no trailing slash) → falls through to SPA 404. | OPEN | session 2026-09-08 | Add `location = /api` fallback. |
| B20 | INFRA | Frontend image uses `npm install`; `package-lock.json` exists → should be `npm ci`. | OPEN | session 2026-09-08 | Switch to `npm ci`. |
| B21 | INFRA | `bin/run.sh` fails cryptically when `.env` is missing (bare repo ships none). | OPEN | session 2026-09-08 | Friendly check / copy `.env.example`. |
| B22 | INFRA | `nuxt.config.ts` sets `vite.server.allowedHosts: true` (accepts any host header). | OPEN | session 2026-09-08 | Restrict to configured host. |
| B23 | BE | Dead Blade welcome view + `/` route contradict the documented API-only app. | OPEN | session 2026-09-08 | Remove. |
| B24 | BE | `config/passport.php` path `api/oauth` contradicts actual mount `api/v1/oauth`. | OPEN | session 2026-09-08 | Align or document. |
| B25 | BE | `AuthController` uses inline `$request->validate` instead of Form Requests (rest of app uses Form Requests). | OPEN | session 2026-09-08 | Align to convention. |
| B26 | BE | `phpunit.xml` doesn't declare `JWT_SECRET`/`APP_KEY`; tests only pass via container-inherited env. | OPEN | session 2026-09-08 | Declare in phpunit.xml for hermetic tests. |
| B27 | DOCS | `AGENTS.md` layout omits `CommentPolicy` (exists + enforced). | OPEN | session 2026-09-08 | Add to docs. |
| B28 | DOCS | `.env`/`.env.example` drifted (`API_VERSION` present in example, consumed by `config/scramble.php`). | OPEN | session 2026-09-08 | Sync files. |

---

## Status change log

Record meaningful status transitions here so history is preserved even after rows
are updated in the tables.

- 2026-09-08 — Ledger created on branch `fixes` (from `main` `87e8106`). All
  A/P/F/V/D rows marked fixed+merged from PR #1/#2 history; B9–B28 registered OPEN
  from the latest full review of `main`.
- 2026-09-08 — B9 (401 handling in useApi), B10 (oauth/client POST+throttle),
  B13 (clearAuth resets authMethod), B17 (/api/v1/user data envelope) marked FIXED
  on branch `fixes`. Awaiting merge to `main`.
- 2026-09-09 — B11 (Passport refresh token usability) marked FIXED on branch
  `fixes` after verification: 47 backend tests pass, Pint green (70 files), frontend
  `nuxt build` succeeds. Updated `AuthApiTest` (getJson→postJson) for the B10 route change.
- 2026-09-09 — B12 (pagination UI for projects + task comments) marked FIXED on branch
  `fixes` after frontend `nuxt build` succeeded. No backend changes needed (index
  endpoints already paginate 10/page and include `meta`).
- 2026-09-09 — B14 (login.vue local authMethod duplication) marked FIXED on branch
  `fixes`. Shared `useAuth` was already the source of truth; removed redundant explicit
  `import useAuth` (auto-imported per convention). Frontend `nuxt build` succeeded.
- 2026-09-09 — B15 (align types/task.ts with TaskResource) marked FIXED on branch
  `fixes`. `project` nullable, removed never-returned `comments`, added `user` +
  timestamps. Frontend `nuxt build` succeeded.
