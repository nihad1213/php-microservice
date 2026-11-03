# PHP Microservices (Core PHP)

A small microservices system written in plain PHP 8.3 without a framework. It runs in Docker with nginx, php-fpm, MySQL and phpMyAdmin.

## Services

| Service | Port | Database | Job |
|---|---|---|---|
| Gateway | 8000 | none | Single entry point. Checks the JWT, rate limits, forwards requests |
| Auth | 8001 | auth_db | Register, login, JWT |
| Tasks | 8002 | tasks_db | Task CRUD per user |
| Notifications | 8003 | notify_db | Stores events created by the Task service |
| phpMyAdmin | 8081 | | Database UI (login: root / root) |

Use the Gateway (port 8000) for normal work. Ports 8001 to 8003 and 8081 are only open on localhost.

```
Client -> Gateway :8000 -> Auth :8001
                        -> Tasks :8002 -> Notifications :8003
                        -> Notifications :8003
```

How a request flows:
1. The client sends a request with `Authorization: Bearer <token>` to the Gateway.
2. The Gateway asks Auth to verify the token.
3. The Gateway adds `X-User-Id` and `X-Internal-Key` and forwards the request to Tasks or Notifications.
4. When a task is created or completed, Tasks calls Notifications. If Notifications is down, the task still succeeds and a warning is logged.

## Project structure

```
.
├── docker-compose.yml
├── docker/            nginx config, php Dockerfile, mysql init.sql
├── scripts/setup.sh   first-time setup
├── shared/            Composer package php-micro/shared (namespace Core\)
│   └── core/          Config, Database, Http, Validation, Logging, Exceptions
└── services/
    ├── gateway/
    ├── auth/
    ├── tasks/
    └── notifications/
```

Every service has its own `.env`, `composer.json`, `public/index.php`, `src/` and `storage/logs/app.log`. Auth, Tasks and Notifications also have `migrations/` and `bin/migrate.php`.

## Requirements

- Docker with the Compose plugin
- Linux, macOS or WSL

## Setup

```bash
./scripts/setup.sh
```

This builds the images, creates each `.env` from `.env.example`, and runs `composer install` in every service.

Add these lines to `services/gateway/.env`, using the same `INTERNAL_KEY` as in Tasks and Notifications:

```
INTERNAL_KEY=change_me_internal_key
ALLOWED_ORIGINS=http://localhost:3000
RATE_LIMIT=120
AUTH_RATE_LIMIT=10
UPSTREAM_TIMEOUT=10
```

Start everything and create the tables:

```bash
docker compose up -d
docker compose exec auth php bin/migrate.php
docker compose exec tasks php bin/migrate.php
docker compose exec notifications php bin/migrate.php
```

Check it works:

```bash
curl http://localhost:8000/health/services
```

## Gateway endpoints

| Method | Path | Auth | Description |
|---|---|---|---|
| GET | /health | no | Gateway status |
| GET | /health/services | no | Status of all services |
| POST | /auth/register | no | Body: email, password |
| POST | /auth/login | no | Returns a token |
| GET | /auth/me | yes | Current user |
| GET | /tasks | yes | List your tasks (status, page, limit) |
| POST | /tasks | yes | Create a task |
| GET | /tasks/{id} | yes | Get a task |
| PUT | /tasks/{id} | yes | Update a task |
| PATCH | /tasks/{id}/complete | yes | Mark a task done |
| DELETE | /tasks/{id} | yes | Delete a task |
| GET | /notifications | yes | List your notifications |
| PATCH | /notifications/{id}/read | yes | Mark as read |

Quick test:

```bash
curl -X POST http://localhost:8000/auth/register -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password123"}'

curl -X POST http://localhost:8000/auth/login -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password123"}'

TOKEN=paste_the_token_here

curl -X POST http://localhost:8000/tasks -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" -d '{"title":"Buy milk"}'

curl http://localhost:8000/notifications -H "Authorization: Bearer $TOKEN"
```

A Postman collection is included: `PHP-Task-Microservice.postman_collection.json`. Import it and use the Gateway folder.

## Error format

```json
{ "error": "Validation failed", "code": 422, "fields": { "title": "The title field is required." } }
```

Common codes: 400, 401, 404, 405, 413, 422, 429, 502, 504.

## Logs

```bash
tail -f services/gateway/storage/logs/app.log
docker compose logs -f gateway
```

## Useful commands

```bash
docker compose ps
docker compose logs gateway --tail=30
docker compose restart tasks
docker compose down
docker compose down -v
```

`down -v` also deletes the database data.

## Troubleshooting

- **Class not found:** a file is missing or in the wrong folder. Compare `src/` with the original, then run `docker compose run --rm --no-deps -e COMPOSER_HOME=/tmp/composer <service> composer dump-autoload -o -d /var/www/html` and restart that service.
- **Permission denied on docker.sock:** the setup script uses `sudo docker` automatically. Or add your user to the docker group.
- **502 from the Gateway:** a service is down. Check `docker compose ps` and `/health/services`.
- **401 with a valid token:** make sure `INTERNAL_KEY` matches in Gateway, Tasks and Notifications.

## Security notes

- The Gateway ignores any `X-User-Id` or `X-Internal-Key` sent by clients.
- `/events` and `/auth/verify` are not reachable through the Gateway.
- Change `JWT_SECRET`, `INTERNAL_KEY` and the database password before any real deployment.

## Known limitations

- No refresh tokens or logout.
- Missed notifications are not retried.
- Rate limiting is per IP and can be inaccurate behind Docker networking.
- No automated test suite yet.