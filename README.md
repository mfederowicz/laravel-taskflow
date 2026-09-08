# TaskFlow

TaskFlow is a small educational project-management application built to practice and demonstrate modern **Laravel, Vue, and Nuxt** development.

The project focuses on clean, understandable fundamentals such as authentication, API development, Eloquent relationships, validation, authorization, testing, and frontend API integration.

## Features

* Multi-method API authentication
* Laravel Sanctum API authentication
* JWT API authentication
* Laravel Passport OAuth2 authentication
* Token-based API authentication
* Logout and authentication state handling
* User-owned Projects
* User-owned Tasks
* Projects containing Tasks
* Comments on Tasks
* Task CRUD
* Project CRUD
* Task status and priority filtering
* Pagination
* Form Request validation
* API Resources
* Policies and resource authorization
* Protection against assigning Tasks to another user's Project
* JSON API error handling for common HTTP errors
* Frontend validation error display
* Nuxt/Vue task management UI
* Nuxt/Vue project management UI
* Basic responsive styling
* Laravel feature tests
* Docker-based development environment

## Tech Stack

### Backend

* PHP 8.5
* Laravel 13
* Laravel Sanctum
* `tymon/jwt-auth`
* Laravel Passport
* Eloquent ORM
* SQLite for local development and automated tests
* MySQL-compatible configuration for environments using MySQL

### Frontend

* Nuxt
* Vue
* TypeScript
* Vite

### Infrastructure

* Docker
* Docker Compose
* Nginx
* PHP-FPM
* Node.js

## Application Architecture

The application is split into a Laravel backend and a Nuxt frontend.

```text
Browser
   |
   v
Nginx
   |
   +--------------------+
   |                    |
   v                    v
Nuxt / Vue          Laravel API
                        |
                        v
                    Eloquent
                        |
                        v
                  SQLite / MySQL
```

Nginx exposes a single application entry point:

```text
/api/*   -> Laravel
/*       -> Nuxt
```

The frontend does not expose its development port directly to the host.

## Domain Model

The current domain relationships are:

```text
User
├── hasMany Projects
├── hasMany Tasks
└── hasMany Comments

Project
├── belongsTo User
└── hasMany Tasks

Task
├── belongsTo User
├── belongsTo Project
└── hasMany Comments

Comment
├── belongsTo User
└── belongsTo Task
```

Conceptually:

```text
User
 ├── Project A
 │    ├── Task 1
 │    │    ├── Comment
 │    │    └── Comment
 │    └── Task 2
 │
 └── Project B
      └── Task 3
```

## Requirements

You need:

* Docker
* Docker Compose
* Git

No PHP, Composer, Node.js, or Nginx installation is required on the host because they run inside Docker containers.

## Installation

Clone the repository and enter the project directory.

Copy the environment file:

```bash
cp .env.example .env
```

Build and start the application:

```bash
./bin/run.sh build
```

Generate the Laravel application key:

```bash
./bin/artisan key:generate
```

Generate JWT_SECRET (only once if JWT_SECRET is empty):

```bash
./bin/artisan jwt:secret
```

Generate passport keys (only once if they don't exist in app/backend/storage):

```bash
./bin/artisan passport:keys
```

Fix ownership of database dir:

```bash
sudo chown www-data:www-data app/backend/database/ -R
```

Run migrations:

```bash
./bin/artisan migrate --seed
```



The application is then available through the configured host.

The development setup currently uses:

```text
http://localhost:8080
```

If this hostname is not configured on your machine, point it to `127.0.0.1` or adjust the frontend/Vite host configuration accordingly.

## Docker Services

The project contains three application services.

### Backend

Laravel + PHP-FPM.

```text
taskflow-backend
```

The container runs PHP-FPM on port `9000`.

### Frontend

Nuxt development server.

```text
taskflow-frontend
```

The Nuxt server listens internally on port `3000`.

### Nginx

Single public entry point.

```text
taskflow-nginx
```

Nginx exposes the application through the configured `APP_PORT`, which defaults to `8080`.

There is intentionally no database container in the current development environment.

## Environment

The root `.env` file is shared with the backend container.

Example development configuration:

```dotenv
APP_NAME=TaskFlow
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8080
APP_PORT=8080
DB_CONNECTION=sqlite
JWT_SECRET=
```

JWT authentication requires `JWT_SECRET`.

If the local `.env` does not already contain a JWT secret, generate one with:

```bash
./bin/artisan jwt:secret
```



The local `.env` file is not committed to Git.

Use `.env.example` as the committed environment template.

### Application Port

`APP_PORT` controls the port exposed by the Nginx container on the host.

The default is:

```dotenv
APP_PORT=8080
```

### MySQL

The application can be configured to use MySQL instead of SQLite:

```dotenv
DB_CONNECTION=mysql
DB_HOST=dbhost
DB_PORT=3306
DB_DATABASE=taskflow.app
DB_USERNAME=tester
DB_PASSWORD=tester
```

The current Docker setup does not include a MySQL container because the development environment uses SQLite.

## Database

SQLite is used for local development and automated tests because it keeps the project setup lightweight.

The SQLite database is located at:

```text
app/backend/database/database.sqlite
```

Laravel migrations are used to create and evolve the database schema.

Run migrations with:

```bash
./bin/artisan migrate
```

Reset and seed the database with:

```bash
./bin/artisan migrate:fresh --seed
```

## Artisan Helper

The repository contains a convenience wrapper:

```text
bin/artisan
```

This allows Laravel Artisan commands to be executed without entering the backend container manually.

Examples:

```bash
./bin/artisan --version
./bin/artisan migrate
./bin/artisan migrate:fresh --seed
./bin/artisan route:list
./bin/artisan tinker
./bin/artisan test
```

Laravel generator commands can also be run through the wrapper:

```bash
./bin/artisan make:model Project -m
./bin/artisan make:controller Api/ProjectController
./bin/artisan make:request StoreProjectRequest
```

Generated files are handled on the host so that newly created project files are owned by the user running the command rather than by the Docker container user.

## Authentication

TaskFlow demonstrates three authentication mechanisms:

- **Sanctum** — Laravel personal access tokens
- **JWT** — `tymon/jwt-auth`
- **Passport** — OAuth2 authorization server

Protected API routes use the `auth.multi` middleware. The authentication
method is selected with the `X-Auth-Method` request header.

Supported values:

- `sanctum`
- `jwt`
- `passport`

If `X-Auth-Method` is omitted, Sanctum is used by default.

Example:

```http
GET /api/tasks
Authorization: Bearer <token>
X-Auth-Method: jwt
```

### Passport setup

Passport uses OAuth2 keys stored in the backend storage directory:

```text
app/backend/storage/oauth-private.key
app/backend/storage/oauth-public.key
```

These keys are environment-specific and should not be committed to the repository.

Passport OAuth clients are stored in the application's `oauth_clients` table.

For manual API testing with the password grant, create a password-grant OAuth client using the Passport Artisan command and keep its client ID and secret available for your requests.

The OAuth token endpoint is:

```text
POST /api/oauth/token
```

The endpoint accepts OAuth2 parameters such as:

```json
{
  "grant_type": "password",
  "client_id": "<client-id>",
  "client_secret": "<client-secret>",
  "username": "john@example.com",
  "password": "password123",
  "scope": ""
}
```

The returned `access_token` can then be used with:

```http
Authorization: Bearer <access-token>
X-Auth-Method: passport
```

The project tests do not depend on a manually created OAuth client. Passport clients required by tests are created inside the test database.


### Register

```text
POST /api/register
```

Example request:

```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

### Login

```text
POST /api/login
```

Example request:

```json
{
  "email": "john@example.com",
  "password": "password123"
}
```

The API returns a Sanctum bearer token.

JWT login uses the dedicated endpoint:

```text
POST /api/login/jwt
```

Example request:

```json
{
  "email": "john@example.com",
  "password": "password123"
}
```

The API returns a JWT bearer token.

For Passport login, the frontend first requests the password-grant OAuth client:

```text
GET /api/oauth/client
```

and then exchanges the credentials at `POST /api/oauth/token`.

Authenticated requests use:

```text
Authorization: Bearer <token>
```

### Current user

```text
GET /api/user
```

Requires authentication.

### Logout

```text
POST /api/logout
```

Requires authentication. The behavior depends on the authentication method sent via `X-Auth-Method`:

- `sanctum` — deletes the current Sanctum access token
- `jwt` — invalidates the current JWT
- `passport` — revokes the current Passport access token

## API

All protected API routes require a valid bearer token authenticated through the selected authentication method.

### Tasks

```text
GET    /api/tasks
POST   /api/tasks
GET    /api/tasks/{task}
PUT    /api/tasks/{task}
DELETE /api/tasks/{task}
```

The task list supports filtering:

```text
GET /api/tasks?status=completed
GET /api/tasks?priority=high
GET /api/tasks?status=completed&priority=high
```

Pagination is provided by Laravel's paginator.

Example:

```text
GET /api/tasks?page=2
```

### Task creation

A Task belongs to a Project.

Example:

```json
{
  "project_id": 1,
  "title": "Build login page",
  "description": "Implement the login screen",
  "status": "pending",
  "priority": "high",
  "due_date": "2026-09-10"
}
```

The API verifies that the selected Project belongs to the authenticated user.

Clients cannot set `user_id` to control task ownership.

### Projects

```text
GET    /api/projects
POST   /api/projects
GET    /api/projects/{project}
PUT    /api/projects/{project}
DELETE /api/projects/{project}
```

Projects are owned by their creating user.

### Project creation

Example:

```json
{
  "name": "Website Redesign",
  "description": "Redesign the company website"
}
```

### Comments

Comments are associated with Tasks.

```text
GET  /api/tasks/{task}/comments
POST /api/tasks/{task}/comments
```

Example:

```json
{
  "body": "Looks good to me."
}
```

A user can only access comments through Tasks they are authorized to view.

## Validation

Laravel Form Requests are used for request validation.

Examples include:

* required fields
* string validation
* maximum lengths
* allowed status values
* allowed priority values
* date validation
* email validation
* unique email validation
* existing Project validation
* Project ownership validation

Validation failures return HTTP `422`.

The application currently uses Polish Laravel validation messages in:

```text
app/backend/lang/pl/validation.php
```

The Nuxt frontend displays field-specific validation errors returned by the API.

## Authorization

Authorization is handled using Laravel Policies.

### TaskPolicy

Users can view, update, or delete Tasks only when they own the Task.

### ProjectPolicy

Users can view, update, or delete Projects only when they own the Project.

### Project ownership during Task creation/update

A particularly important authorization rule is that users cannot associate Tasks with Projects belonging to another user.

```text
User A
 ├── Project A
 └── Task A

User B
 └── Project B
```

User A cannot create or move a Task to Project B.

This ownership rule is enforced on the backend rather than relying on frontend behavior.

## API Error Handling

The API returns JSON responses for common errors.

### Unauthenticated

```text
401
```

```json
{
  "success": false,
  "message": "Unauthenticated."
}
```

### Unauthorized

```text
403
```

```json
{
  "success": false,
  "message": "This action is unauthorized."
}
```

### Not Found

```text
404
```

```json
{
  "success": false,
  "message": "Resource not found."
}
```

This keeps the API behavior predictable for the Nuxt frontend.

## Frontend

The Nuxt application contains separate pages for authentication, Tasks, and Projects.

```text
app/frontend/app/pages/login.vue
app/frontend/app/pages/tasks.vue
app/frontend/app/pages/projects.vue
```

Shared navigation is implemented as:

```text
app/frontend/app/components/AppNav.vue
```

Shared Task-related TypeScript definitions are stored in:

```text
app/frontend/app/types/task.ts
```

Global styling is stored in:

```text
app/frontend/app/assets/css/main.css
```

### Authentication state

The frontend stores the bearer token and the selected authentication method in browser `localStorage`.

The authentication composable is:

```text
app/frontend/app/composables/useAuth.ts
```

It handles:

* storing the token and selected method
* retrieving the token and selected method
* removing the token and selected method
* logout

The login page (`app/frontend/app/pages/login.vue`) lets the user pick the authentication method (Sanctum, JWT, or Passport).

API requests centralize their headers through:

```text
app/frontend/app/composables/useApi.ts
```

which sets `Accept`, `X-Auth-Method`, and the `Authorization` bearer header on every request.

A client plugin (`app/frontend/app/plugins/auth-header.ts`) restores the stored authentication method so subsequent requests keep using the same method.

Browser-only APIs are guarded so they are not accessed during Nuxt server-side rendering.

## Tasks UI

The Tasks page currently supports:

* loading authenticated Tasks
* creating Tasks
* editing Tasks
* deleting Tasks
* selecting a Project
* filtering by status
* filtering by priority
* pagination
* loading comments
* adding comments
* displaying validation errors
* basic authentication redirects

Task actions are separated from task information visually, with editing/deletion controls placed on the right side of a task row and comments kept with the task content.

## Projects UI

The Projects page currently supports:

* loading Projects
* creating Projects
* editing Projects
* deleting Projects
* displaying validation errors
* navigation to Tasks
* logout

## Styling

The frontend uses a deliberately minimal global CSS layer.

The styling focuses on:

* readable typography
* centered content
* full-width application navigation
* consistent forms
* consistent buttons
* simple cards/rows
* basic responsive behavior
* clear validation/error messages

No external component library is required.

## Testing

Laravel feature tests cover the main API behavior.

Run the complete test suite with:

```bash
./bin/artisan test
```

Run a specific test class:

```bash
./bin/artisan test --filter=TaskApiTest
./bin/artisan test --filter=ProjectApiTest
./bin/artisan test --filter=CommentApiTest
./bin/artisan test --filter=MultiAuthTest
```

Current test coverage includes:

### Tasks

* listing a user's Tasks
* creating Tasks
* viewing Tasks
* updating Tasks
* deleting Tasks
* authorization between users
* validation
* authentication
* Project ownership during Task creation
* Project ownership during Task updates

### Projects

* listing Projects
* creating Projects
* viewing Projects
* updating Projects
* deleting Projects
* authorization between users
* validation

### Comments

* listing comments
* creating comments
* task ownership authorization
* validation
* unauthenticated access

### Multi-authentication

`MultiAuthTest` verifies authentication through all three supported mechanisms:

* Sanctum authentication with an explicit `X-Auth-Method: sanctum` header
* JWT authentication with an explicit `X-Auth-Method: jwt` header
* Passport authentication with an explicit `X-Auth-Method: passport` header
* Sanctum as the default when `X-Auth-Method` is not provided
* `401 Unauthorized` for an invalid `X-Auth-Method`
* logout deletes the current Sanctum token
* logout invalidates the current JWT
* logout revokes the current Passport token

The Passport test creates its OAuth client and obtains an access token through the OAuth2 password grant, keeping the test independent of manually configured OAuth clients.

Tests use Laravel's `RefreshDatabase` and Sanctum's `actingAs()` helpers.


## Project Structure

Relevant repository structure:

```text
.
├── .env
├── .env.example
├── README.md
├── app
│   ├── backend
│   │   ├── app
│   │   │   ├── Http
│   │   │   ├── Models
│   │   │   └── Policies
│   │   ├── database
│   │   ├── routes
│   │   └── tests
│   │
│   └── frontend
│       ├── app
│       │   ├── assets
│       │   ├── components
│       │   ├── composables
│       │   ├── pages
│       │   └── types
│       └── nuxt.config.ts
│
├── bin
│   └── artisan
│
└── docker
    ├── docker-compose.yml
    ├── configs
    │   └── nginx
    │       └── default.conf
    ├── image-backend
    │   └── Dockerfile
    └── image-frontend
        └── Dockerfile
```

All Docker-related configuration is kept under the `docker/` directory.

## Design Decisions

### Keep ownership on the server

Task and Project ownership is derived from the authenticated user.

The frontend cannot choose a different `user_id` to transfer ownership.

### Use Policies for resource authorization

Task and Project authorization rules are implemented with Laravel Policies rather than duplicated throughout controllers.

This keeps ownership rules centralized and reusable.

### Use Form Requests for validation

Request validation is kept out of controllers where practical.

This makes controllers easier to read and keeps validation rules close to the request being validated.

### Use API Resources

Laravel API Resources provide a consistent response structure and prevent exposing raw Eloquent models directly as the public API contract.

### Use relationship-based ownership checks

When assigning a Task to a Project, the Project is looked up through the authenticated user's Projects rather than simply checking that the Project ID exists.

This prevents a user from attaching data to another user's resources.

### Use SQLite for development and tests

SQLite keeps local development and automated testing simple and fast.

The application is also configured so it can use MySQL when required.

### Avoid unnecessary abstractions

The project deliberately does not introduce Repository or Service layers everywhere.

Abstractions should solve a real problem rather than exist only for structural purposes.

### Transactions

Database transactions are not used simply for demonstration purposes.

A transaction is appropriate when a single application operation performs multiple related writes that must succeed or fail together. The current CRUD operations do not require an artificial transaction.

SQLite supports transactions, so the development database does not prevent using them when a real use case appears.

## Development Commands

Control the Docker application with `bin/run.sh`:

```bash
./bin/run.sh        # start
./bin/run.sh build  # rebuild
./bin/run.sh stop   # stop
```

The Artisan Helper section above documents migration, route, test, and shell commands.

## Future Improvements

Possible next improvements include:

* Role-based access control
* Project member management
* More granular permissions
* Comment editing and deletion
* More comprehensive frontend tests
* Improved production deployment configuration
* Better confirmation flows for destructive actions
* More reusable frontend components
* Additional API documentation
* CI/CD integration

These are intentionally outside the scope of the current project.

## Learning Goals

This project is intended as a practical learning exercise for:

* Laravel API development
* Eloquent relationships
* Form Requests
* Policies and authorization
* API Resources
* Sanctum authentication
* Pagination and filtering
* Validation
* Feature testing
* Vue/Nuxt application development
* TypeScript
* API integration
* Docker-based development
* Frontend state management
* Separation between frontend and backend responsibilities

The implementation intentionally favors straightforward Laravel and Vue patterns that are easy to understand, test, and maintain.

## License

This project was created as an educational project for learning Laravel, Vue, and Nuxt.
