# TaskFlow — Product Requirements Document (PRD)

## Overview

TaskFlow is a lightweight full-stack task management application. It lets users organize their work into **projects**, manage **tasks** within them, and discuss tasks via **comments**. Users can share projects with others (member roles: admin / editor / viewer), transfer task ownership, and receive in-app notifications when tasks fall due or run overdue.

## Goals

- Provide a simple, self-hostable task management tool.
- Demonstrate a multi-strategy authentication API (Sanctum, JWT, OAuth2/Passport) in one backend.
- Keep the API clean and idiomatic Laravel: thin controllers, policies for authorization, Form Requests for validation, Resources for responses.
- Cover the core flows in a minimal Nuxt UI: login, dashboard, tasks, projects (with membership management), notifications, and manager administration.

## Non-Goals

- No real-time push — notifications are in-app only, delivered via 60s polling.
- No file uploads or attachments — the database is the single source of truth. All data (tasks, projects, comments, notifications, memberships) lives in database tables only; there is no file storage, media, or any other data source.
- No admin panel / Blade views (API-only backend).

## Users & Personas

- **User**: signs up / signs in, creates personal projects and tasks, comments on tasks, joins shared projects, receives due-date notifications.
- **Manager**: in addition to user capabilities, administers accounts (lock/unlock, roles, password reset), manages OAuth clients, and can transfer task ownership.

## User Stories

1. As a user, I can register and log in.
2. As a user, I can create, view, update, and delete my own projects.
3. As a user, I can create, view, update, and delete tasks (optionally assigned to one of my projects).
4. As a user, I can add comments to my tasks and list existing comments.
5. As a user, I can restrict access so that only the people I share a project with can see or modify my resources.
6. As a user, I can share a project with other users and grant them admin/editor/viewer roles.
7. As a user, I receive in-app notifications when my tasks fall due or run overdue, and I can dismiss them.
8. As a manager, I can transfer a task's ownership to another user and review its ownership history.
9. As a manager, I can lock/unlock accounts, change roles, reset passwords, and manage OAuth clients.
10. As a user, I can see which account I'm logged in as (name, role, auth method) and log out from the nav bar.

## Functional Requirements

### Authentication

- `POST /api/v1/register` — create account; returns a Sanctum token.
- `POST /api/v1/login` — Sanctum login (email + password) → token.
- `POST /api/v1/login/jwt` — JWT login → bearer token.
- `POST /api/v1/oauth/token` — Passport OAuth2 password grant (native endpoint).
- `POST /api/v1/logout` — revoke token for the active auth method.
- `GET /api/v1/user` — return the authenticated user.
- Refresh endpoints (public, throttled): `POST /api/v1/sanctum/refresh` and `POST /api/v1/jwt/refresh`; Passport refreshes via the OAuth2 refresh grant.
- Uniform token policy: 60-minute access tokens, 7-day refresh window — env-driven lifetimes.
- The auth method used by a request is declared via the `X-Auth-Method` header (`sanctum` default, `jwt`, or `passport`) and enforced by the `auth.multi` middleware.
- Locked accounts are blocked from login/refresh and from all API calls.

### Account self-service

- `PUT /api/v1/user/profile` — update own name/email (email must stay unique).
- `PUT /api/v1/user/password` — change own password (current password verified).
- Frontend: `/profile` page for name/email editing; change-password panel in the nav user dropdown.

### Projects & membership

- `GET /api/v1/projects`, `GET /api/v1/projects/{project}`
- `POST /api/v1/projects`
- `PUT /api/v1/projects/{project}` / `PATCH`
- `DELETE /api/v1/projects/{project}`
- Fields: `name` (required), `description` (nullable).
- Members: `GET/POST/PATCH/DELETE /api/v1/projects/{project}/members` — roster visible to owner + members; add/update/remove for the owner or a project admin. Roles: `admin` / `editor` / `viewer`.
- Access: the project index includes owned + joined projects. Owner and members can view; the owner or an admin can update/delete.

### Tasks

- `GET /api/v1/tasks` (own + shared-project tasks; filters: `search`, `due_from`, `due_to`, `project_id`, `user_id` for managers), `GET /api/v1/tasks/{task}`
- `POST /api/v1/tasks`, `PUT /api/v1/tasks/{task}`, `DELETE /api/v1/tasks/{task}`
- `POST /api/v1/tasks/{task}/transfer` — manager-only ownership transfer; every transfer is recorded in the task's ownership history.
- Fields: `title` (required), `description`, `status` (`pending` default), `priority` (`medium` default), `due_date`, optional `project_id`.
- Access is member-role aware: **view** for the owner, manager, or any project member; **update/comment** for the owner, manager, or a project admin/editor; **delete** for the owner, manager, or a project admin. Creating a task is scoped to owned + admin/editor projects.

### Comments

- `GET /api/v1/tasks/{task}/comments`, `GET /api/v1/tasks/{task}/comments/{comment}`
- `POST /api/v1/tasks/{task}/comments`
- `PUT /api/v1/tasks/{task}/comments/{comment}`
- `DELETE /api/v1/tasks/{task}/comments/{comment}`
- Fields: `body` (required); `task_id` and `user_id` set server-side.
- Access: only the author can **update**; **view** is allowed for the author, the task owner, or any project member; **delete** for the author, the task owner, or a project admin.

### Notifications

- `GET /api/v1/notifications`, `GET /api/v1/notifications/unread-count`
- `PATCH /api/v1/notifications/{notification}/read`, `POST /api/v1/notifications/read-all`
- `DELETE /api/v1/notifications/{notification}`, `POST /api/v1/notifications/clear-read`
- In-app due-date reminders (`task_due` / `task_overdue`) generated hourly by the `notifications:send-due` scheduler (idempotent, locked/completed users skipped); completing a task clears its reminders; read notifications older than the retention window are pruned daily; notifications are owner-scoped.

### User administration (manager-only)

- `GET /api/v1/users`, `GET /api/v1/users/search`
- `POST /api/v1/users/{user}/lock`, `POST /api/v1/users/{user}/unlock`
- `PATCH /api/v1/users/{user}/role`
- `PUT /api/v1/users/{user}/password`
- Locking a user revokes their tokens and blocks login; a manager cannot lock themselves or change their own role.

### OAuth client management (manager-only)

- `GET /api/v1/oauth/clients`
- `POST /api/v1/oauth/clients` — creates a password-grant client; the plain secret is shown once.
- `DELETE /api/v1/oauth/clients/{client}`

### Frontend (client)

- Pages: dashboard (stat cards + "Coming up"), login, profile, tasks (search/filters, due badges, ownership history), projects (list + per-project detail with member roster and task overview), users, oauth.
- Persists token + auth method in `localStorage`; expired tokens auto-refresh via the relevant refresh endpoint (single-flight, retry-safe).
- Nav shows the logged-in account (initial avatar, name, role + auth-method badges, logout) and a notification bell with an unread badge (60s polling).

## Non-Functional Requirements

- API responses are JSON; errors follow `{ success: false, message: ... }` with `401`/`403`/`422` semantics.
- Validation messages localized (default `en`, `pl` provided).
- Backend covered by PHPUnit feature tests.
- Everything runs via Docker Compose; single-command startup.
- SQLite for local dev and tests (no external DB service required).

## Future Roadmap

Ordered by implementation simplicity (easiest first).

- CSV / JSON export of tasks and projects.
- Tags / labels for tasks with filtering.
- Project archiving (soft "archived" flag).
- Activity feed / audit log per project.
- Calendar / agenda view of due dates.
- Recurring tasks (frequency column; regenerated on completion).
- Task assignment to project members.
- Forgot-password / password reset via security question.
- Teams/orgs beyond per-project membership — planned for the near future.
- Passkeys (passwordless WebAuthn login).
- Personal API tokens (user-owned, not tied to an OAuth client) — maybe someday.