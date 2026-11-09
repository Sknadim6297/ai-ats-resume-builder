# Deployment Guide — AI ATS Resume Builder

This guide prepares a production-ready deployment. **Do not deploy until explicitly approved.**

## 1. Hosting prerequisites

- PHP **8.2+** with extensions: `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `mbstring`, `openssl`, `pdo`, `pdo_mysql` (or `pdo_sqlite` for local only), `tokenizer`, `xml`, `zip`
- Composer 2.x
- Node.js 20+ and npm (for asset build)
- MySQL 8+ / MariaDB 10.6+ (recommended for production)
- HTTPS-capable web server (Apache/Nginx/cPanel)
- Writable `storage/` and `bootstrap/cache/`

Shared hosting / cPanel notes:

- Point the domain (or subdomain) document root to `/public`
- Prefer SSH/Composer if available; otherwise build assets locally and upload `public/build`
- Long-running queue workers are often unavailable — keep `QUEUE_CONNECTION=sync`
- Confirm `max_execution_time` can cover Grok timeouts (~45–60s) or lower AI timeouts

## 2. Install application code

```bash
# On the server (example)
cd /path/to/ai-ats-resume-builder
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Never commit `.env`.

## 3. Environment

Set production values from `docs/PRODUCTION_CHECKLIST.md`:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://...`
- `SESSION_SECURE_COOKIE=true`
- MySQL credentials
- Grok credentials (`AI_PROVIDER`, `XAI_API_KEY`, `XAI_MODEL`)
- Strong `ADMIN_PASSWORD` before first seed

## 4. Database

```bash
php artisan migrate --force
php artisan db:seed --force   # only if no users exist; requires non-default ADMIN_PASSWORD in production
```

Do **not** run `migrate:fresh` / `db:wipe` on production.

## 5. Frontend assets

On a machine with Node:

```bash
npm ci
npm run build
```

Deploy the generated `public/build` directory with the app.

## 6. Storage & permissions

```bash
php artisan storage:link
# Ensure the PHP user can write:
# storage/ framework/ cache/ logs/ sessions/ app/
# bootstrap/cache/
```

PDF/DOCX downloads are streamed and must not be stored under a public URL.

## 7. Optimize Laravel

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache   # if applicable
```

After changing `.env`, re-run `config:cache`.

## 8. Web server document root

- Root must be `public/index.php`
- Block access to application source outside `public/`
- Force HTTPS redirects

### Apache (example)

```apache
DocumentRoot /var/www/ai-ats-resume-builder/public
<Directory /var/www/ai-ats-resume-builder/public>
    AllowOverride All
    Require all granted
</Directory>
```

### Nginx (example)

```nginx
root /var/www/ai-ats-resume-builder/public;
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

## 9. Post-deploy health checks

1. `GET /up` returns success
2. Login page loads
3. Admin can sign in
4. Dashboard loads with empty/real stats
5. Job Analyzer page loads (do not burn paid AI calls during smoke unless intended)
6. `php artisan about` shows `environment=production` and `debug=false`

## 10. Backup & rollback

Before each release:

1. Dump the database
2. Archive the previous release directory (or keep the prior deploy slot)
3. Record the deployed commit/tag and asset build hash

Rollback:

1. Restore previous code release
2. Restore DB dump if migrations are incompatible
3. Re-run `config:cache` / `route:cache`
4. Verify `/up` and login

## 11. Test isolation reminder (local/CI)

PHPUnit is hard-isolated to SQLite `:memory:` via `phpunit.xml` and guarded in `tests/TestCase.php`.

Never point tests at `database/database.sqlite` or production MySQL.

```bash
php artisan test
npm run build
composer audit
npm audit --omit=dev
```

## 12. Explicit non-actions

- Do not deploy from this document alone without user approval
- Do not run paid Grok smoke tests without authorization
- Do not disable CSRF, rate limits, or grounding validators for “convenience”
