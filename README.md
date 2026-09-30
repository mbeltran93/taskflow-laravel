# TaskFlow API - Laravel

A small REST API for managing projects and tasks (a reduced Trello/Jira), built with
**Laravel 13**, **MySQL** and **Laravel Sanctum** token authentication.

This is one of several TaskFlow backends in a portfolio series that all model the same
domain and expose the same API contract, each implemented with a different stack.

## Domain

- **User** - id, name, email, password (hashed)
- **Project** - id, name, description, owner (a User)
- **Task** - id, title, description, status (`TODO` \| `IN_PROGRESS` \| `DONE`), project,
  assignee (a User, optional), due date (optional)

## Tech stack

- PHP 8.3 / Laravel 13
- MySQL 8
- Laravel Sanctum (personal access tokens, bearer auth)
- Eloquent ORM, Form Requests, API Resources, Policies
- PHPUnit feature tests
- Docker + docker-compose

## Running the project

You only need Docker installed.

```bash
docker compose up --build
```

That single command will:

1. Build the PHP application image (Alpine, PHP 8.3, the MySQL PDO driver).
2. Start MySQL and wait until it reports healthy.
3. Run the database migrations.
4. Seed a small demo dataset (only the first time - the entrypoint script skips
   seeding if the `users` table already has rows, so restarting the stack is safe).
5. Serve the API at **http://localhost:8000**.

MySQL is also published on `localhost:3307` (mapped from its container port `3306`) if
you want to inspect the database with a client.

To stop everything: `docker compose down`. To also wipe the database volume:
`docker compose down -v`.

### Demo users (seeded)

| Email | Password | Role |
|---|---|---|
| `alice@taskflow.test` | `password` | Owns the "Website Revamp" project |
| `bob@taskflow.test` | `password` | Owns the "Mobile App" project, assignee on some tasks |
| `carol@taskflow.test` | `password` | Assignee on some tasks |

### Running without Docker

If you already have PHP 8.3+, Composer and a MySQL server available:

```bash
composer install
cp .env.example .env
php artisan key:generate
# point DB_HOST/DB_DATABASE/DB_USERNAME/DB_PASSWORD in .env at your MySQL server
php artisan migrate --seed
php artisan serve
```

## API contract

All responses are JSON. Single resources are returned as flat objects; list endpoints
return a Laravel paginator shape: `{ "data": [...], "links": {...}, "meta": {...} }`.

Write operations (`POST` / `PUT` / `PATCH` / `DELETE`) require a Sanctum bearer token:

```
Authorization: Bearer <token>
```

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| POST | `/api/auth/login` | - | Log in with `email` + `password`, returns `{ "token": "..." }` |
| POST | `/api/auth/logout` | required | Revoke the token used for the request |
| GET | `/api/auth/me` | required | The authenticated user (bonus, not part of the shared contract) |
| GET | `/api/users` | - | List users (paginated) |
| GET | `/api/users/{id}` | - | Show a single user |
| GET | `/api/projects` | - | List projects (paginated) |
| POST | `/api/projects` | required | Create a project (caller becomes its owner) |
| GET | `/api/projects/{id}` | - | Show a single project |
| PUT | `/api/projects/{id}` | required, owner only | Update a project |
| DELETE | `/api/projects/{id}` | required, owner only | Delete a project (cascades to its tasks) |
| GET | `/api/tasks?projectId=&status=` | - | List tasks, optionally filtered |
| POST | `/api/tasks` | required | Create a task |
| GET | `/api/tasks/{id}` | - | Show a single task |
| PUT | `/api/tasks/{id}` | required, project owner or assignee | Update a task |
| DELETE | `/api/tasks/{id}` | required, project owner only | Delete a task |
| PATCH | `/api/tasks/{id}/status` | required, project owner or assignee | Update only a task's status |

### Authorization rules

- Reading (`GET`) is public - no token required.
- Creating a project or task only requires being authenticated.
- Updating/deleting a **project** is restricted to its owner.
- Updating a **task** (including its status) is allowed for the project's owner or the
  task's assignee.
- Deleting a **task** is restricted to the project's owner.

### Example flow (curl)

```bash
# 1. Log in
TOKEN=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"alice@taskflow.test","password":"password"}' | jq -r .token)

# 2. Create a project
PROJECT_ID=$(curl -s -X POST http://localhost:8000/api/projects \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"name":"New project","description":"Created from curl"}' | jq -r .id)

# 3. Create a task in that project
curl -s -X POST http://localhost:8000/api/tasks \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d "{\"title\":\"First task\",\"project_id\":$PROJECT_ID}"

# 4. Update its status
curl -s -X PATCH http://localhost:8000/api/tasks/1/status \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"status":"IN_PROGRESS"}'
```

## Tests

Feature tests cover login, project CRUD + ownership permissions, task CRUD + filtering
+ assignment permissions, and user listing. They run against an in-memory SQLite
database (see `phpunit.xml`), so no MySQL container is needed to run them.

```bash
# Inside the running app container
docker compose exec app php artisan test

# Or, without Docker, with a local PHP/Composer install
composer install
php artisan test
```

## Project structure highlights

- `app/Models` - `User`, `Project`, `Task` Eloquent models and their relationships.
- `app/Enums/TaskStatus.php` - the `TODO` / `IN_PROGRESS` / `DONE` backed enum.
- `app/Http/Requests` - Form Requests validating every write endpoint's input.
- `app/Http/Resources` - API Resources shaping every JSON response.
- `app/Http/Controllers/Api` - one controller per resource (`Auth`, `User`, `Project`,
  `Task`).
- `app/Policies` - `ProjectPolicy` and `TaskPolicy` implementing the authorization rules
  above.
- `database/migrations`, `database/factories`, `database/seeders` - schema and demo data.
- `tests/Feature` - PHPUnit feature tests exercising the endpoints above.
- `docker/entrypoint.sh` - waits for MySQL, runs migrations, seeds on first boot.

## Known limitations

- There is no user registration endpoint - the shared API contract only specifies
  login, so accounts only come from the seeder. Adding a `POST /api/users` endpoint
  would be a small, isolated addition (a `StoreUserRequest` + controller action).
- Tokens never expire and are not rotated; there is no refresh-token flow (Sanctum
  personal access tokens, kept deliberately simple for a portfolio project).
- No rate limiting beyond Laravel's default API throttle middleware.
- The Docker image is built with the application baked in (no source bind-mount), so
  code changes require `docker compose up --build` rather than hot-reloading.
- Pagination uses Laravel's default page-based paginator; there is no cursor
  pagination or configurable page size via query parameters.
