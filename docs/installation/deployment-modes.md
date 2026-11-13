# Deployment modes

Modrix Commerce installs in one of two ways. The web installer at `/install` detects which one applies.

| Mode            | For                                                                            | The installer asks for                                                               |
| --------------- | ------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------ |
| **Auto-deploy** | Platforms that deploy from Git and provide the database (Uplary and similar)   | The owner account and store basics, plus the database password as proof of ownership |
| **Manual**      | cPanel, Plesk, DirectAdmin, CyberPanel and other shared hosting, no SSH needed | Database connection, then the owner account and store basics                         |

For a VPS with shell access, see [production deployment](production.md). There, `php artisan ecomodular:install` is the usual path.

## How the mode is chosen

`ECOMODULAR_INSTALL_MODE` controls it:

- `detect` (default): **auto** when the configured database connects, has a `migrations` table and no pending migrations. Otherwise **manual**.
- `auto`: always auto. If the database is not ready, the installer names the problem (not configured, unreachable, or migrations not run) instead of offering the database step.
- `manual`: always the full wizard, even when a database is configured.

Either way, the installer locks itself when it finishes. It writes `storage/app/installed` (path set by `ECOMODULAR_INSTALL_LOCK`), deletes the setup token, and `/install` returns 404 from then on. `ECOMODULAR_WEB_INSTALLER=false` disables the web installer completely.

## Auto-deploy (Git platforms)

### What the platform must provide

Environment variables, set in the platform's encrypted environment settings, not in Git:

| Variable                                                                           | Value                                                                                                            |
| ---------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| `APP_KEY`                                                                          | `base64:` plus 32 random bytes (`php artisan key:generate --show`). Keep it: it decrypts stored credentials      |
| `APP_ENV`, `APP_DEBUG`                                                             | `production`, `false`                                                                                            |
| `APP_URL`                                                                          | The public HTTPS URL                                                                                             |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | The database the platform provisions (MySQL 8 or MariaDB recommended). Set a password: the installer asks for it |
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION`                                | `redis` if the platform offers Redis, otherwise `database`                                                       |
| `SESSION_SECURE_COOKIE`                                                            | `true`                                                                                                           |
| `MAIL_*`                                                                           | Your SMTP provider                                                                                               |

Deploy script (build and release steps):

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize
```

You also need:

- A persistent volume for `storage/`. Uploads, logs, the install lock, backups and the modules and themes installed from Modrix Cloud (`storage/app/packages/`) live there, and they must survive redeploys.

### Redeploying

A redeploy updates the code and runs the new migrations. It does not reset the store: the database, uploads, settings and installed modules and themes stay as they are. Only the packages that ship with the code (`modules/modrixcommerce/cloud-connector` and `themes/modrixcommerce/circuit`) come from the repository.

If the platform cannot keep `storage/`, or packages were lost before 1.2.2, the store reinstalls them itself: after `php artisan migrate` and every five minutes (scheduler), the Modrix Cloud Connector downloads again every installed module and the active theme whose files are missing, and restores its state. Run `php artisan modrix:cloud:restore` to do it at once. Uploads and backups in `storage/` cannot be recovered this way, so use a persistent volume.
- A queue worker (`php artisan queue:work --tries=3`) and the scheduler (`php artisan schedule:run` every minute, or `php artisan schedule:work` as a long-running process).

### Finishing the install

1. Deploy. The first deploy runs the migrations, so the installer detects auto mode.
2. Open `https://your-domain/install`.
3. Enter the database password from the platform's environment settings (`DB_PASSWORD`). It proves you own this deployment. It is only compared, never stored or displayed. If the database has no password, the installer asks for the setup token in `storage/app/private/installer.token` instead.
4. Enter the store name, currency and timezone, then the owner's name, email and password. You are signed in to the admin.

## Manual install (cPanel and other control panels)

No SSH or terminal is needed.

1. **Upload.** Upload the release archive, which includes `vendor/` and `public/build`, and extract it into a folder such as `/home/USER/modrix`. Do not extract it inside `public_html`.
2. **Document root.** Point the domain's document root at the `public/` folder: `/home/USER/modrix/public`.
    - cPanel: Domains → Manage → Document Root.
    - Plesk: Hosting Settings → Document root.
    - DirectAdmin: Domain Setup → Custom document root.
    - CyberPanel: Websites → vHost Conf.

    The installer refuses to continue while the site is served from the project folder, because `.env` and `storage/` would be reachable from the web.

3. **PHP.** Select PHP 8.3 or 8.4 in the panel. Enable these extensions: `bcmath ctype curl dom fileinfo filter intl mbstring openssl pdo pdo_mysql session tokenizer xml zip`.
4. **Database.** In the panel (for example cPanel → MySQL Databases), create a database and a user, and give the user all privileges on that database. MySQL 5.7+ or MariaDB 10.3+ is required.
5. **Permissions.** `storage/` and `bootstrap/cache/` must be writable by PHP. So must `.env` in the application folder, or the folder itself if `.env` does not exist yet. On most panels the defaults (755 for folders, 644 for files, owned by your account) already work.
6. **Run the wizard.** Open `https://your-domain/install`.
    1. **Requirements:** fix anything marked failed, then reload.
    2. **Database:** enter the host (usually `localhost`), port (`3306`), database name, user and password. Use **Test connection** to check them.
    3. **Setup token:** open `storage/app/private/installer.token` in the panel's File Manager and paste its contents. The token expires after 24 hours; reloading the installer issues a new one.
    4. **Save and create tables:** this writes the details to `.env`, creates the tables and the system roles, and links `public/storage`. If the host forbids symbolic links, the installer says so: create the link through the panel or ask your host, or uploaded images will not display.
    5. **Owner:** enter the store details and the owner account. You are signed in to the admin, and the installer is locked.
7. **Cron.** Add a cron job that runs every minute (cPanel → Cron Jobs):

    ```
    * * * * * /usr/local/bin/php /home/USER/modrix/artisan schedule:run >> /dev/null 2>&1
    ```

    The scheduler runs backups, payment reconciliation, the license check and queued work. Without SSH, set `QUEUE_CONNECTION=sync` in `.env` so emails and webhooks run during the request.

8. **Afterwards.** Set `APP_ENV=production` and `APP_DEBUG=false` in `.env` if they are not already, then work through the [post-install hardening checklist](production.md#post-install-hardening-checklist).

## Troubleshooting

| Symptom                                            | Fix                                                                                           |
| -------------------------------------------------- | --------------------------------------------------------------------------------------------- |
| A plain-text error about `.env` on the first visit | Make the application folder writable, or upload `.env.example` as `.env` and make it writable |
| "Wrong document root"                              | Point the domain at `public/` (step 2)                                                        |
| "Migrations have not run" in auto mode             | Add `php artisan migrate --force` to the deploy script and redeploy                           |
| "The database password is incorrect"               | Use the exact `DB_PASSWORD` from the platform's environment settings                          |
| "MySQL x.y is too old"                             | Ask the host for MySQL 5.7+ or MariaDB 10.3+                                                  |
| `/install` returns 404                             | The store is installed, or `ECOMODULAR_WEB_INSTALLER=false` is set                            |
