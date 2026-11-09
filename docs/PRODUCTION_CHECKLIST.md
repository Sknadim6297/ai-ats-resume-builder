# Production Configuration Checklist

Use this before any public deployment. Do **not** copy secrets into tickets or chat.

## Application

| Variable | Production value |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://your-domain.example` |
| `APP_KEY` | Unique `php artisan key:generate` value |

## Session / cookies

| Variable | Production value |
|---|---|
| `SESSION_DRIVER` | `database` (or `redis` if available) |
| `SESSION_LIFETIME` | `120` (or policy-required value) |
| `SESSION_SECURE_COOKIE` | `true` |
| `SESSION_SAME_SITE` | `lax` (or `strict` if compatible) |
| `SESSION_HTTP_ONLY` | `true` (default) |
| `SESSION_ENCRYPT` | optional `true` |

## Database

Prefer MySQL/MariaDB in production (not the local SQLite file).

| Variable | Notes |
|---|---|
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Dedicated schema; strong password |
| Backups | Automated daily dumps + restore drill |

## AI / Grok

| Variable | Notes |
|---|---|
| `AI_PROVIDER` | `grok` only when live key+model are ready |
| `XAI_API_KEY` | Server-side only; never `VITE_*` |
| `XAI_MODEL` | Explicit current xAI model id |
| `XAI_BASE_URL` | `https://api.x.ai/v1` unless docs say otherwise |
| `XAI_TIMEOUT` | `45` (adjust for host limits) |
| `XAI_MAX_RETRIES` | `0` or `1` |
| `AI_JD_RATE_LIMIT` / resume throttle | Keep route + app limits enabled |

## Admin bootstrap

| Variable | Notes |
|---|---|
| `ADMIN_EMAIL` | Non-default address |
| `ADMIN_PASSWORD` | Strong unique password; seeder refuses default `password` when `APP_ENV=production` |

Public registration remains disabled.

## Cache / queue / logging

| Area | Recommendation |
|---|---|
| Cache | `file` or `redis` |
| Queue | `sync` is acceptable on shared hosting; use a worker only if the host supports it |
| Logging | `stack` / `single` or `stderr`; `LOG_LEVEL=error` or `warning` |
| `storage/` + `bootstrap/cache/` | Writable by the PHP user |

## HTTPS & web server

- TLS certificate installed and forced (HSTS optional)
- Document root = `public/`
- Deny web access to `.env`, `storage/`, `vendor/`, `tests/`
- Run `php artisan config:cache`, `route:cache`, `view:cache` after deploy
- Build frontend assets (`npm ci && npm run build`) on CI or deploy host

## Health & rollback

- Health route: `GET /up`
- Keep previous release + DB dump for rollback
- Rotate `XAI_API_KEY` if ever exposed
