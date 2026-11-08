# Core Updates

Modrix Commerce can update its own platform code from Modrix Cloud. A release is built from a checkout, published and signed on Modrix Cloud, detected by each store, and installed from Admin → Settings → System status or the command line. The install is verified, backed up, runs in maintenance mode, migrates, and rolls back on failure.

A core release replaces **core code only**. It never touches `.env`, `storage/`, `modules/`, `themes/`, `public/storage`, `bootstrap/cache` or the database files in `database/` (such as `database/database.sqlite`). Modules and themes keep updating through Modrix Cloud separately.

## Versioning

- `App\Core\Platform::VERSION` (`app/Core/Platform.php`) is the single source of truth. There is no VERSION file.
- It is shown in Admin → Settings → System status (Environment card, first row **Modrix Commerce**) and in the admin sidebar footer as `v<version>`.
- **Bump it for every release** before building the package. Modrix Cloud refuses an upload whose `app/Core/Platform.php` does not declare the version you enter.

Versions must be semver (`1.1.0`, `1.2.0-rc.1`). A store only offers releases newer than its own.

## Building a release

From a clean checkout of the release commit:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build        # creates public/build/manifest.json
php artisan ecomodular:core-package
php artisan ecomodular:core-package --output=/tmp/releases          # a directory
php artisan ecomodular:core-package --output=/tmp/modrix-1.1.0.zip  # or a .zip path
```

The command refuses to build when `vendor/autoload.php` or `public/build/manifest.json` is missing. The default output is `storage/app/private/core-packages/modrix-commerce-<version>.zip`. It prints the size and SHA-256.

**Included** (the managed paths, `ecomodular.updates.paths`, and the only paths an install replaces): `app`, `bootstrap/app.php`, `bootstrap/providers.php`, `config`, `database/migrations`, `database/seeders`, `database/factories`, `lang`, `public/build`, `public/index.php`, `resources`, `routes`, `vendor`, `artisan`, `composer.json`, `composer.lock`, `package.json`. Entries are relative to the app root, with no wrapping folder.

**Excluded:** `.env*` files (except `.env.example`), anything named `.git`, symlinks, SQLite database files anywhere (`*.sqlite`, `*.sqlite3`, `*.db`, `*-journal`, `*-wal`, `*-shm`), and everything outside the managed paths, including the rest of `database/`: `storage/`, `modules/`, `themes/`, `public/storage`, `bootstrap/cache`, `node_modules/`, `tests/`. A path missing from a package is left alone on the store, never deleted.

Build with `--no-dev` and the same PHP major/minor you declare as the minimum, because `vendor/` ships inside the package.

## Publishing on Modrix Cloud

1. Open `/admin/core` on Modrix Cloud (admin sign-in required).
2. Fill in **Version**, **Release notes**, **Minimum PHP** (`8.3` or `8.3.0`) and the **zip**, then upload. The release is stored as a **draft**.
3. **Publish** (drafts only) checks the stored file again with the upload rules below, re-hashes it and signs `{kind: "core", release_id, version, min_php, sha256, size, key_id}`. Stores see it from now on. A published or withdrawn release cannot be published again (`Only draft releases can be published.`).
4. **Withdraw** (a draft or a published release) stops listing and serving it. Stores that already downloaded it are not affected; an install in progress that has not yet fetched the package will fail.

Upload validation (the message is shown on the form):

| Rule                                                                               | Limit or message                                                       |
| ---------------------------------------------------------------------------------- | ---------------------------------------------------------------------- |
| Version is valid semver and not used before                                        | `Enter a valid version such as 1.1.0.` / `Version X already exists.`   |
| Minimum PHP like `8.3` or `8.3.0`                                                  | `Enter the minimum PHP version as 8.3 or 8.3.0.`                       |
| Valid zip, up to `marketplace.core_max_mb` (default 200 MB)                        | `Upload a ZIP up to 200 MB.`                                           |
| No symlinks, backslashes, absolute or `..` paths, at most 50,000 entries           | `The release contains unsafe paths.`                                   |
| No SQLite database files (`*.sqlite`, `*.sqlite3`, `*.db`, `*-journal`, `*-wal`, `*-shm`) | `The release contains a database file (<name>). Build it from a clean checkout.` |
| Contains `artisan`, `vendor/autoload.php`, `app/Core/Platform.php`                 | `The release must contain <file>.`                                     |
| `app/Core/Platform.php` declares `VERSION = '<version>'` exactly as entered        | `app/Core/Platform.php must declare VERSION = '<version>'.`            |

A published release cannot be edited. To fix a mistake, withdraw it and publish a new, higher version.

## Store side: finding updates

- **Daily check:** the scheduler runs `php artisan ecomodular:core-check` (needs the scheduler cron entry, like backups).
- **Manual check:** Admin → Settings → System status → **Core platform** card → **Check now**, or run `php artisan ecomodular:core-check`.
- Listing is public and needs no connection. The check keeps only the newest release whose signature verifies (against the official key plus `ECOMODULAR_MARKETPLACE_TRUSTED_KEYS`), whose kind is `core`, whose version is newer and whose minimum PHP is met by the store.
- The card shows the installed version, the available version with notes and minimum PHP, the last check time or error, and the last 5 runs. A failed check (Modrix Cloud unreachable) is shown as the last error and never breaks the page.
- **Health check "Core updates"** (Admin → System status, `ecomodular:health`, `/health`):
  - **failed** when a failed run could not be fully undone (`needs_attention`) and no update has completed since; the detail names the code and database backups to recover from;
  - **warning** when a run has not progressed for 10 minutes (`CoreUpdateRun::STUCK_MINUTES`), when the last run was rolled back, or `Modrix Commerce X is available` (only versions newer than the installed one count);
  - otherwise **ok**: `Up to date (checked <time>)`.
- After a successful install the stored check result is cleared when it offers the installed (or an older) version, so the card and the health check no longer offer it.
- **Downloading needs a Modrix Cloud connection** (Admin → Modrix Cloud, the Cloud Connector). Without it the card offers **Connect to Modrix Cloud to download** instead of **Install update**. The updater itself lives in core, so it works even when the Cloud Connector module is disabled, provided the store is connected.
- The Cloud address is `ECOMODULAR_MARKETPLACE_URL` (default `https://cloud-apps.modrixcommerce.com`).

### Who can install

Installing needs the `system.update` permission ("Install core updates") and a recent password confirmation, like installing an extension: the first click on **Install update** asks for your password and brings you back to System status, then click it again. By default only the **Owner** role has the permission; the Administrator role does not. Grant it to another role under Roles & permissions if you want. Checking for updates only needs `system.view`.

## Installing

From the card, **Install update** asks for confirmation and starts a detached `php artisan ecomodular:core-update <run>` process (in its own session with `setsid nohup … &` on Linux, `cmd /c start /B` on Windows), so the install survives the web request. The card polls the run's status. If the run is still `queued` after 60 seconds, the detached process did not start (for example `exec`-style functions are disabled or the PHP CLI was not found); the card then says `The update did not start. Run php artisan ecomodular:core-update <id> on the server.` The same install can be run from SSH:

```bash
php artisan ecomodular:core-update 12     # 12 = id of a queued run
```

A run is created by the admin button (status `queued`); the command takes the run id, not a version. Only one run executes at a time (cache lock `ecomodular:core-update` plus a check of the run table).

What happens, in order. Run status in brackets:

1. **Verify:** the stored release metadata is re-verified (signature, kind, version newer than installed, PHP requirement).
2. **Download** (`downloading`): requests a single-use link from Modrix Cloud with the connection token and streams the zip. It must match the signed size.
3. **Checksum** (`verifying`): the SHA-256 must equal the signed value.
4. **Extract checks:** every entry is checked before anything is written (no unsafe paths or symlinks, entry count and unpacked size limits from `ecomodular.updates.max_mb`, default 300). The package's `app/Core/Platform.php` must declare the signed version, `vendor/autoload.php` must exist, and each managed path must be the same kind (file or directory) as the installed one.
   It also refuses the install when the SQLite database file (`DB_DATABASE`) lies inside a managed path, because the swap would replace it. The default `database/database.sqlite` is outside the managed paths and is never touched.
5. **Code snapshot** (`backing_up`): the current managed paths are zipped to `storage/app/private/core-updates/core-<old-version>-<timestamp>.zip` (UTC, `Ymd-His`). The store is still online.
6. **Maintenance mode:** `php artisan down` with a bypass secret derived from the app key and run id. While a run is active the card shows the installing admin a **maintenance bypass link** (`/<secret>`), and the card uses it by itself when polling gets a 503, so the person installing can still follow the run.
7. **Database backup:** a database-only backup (`Backups::create(withFiles: false)`), taken in maintenance mode so no order can be written after it. Its name is stored on the run (`backup_name`) and lives in `storage/app/private/backups`.
8. **Swap** (`installing`): each managed path is renamed to `<path>.old-<random>` and the new one is moved in.
9. **Migrate** (`migrating`): `migrate --force` and `optimize:clear` run in a fresh PHP process, so the new code runs. Stale files in `bootstrap/cache` are removed first.
10. **Finish:** maintenance mode is lifted (`php artisan up`; if that fails, the updater deletes `storage/framework/down` itself and says so in the run message), the `.old-*` copies are deleted, `queue:restart` is issued, and the run becomes `completed`.

Every outcome is written to the update history (type `core`, `modrixcommerce/core`) and the audit log as `core.update_started`, `core.update_completed`, `core.update_failed` or `core.update_rolled_back`. If the message has a trailing `Note:` (for example "restart queue workers manually" or "could not delete ...old-..."), follow it; the update itself succeeded.

Plan for a few minutes of maintenance mode on a large `vendor/`. Schedule installs for quiet hours.

### Stuck runs

If the PHP process is killed (server restart, OOM), the run row stays in a non-final state. After **10 minutes without any change** the health check warns about it. After **120 minutes without any change** (`CoreUpdateRun::STALE_MINUTES`) the run is treated as dead and no longer blocks a new one. Before retrying, check the site is not still in maintenance mode (`php artisan up`) and that no `*.old-*` directories are left in the app root (see manual recovery).

## Failure and rollback

| Status        | Meaning                                                                                                                                                                         |
| ------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `failed`      | Stopped before the swap (steps 1-7): **nothing was changed**. Typical causes: not connected, checksum mismatch, bad package, SQLite file inside a managed path, backup failed, another update running, no disk space. |
| `rolled_back` | The swap or migration failed (steps 8-9) and was **undone automatically**. The store runs the previous version.                                                                 |
| `failed` with "could NOT be fully undone" | An automatic rollback itself had problems (or maintenance mode could not be lifted). The run is flagged `needs_attention`: the card shows a red alert and the health check fails until an update completes. The message lists the problems and names the code and database backups. Follow manual recovery. |

On a rollback the updater, in reverse order, moves each new path aside and puts the `.old-*` copy back, clears `bootstrap/cache`, and, if migrations had started, restores the database from the step 7 backup (`Backups::restoreDatabase`: database only, no file sets, no migrations). Tables and views created after the backup are dropped on MySQL and PostgreSQL. Maintenance mode is always lifted.

Limits:

- **Not dropped by a database restore:** PostgreSQL enum types and sequences, and MySQL triggers, created by a failed migration. Only tables and views are removed. Check for leftovers if a migration failed halfway, and remove them by hand before retrying.
- The database driver of the backup must match the store's (it always does for an update's own backup).

## Manual recovery

Use this when the message says the update could not be undone, or the site is broken after an interrupted run. The run row (Admin → System status → recent runs, or the `core_update_runs` table) names both backups: `code_backup_path` and `backup_name`.

1. **Leftover swap directories:** in the app root, `app.old-xxxxxxxx`, `vendor.old-xxxxxxxx` and similar are the previous code. If the current path is the new, broken one, rename it aside and rename the `.old-*` copy back.
2. **Code from the snapshot:** if the `.old-*` copies are gone, restore from `storage/app/private/core-updates/core-<version>-<timestamp>.zip`. Its entries are relative to the app root:
   ```bash
   cd /var/www/modrix
   unzip -o storage/app/private/core-updates/core-1.0.0-rc.1-20261108-020500.zip
   rm -f bootstrap/cache/*.php
   ```
   Files the new release added (new directories under `app/`, `database/migrations`) remain. Delete them if they cause trouble, or move the old paths aside first and unzip into clean ones.
3. **Database:** if migrations ran, restore the database backup named in the run:
   ```bash
   php artisan ecomodular:restore ecomodular-backup-20261108-020500-ab12.zip --verify-only
   php artisan ecomodular:restore ecomodular-backup-20261108-020500-ab12.zip
   ```
   See [Backup and Restore](backup-and-restore.md). The full restore also runs `migrate --force`, which on old code is harmless; run `php artisan migrate:status` afterwards.
4. **Bring the site back:** `php artisan up`, then `php artisan optimize:clear`, `php artisan queue:restart` and `php artisan ecomodular:health`.

Keep the code snapshots and update backups until you are confident in the new version. They are not pruned automatically (database backups follow the normal retention).

## Operational notes

- **Permissions:** the web server / PHP user must be able to write (create, rename, delete) every managed path and the app root, as well as `storage/` and `bootstrap/cache`. Renaming `vendor/` needs write access to the app root itself, not only to the folders inside it. A read-only code tree makes every install fail at the swap, and it is rolled back.
- **Windows:** file watchers, dev servers (`npm run dev`, `php artisan serve` file watching, `vite`), IDE indexers and antivirus can lock directories and block the rename. The updater retries moves for a few seconds, but stop watchers and dev servers before installing, and exclude the app folder from real-time scanning if you can.
- **Storage on another disk:** a `storage/` that is a symlink or mount on a different filesystem than the code is not supported. The updater keeps its work files in `storage/app/private/core-updates` and moves directories with `rename`, which cannot cross filesystems.
- **Caches:** the update clears caches itself. If you use `config:cache` / `route:cache` / `php artisan optimize` in production, run `php artisan optimize` again after a successful update to rebuild them for the new version. Restart PHP-FPM or reload OPcache if you run with `opcache.validate_timestamps=0`.
- **Queue workers:** `queue:restart` is issued automatically; workers managed by Supervisor or systemd will pick up the new code.
- **Disk space:** you need room for the package, its extracted copy, the code snapshot and a database backup at the same time.
- **Settings reference:** `ECOMODULAR_MARKETPLACE_URL`, `ECOMODULAR_MARKETPLACE_TRUSTED_KEYS` (comma-separated extra public keys), `ECOMODULAR_UPDATES_ROOT` (default: the app path) and `ECOMODULAR_UPDATES_PHP` (the PHP CLI binary used for the detached process and artisan calls). When it is empty the updater uses the current binary from the CLI, and under PHP-FPM or CGI looks up the `php` CLI (`PHP_PATH`, the `PATH`, then `PHP_BINDIR`). Set it if the store runs several PHP versions and the one found is not the store's.
- **SQLite:** keep the database file (`DB_DATABASE`) outside the managed paths; `database/database.sqlite` is fine. Packages never contain SQLite files, and Modrix Cloud refuses uploads that do.
- **Not covered:** release channels (beta/stable), delta packages, automatic installs, and multi-server deployments. On several servers, update one release at a time through your normal deployment instead.
