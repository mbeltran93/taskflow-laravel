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

- PHP 8.5 / Laravel 13
- MySQL 8
- Laravel Sanctum (personal access tokens, bearer auth)
- Eloquent ORM, Form Requests, API Resources, Policies
- PHPUnit feature + unit tests
- Docker + docker-compose
- Postman collection (`postman_collection.json`) for manual/automated API testing

## Running the project

You only need Docker installed.

```bash
docker compose up --build
```

That single command will:

1. Build the PHP application image (Alpine, PHP 8.5, the MySQL PDO driver).
2. Start MySQL and wait until it reports healthy.
3. Run the database migrations.
4. Seed a small demo dataset (only the first time - the entrypoint script skips
   seeding if the `users` table already has rows, so restarting the stack is safe).
5. Serve the API at **http://localhost:8000**.

MySQL is also published on `localhost:3309` (mapped from its container port `3306`) if
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

If you already have PHP 8.4+, Composer and a MySQL server available:

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

### Postman collection

`postman_collection.json` (repo root) exercises the same flow as the curl example above,
plus the two authorization failure cases, as a ready-to-run Postman collection:

1. Log in as Alice (demo owner) - the returned token is saved into a **collection
   variable** (`token`) by a test script, so you never have to copy/paste it.
2. Create a project (using `{{token}}`) - its id is saved into `{{project_id}}`.
3. Create a task in that project - its id is saved into `{{task_id}}`.
4. Change the task's status to `IN_PROGRESS`.
5. Attempt a write with **no token** - expects `401`.
6. Log in as Bob (a different demo user) - his token is saved into `{{bob_token}}`.
7. Attempt to update Alice's project **as Bob** - expects `403`.
8. (Cleanup) delete the task and project created above, so the collection can be
   re-run from scratch any number of times.

**To import it:** open Postman -> **Import** -> select `postman_collection.json`. No
separate environment file is needed - `base_url` (defaults to `http://localhost:8000`)
and every token/id are collection variables, editable from the collection's
**Variables** tab if you need to point it at a different host. Run the whole flow at
once with the **Collection Runner**, or step through requests 1-7 manually in order
(each request after the first one depends on a variable set by an earlier request).

It can also be run headlessly with [Newman](https://www.npmjs.com/package/newman):

```bash
npx newman run postman_collection.json
```

## Tests

- `tests/Feature` - HTTP-level tests covering login, project CRUD + ownership
  permissions, task CRUD + filtering + assignment permissions, and user listing. They
  run against an in-memory SQLite database (see `phpunit.xml`), so no MySQL container
  is needed to run them.
- `tests/Unit/Policies` - plain PHP unit tests for `ProjectPolicy` and `TaskPolicy`
  that instantiate `User`/`Project`/`Task` models directly (no database, no HTTP) and
  assert on the policy methods' return values for every allow/deny branch.

```bash
# Inside the running app container
docker compose exec app php artisan test

# Or, without Docker, with a local PHP/Composer install
composer install
php artisan test
```

> **Why `phpunit.xml` sets both `<env>` and `<server>` overrides, each `force="true"`:**
> the app container sets `DB_CONNECTION=mysql` (and friends) as real OS environment
> variables (see `docker-compose.yml`), which PHP copies into `$_SERVER` at startup.
> PHPUnit's `<env>` only ever updates `putenv()`/`$_ENV`, never `$_SERVER` - and
> Laravel's `env()` helper reads `$_SERVER` first. Without matching `<server>` entries,
> `docker compose exec app php artisan test` would silently run the test suite's
> `RefreshDatabase` migrations against the real MySQL database instead of the intended
> in-memory SQLite one, wiping the demo data. Both overrides are in place; verified by
> seeding the container's MySQL database, running `docker compose exec app php artisan
> test`, and confirming the row counts are unchanged afterwards.

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
- `tests/Unit/Policies` - plain PHP unit tests for the policies, with no HTTP/database
  involved.
- `docker/entrypoint.sh` - waits for MySQL, runs migrations, seeds on first boot.
- `postman_collection.json` - a ready-to-run Postman collection covering the same flow
  (see "Postman collection" above).

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
