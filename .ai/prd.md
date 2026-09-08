# TaskFlow — Product Requirements Document (PRD)

## Overview

TaskFlow is a lightweight full-stack task management application. It lets users organize their work into **projects**, manage **tasks** within them, and discuss tasks via **comments**.

The product is intentionally simple: a single-user-per-resource ownership model (no team collaboration), a REST API, and a minimal Nuxt client.

## Goals

- Provide a simple, self-hostable task management tool.
- Demonstrate a multi-strategy authentication API (Sanctum, JWT, OAuth2/Passport) in one backend.
- Keep the API clean and idiomatic Laravel: thin controllers, policies for authorization, Form Requests for validation, Resources for responses.
- Support a minimal UI that covers the core flows (login, list/create tasks, list/create projects).

## Non-Goals

- No teams, organizations, roles, or shared projects.
- No real-time push/notifications.
- No file attachments.
- No admin panel / Blade views (API-only backend).

## Users & Personas

- **Individual user**: signs up / signs in, creates personal projects and tasks, comments on tasks.
- **API consumer / frontend**: calls the REST API using one of three auth methods.

## User Stories

1. As a user, I can register and log in.
2. As a user, I can create, view, update, and delete my own projects.
3. As a user, I can create, view, update, and delete tasks (optionally assigned to one of my projects).
4. As a user, I can add comments to my tasks and list existing comments.
5. As a user, I can restrict access so that only I can see or modify my resources.

## Functional Requirements

### Authentication

- `POST /api/v1/register` — create account; returns a Sanctum token.
- `POST /api/v1/login` — Sanctum login (email + password) → token.
- `POST /api/v1/login/jwt` — JWT login → bearer token.
- `GET /api/v1/oauth/client` — create/return a password-grant Passport client (dev convenience).
- `POST /api/v1/oauth/token` — Passport OAuth2 password grant (native endpoint).
- `POST /api/v1/logout` — revoke token for the active auth method.
- `GET /api/v1/user` — return the authenticated user.
- The auth method used by a request is declared via the `X-Auth-Method` header (`sanctum` default, `jwt`, or `passport`) and enforced by the `auth.multi` middleware.

### Projects

- `GET /api/v1/projects`, `GET /api/v1/projects/{project}`
- `POST /api/v1/projects`
- `PUT /api/v1/projects/{project}` / `PATCH`
- `DELETE /api/v1/projects/{project}`
- Fields: `name` (required), `description` (nullable).
- Ownership enforced by `ProjectPolicy` (owner-only view/update/delete).

### Tasks

- `GET /api/v1/tasks`, `GET /api/v1/tasks/{task}`
- `POST /api/v1/tasks`, `PUT /api/v1/tasks/{task}`, `DELETE /api/v1/tasks/{task}`
- Fields: `title` (required), `description`, `status` (`pending` default), `priority` (`medium` default), `due_date`, optional `project_id`.
- Ownership enforced by `TaskPolicy` (owner-only view/update/delete).

### Comments

- `GET /api/v1/tasks/{task}/comments`
- `POST /api/v1/tasks/{task}/comments`
- Fields: `body` (required); `task_id` and `user_id` set server-side.
- Comments are nested under tasks; no update/delete endpoints in the current scope.

### Frontend (client)

- Pages: index, login, tasks, projects.
- Persists token + auth method in `localStorage`.
- Switches auth method at login time (Sanctum / JWT / Passport via OAuth2 password grant).

## Non-Functional Requirements

- API responses are JSON; errors follow `{ success: false, message: ... }` with `401`/`403`/`422` semantics.
- Validation messages localized (default `en`, `pl` provided).
- Backend covered by PHPUnit feature tests.
- Everything runs via Docker Compose; single-command startup.
- SQLite for local dev and tests (no external DB service required).

## Out of Scope / Future Ideas

- Pagination, search, filters.
- Teams / shared projects / permissions matrix.
- Task assignees, due-date reminders.
- Passport client management UI.