# Production Installation

This guide targets a conventional Linux VPS running Nginx, PHP-FPM 8.3/8.4, MySQL 8 and Redis. Kubernetes is not required.

> EcoModular is pre-1.0. Commerce features (catalog, checkout, payments) are not available yet. These instructions cover the platform foundation and will grow with each phase.

## 1. Server packages

- PHP 8.3 or 8.4 with FPM and these extensions: `bcmath ctype curl dom fileinfo filter intl mbstring openssl pdo_mysql session tokenizer xml zip gd redis`
- Composer 2, plus Node 22+ to build assets (or build them in CI and upload `public/build`)
- MySQL 8.0+/8.4 LTS, Redis 7, Nginx, and a TLS certificate (for example Let's Encrypt)

## 2. Database

```sql
CREATE DATABASE ecomodular CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ecomodular'@'localhost' IDENTIFIED BY '<strong unique password>';
GRANT ALL PRIVILEGES ON ecomodular.* TO 'ecomodular'@'localhost';
```

## 3. Application

```bash
cd /var/www/ecomodular
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

| Variable                                            | Production value           |
| --------------------------------------------------- | -------------------------- |
| `APP_ENV`                                           | `production`               |
| `APP_DEBUG`                                         | `false`                    |
| `APP_URL`                                           | `https://shop.example.com` |
| `DB_CONNECTION`, `DB_*`                             | MySQL credentials          |
| `CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER` | `redis`                    |
| `SESSION_SECURE_COOKIE`                             | `true`                     |
| `AUTH_PASSWORD_TIMEOUT`                             | `1800` (seconds) or lower  |
| `MAIL_*`                                            | your SMTP provider         |
| `LOG_LEVEL`                                         | `warning`                  |

Then:

```bash
npm ci && npm run build           # or deploy a prebuilt public/build
php artisan migrate --force
php artisan ecomodular:install    # interactive; creates the owner and locks the installer
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan storage:link
```

For non-interactive installs, pass `--store-name --name --email --currency --timezone` and provide the owner password in the `ECOMODULAR_OWNER_PASSWORD` environment variable for that single command. Never pass it as a CLI argument.

## 4. File ownership and permissions

```bash
chown -R deploy:www-data /var/www/ecomodular
find /var/www/ecomodular -type f -exec chmod 640 {} \;
find /var/www/ecomodular -type d -exec chmod 750 {} \;
chmod -R ug+rwX storage bootstrap/cache
chmod 600 .env
```

Only `storage/` and `bootstrap/cache/` should be writable by the PHP-FPM user.

## 5. Nginx

Use `docker/nginx/default.conf` as a template. It sets the document root to `public/`, allows only `index.php` to execute, and denies dotfiles. Add TLS, and redirect HTTP to HTTPS.

## 6. Queue worker and scheduler

systemd unit (`/etc/systemd/system/ecomodular-queue.service`):

```ini
[Service]
User=www-data
ExecStart=/usr/bin/php /var/www/ecomodular/artisan queue:work redis --tries=3 --max-time=3600
Restart=always
```

Restart workers on every deploy with `php artisan queue:restart`.

Add the scheduler to cron (`crontab -u www-data -e`):

```
* * * * * cd /var/www/ecomodular && php artisan schedule:run >> /dev/null 2>&1
```

## 7. Updates

```bash
php artisan down
git pull                         # or deploy the new release
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan ecomodular:sync-permissions
php artisan optimize
php artisan queue:restart
php artisan up
```

Take a backup before every update ([backup-and-restore.md](../operations/backup-and-restore.md)). To roll back, restore the previous release directory and database backup.

## 8. Health checks

- `GET /up` returns 200 when the application boots (Laravel health route).
- Admin → **System status** shows driver configuration, requirement checks, free disk space and configuration warnings.

## Post-install hardening checklist

- [ ] `APP_DEBUG=false` and `APP_ENV=production`
- [ ] HTTPS enforced, and `SESSION_SECURE_COOKIE=true`
- [ ] `.env` is mode 600 and not web-accessible; the document root is `public/`
- [ ] Owner account has two-factor authentication enabled
- [ ] `storage/app/private/installer.token` does not exist (it is deleted automatically on install)
- [ ] Database and Redis are not reachable from the internet
- [ ] Queue worker and scheduler are running
- [ ] Automated, encrypted, off-site backups are configured and a restore has been tested
- [ ] Log rotation is configured (`/etc/logrotate.d` for `storage/logs/*.log`, or use `LOG_STACK=daily`)
- [ ] `composer audit` and `npm audit` are clean
