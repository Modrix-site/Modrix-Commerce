# Production Deployment

This guide targets a conventional Linux server (VPS or bare metal) running Nginx, PHP-FPM 8.3/8.4, MySQL 8 and Redis. Kubernetes is not required. Every command assumes the application lives in `/var/www/ecomodular`, is owned by a `deploy` user, and runs as `www-data`.

Related guides: [backup and restore](../operations/backup-and-restore.md), [monitoring](../operations/monitoring.md), [troubleshooting](../operations/troubleshooting.md), [Stripe](../payments/stripe.md), [marketplace and licensing](../marketplace/licensing.md).

## 1. Server packages

- PHP 8.3 or 8.4 with FPM and these extensions: `bcmath ctype curl dom fileinfo filter intl mbstring openssl pdo_mysql session tokenizer xml zip gd redis` (`sodium` is recommended; a pure-PHP fallback is bundled).
- Composer 2, and Node 22+ to build assets (or build them in CI and upload `public/build`).
- MySQL 8.0+ (8.4 LTS recommended) with the `mysqldump` and `mysql` clients (used by `ecomodular:backup`), Redis 7, Nginx, and a TLS certificate (for example Let's Encrypt).

## 2. Database

```sql
CREATE DATABASE ecomodular CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ecomodular'@'localhost' IDENTIFIED BY '<strong unique password>';
GRANT ALL PRIVILEGES ON ecomodular.* TO 'ecomodular'@'localhost';
```

Keep MySQL and Redis bound to `127.0.0.1` or a private network. They must never be reachable from the internet.

## 3. Application

```bash
cd /var/www/ecomodular
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

### Environment variables

| Variable                                            | Production value                 | Notes                                                                        |
| --------------------------------------------------- | -------------------------------- | ---------------------------------------------------------------------------- |
| `APP_ENV`                                           | `production`                     |                                                                              |
| `APP_DEBUG`                                         | `false`                          | `true` exposes secrets on error pages; the health check fails on it          |
| `APP_KEY`                                           | generated                        | Decrypts stored credentials and 2FA secrets. Keep a copy in a secret manager |
| `APP_URL`                                           | `https://shop.example.com`       | Must be HTTPS                                                                |
| `DB_CONNECTION`, `DB_*`                             | MySQL credentials                |                                                                              |
| `CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER` | `redis`                          |                                                                              |
| `SESSION_SECURE_COOKIE`                             | `true`                           |                                                                              |
| `AUTH_PASSWORD_TIMEOUT`                             | `1800` or lower                  | Password confirmation window for sensitive admin actions                     |
| `MAIL_*`                                            | your SMTP provider               | Order and account emails                                                     |
| `LOG_STACK`, `LOG_LEVEL`, `LOG_DAILY_DAYS`          | `daily`, `warning`, `14`         | Rotated, size-bounded logs                                                   |
| `ECOMODULAR_MEDIA_DISK`                             | `public` or `s3`                 | With `s3`, set the `AWS_*` variables and `AWS_URL` for the CDN               |
| `ECOMODULAR_CSP`                                    | `enforce`                        | `report-only` while testing a custom CDN or integration                      |
| `ECOMODULAR_CSP_IMG_SOURCES`                        | empty                            | Extra image origins, comma-separated                                         |
| `ECOMODULAR_WEB_INSTALLER`                          | `false` after installing via CLI | The `/install` pages return 404                                              |
| `ECOMODULAR_HEALTH_TOKEN`                           | long random string               | Unlocks per-check details on `/health`                                       |
| `ECOMODULAR_BACKUP_PASSWORD`                        | long random string               | AES-256 for backup archives; store it in the secret manager too              |
| `ECOMODULAR_BACKUP_SCHEDULE`, `_KEEP`               | `02:30`, `7`                     | Daily backup time and retention                                              |
| `ECOMODULAR_MARKETPLACE_TRUSTED_KEYS`               | the publisher's public key       | Only needed to install marketplace packages                                  |

Stripe keys are entered in Admin → Payments and stored encrypted; they never go in `.env`.

### Build, migrate, install

```bash
npm ci && npm run build           # or deploy a prebuilt public/build
php artisan migrate --force
ECOMODULAR_OWNER_PASSWORD='<owner password>' php artisan ecomodular:install --no-interaction \
    --store-name="My Store" --name="Owner Name" --email=owner@example.com --currency=USD --timezone=Europe/Madrid
php artisan storage:link
php artisan optimize               # config, route, event and view caches
```

`ecomodular:install` checks requirements, creates the owner and locks the installer; it cannot run twice. Run it interactively instead to be prompted. Never pass the password as an argument, because it would end up in shell history.

The web installer (`/install`) is an alternative for hosts without shell access. It requires the one-time token from `storage/app/private/installer.token`, which expires after 24 hours. Set `ECOMODULAR_WEB_INSTALLER=false` when you install from the CLI.

## 4. File ownership and permissions

```bash
chown -R deploy:www-data /var/www/ecomodular
find /var/www/ecomodular -type f -exec chmod 640 {} \;
find /var/www/ecomodular -type d -exec chmod 750 {} \;
chmod -R ug+rwX storage bootstrap/cache
chmod 600 .env
```

The PHP-FPM user needs write access to `storage/` and `bootstrap/cache/` only. Installing modules and themes from the marketplace also needs write access to `modules/` and `themes/`. Grant it only if you use the marketplace client:

```bash
chmod -R g+w modules themes
```

## 5. Nginx and HTTPS

Use `docker/nginx/default.conf` as the template:

- The document root is `public/`, only `index.php` executes, and dotfiles are denied.
- Build assets are cached for a year, and uploaded media under `/storage/` is served with `nosniff` and a sandbox CSP.
- Application security headers (CSP with nonces, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, HSTS) are sent by the application. **Do not add them in Nginx too**, because conflicting duplicates can make browsers ignore them.

Add TLS and redirect HTTP to HTTPS:

```nginx
server {
    listen 80;
    server_name shop.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    http2 on;
    server_name shop.example.com;
    ssl_certificate     /etc/letsencrypt/live/shop.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/shop.example.com/privkey.pem;
    # … the rest of docker/nginx/default.conf, with fastcgi_pass unix:/run/php/php8.4-fpm.sock;
}
```

Behind a load balancer or proxy that terminates TLS, configure Laravel's trusted proxies (`bootstrap/app.php`, `$middleware->trustProxies(at: [...])`). Otherwise the application cannot tell that requests are HTTPS, and it skips HSTS and secure redirects.

### PHP-FPM

In `php.ini`: `expose_php = Off`, `upload_max_filesize = 20M`, `post_max_size = 24M`, `memory_limit = 256M`, `opcache.enable = 1`, `opcache.validate_timestamps = 0` (then reload PHP-FPM on every deploy).

## 6. Queue worker and scheduler

Both are required:

- **Queue:** order emails and payment webhook processing run on the queue.
- **Scheduler:** runs payment reconciliation (every 5 minutes), the daily backup, the daily marketplace license check and a heartbeat that the health check watches.

`/etc/systemd/system/ecomodular-queue.service`:

```ini
[Unit]
Description=EcoModular queue worker
After=network.target mysql.service redis.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/ecomodular
ExecStart=/usr/bin/php artisan queue:work redis --tries=3 --backoff=10 --max-time=3600 --sleep=3
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
systemctl daemon-reload && systemctl enable --now ecomodular-queue
```

Workers exit after an hour (`--max-time`) and systemd restarts them, which bounds memory growth. After every deploy, run `php artisan queue:restart` so they load the new code. Supervisor works the same way, with `autorestart=true` and `stopwaitsecs=3600`.

Scheduler (`crontab -u www-data -e`):

```
* * * * * cd /var/www/ecomodular && php artisan schedule:run >> /dev/null 2>&1
```

Check it with `php artisan schedule:list`. Within two minutes, the health check reports "Scheduler: Running".

## 7. Logs

Use `LOG_STACK=daily` with `LOG_DAILY_DAYS=14`; Laravel then rotates `storage/logs/laravel-YYYY-MM-DD.log` and deletes old files. If you use the `single` channel instead, configure logrotate:

```
/var/www/ecomodular/storage/logs/*.log {
    daily
    rotate 14
    compress
    missingok
    notifempty
    copytruncate
}
```

Secrets are never logged: request fields such as passwords and tokens are excluded from exception reports, and external tools' error output is truncated. To send errors to a monitoring service, add a log channel (for example Sentry's Laravel SDK or a Slack webhook at `LOG_LEVEL=error`).

## 8. Updates and rollback

Before every update, take a backup and keep the previous release:

```bash
php artisan ecomodular:backup                 # database + files, verified checksums
php artisan down --retry=60
git fetch --tags && git checkout v1.0.1        # or unpack the release archive into a new directory
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan ecomodular:sync-permissions
php artisan optimize
php artisan queue:restart
php artisan up
php artisan ecomodular:health
```

Releases follow Semantic Versioning, and migrations only move forward. To roll back a failed update:

1. Put the store in maintenance mode: `php artisan down`.
2. Switch the code back to the previous release (a previous release directory or tag), then run `composer install --no-dev` and `php artisan optimize`.
3. Restore the pre-update backup: `php artisan ecomodular:restore <backup>.zip --force`. Restoring is required because migrations may have changed the schema.
4. Run `php artisan queue:restart`, then `php artisan up`.

With symlinked release directories (for example Deployer or Envoyer), steps 2 and 4 come down to switching the `current` symlink and reloading PHP-FPM.

Extension updates (modules, themes, marketplace packages) have their own recovery paths. See [extensions/recovery.md](../extensions/recovery.md).

## 9. Health checks and monitoring

- `GET /up`: liveness check that returns 200 when the application boots.
- `GET /health`: readiness check that returns `{"status":"ok|warning|failed"}`, with HTTP 503 when a check failed. Call it with `Authorization: Bearer $ECOMODULAR_HEALTH_TOKEN` to get every check.
- `php artisan ecomodular:health`: the same checks for cron-based alerting. It exits with status 1 on failure.
- Admin → **System status**: the same checks, plus environment and requirements.

Details and alert suggestions: [monitoring.md](../operations/monitoring.md).

## Post-install hardening checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` uses `https://`
- [ ] HTTPS enforced (HTTP → HTTPS redirect), `SESSION_SECURE_COOKIE=true`, trusted proxies configured if TLS terminates upstream
- [ ] `.env` is mode 600; the document root is `public/`; `APP_KEY` and `ECOMODULAR_BACKUP_PASSWORD` are stored in a secret manager
- [ ] The owner account has two-factor authentication or a passkey
- [ ] `storage/app/private/installer.token` does not exist (it is deleted on install); `ECOMODULAR_WEB_INSTALLER=false`
- [ ] MySQL and Redis are not reachable from the internet
- [ ] Queue worker and scheduler are running (`php artisan ecomodular:health` shows no warnings for them)
- [ ] Backups are encrypted, copied off the server and a restore has been tested ([backup-and-restore.md](../operations/backup-and-restore.md))
- [ ] Log rotation is configured
- [ ] `/health` is monitored, and `ECOMODULAR_HEALTH_TOKEN` is set
- [ ] `composer audit` and `npm audit` are clean
- [ ] Stripe webhooks are configured and the webhook secret is set (Admin → Payments)
