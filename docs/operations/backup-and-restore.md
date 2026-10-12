# Backup and Restore

EcoModular backs itself up with `php artisan ecomodular:backup` and restores with `php artisan ecomodular:restore`. The restore procedure is tested on every test run (`tests/Feature/Operations`), and CI runs it against MySQL (see [the drill record](#restore-drill-record)).

## What is backed up

| Item                         | In the archive as                 | Notes                                                                                            |
| ---------------------------- | --------------------------------- | ------------------------------------------------------------------------------------------------ |
| Database                     | `database/database.sql`           | `mysqldump --single-transaction` (MySQL/MariaDB), `pg_dump` (PostgreSQL), `VACUUM INTO` (SQLite) |
| Media                        | `files/storage-public/`           | `storage/app/public` (product images, theme uploads)                                             |
| Private files                | `files/storage-private/`          | `storage/app/private` except `backups/` and temporary marketplace files                          |
| Installed modules and themes | `files/modules/`, `files/themes/` | Includes marketplace installs and their backups                                                  |
| Manifest                     | `manifest.json`                   | Format, platform version, date, database driver and the SHA-256 of every entry                   |

**Not included:**

- `.env`. Keep `APP_KEY` and `ECOMODULAR_BACKUP_PASSWORD` in a secret manager. Without the same `APP_KEY`, encrypted data (gateway credentials, 2FA secrets, license keys) cannot be decrypted after a restore.
- `vendor/`, `node_modules/` and `public/build`, which are rebuilt from the release.
- Media on S3. With `ECOMODULAR_MEDIA_DISK=s3`, protect the bucket with versioning and lifecycle rules instead.

## Taking backups

```bash
php artisan ecomodular:backup                  # database and files
php artisan ecomodular:backup --database-only  # quick, before risky admin work
php artisan ecomodular:backup --list
```

- **Location:** `storage/app/private/backups` (`ECOMODULAR_BACKUP_PATH`), files mode 600.
- **Schedule:** daily at `ECOMODULAR_BACKUP_SCHEDULE` (default `02:30`, server time; empty disables it). This needs the scheduler cron entry.
- **Retention:** the newest `ECOMODULAR_BACKUP_KEEP` archives (default 7).
- **Encryption:** set `ECOMODULAR_BACKUP_PASSWORD`, and every entry is AES-256 encrypted. Without it, the command warns on each run.
- **Credentials:** MySQL credentials are passed through a temporary file readable only by the PHP user, never on the command line. Command error output goes to the log, not to the console.
- **Off-site copies:** copy the archives off the server (for example `rclone copy storage/app/private/backups remote:ecomodular`), and restrict access to operators.
- **Monitoring:** the health check warns when the newest backup is older than 26 hours.

Recommended retention: 7 daily copies on the server, plus 4 weekly and 12 monthly copies off-site. Busy stores should also enable MySQL binary logs for point-in-time recovery.

## Restoring

```bash
php artisan ecomodular:restore ecomodular-backup-20261013-023000-ab12.zip --verify-only
php artisan ecomodular:restore ecomodular-backup-20261013-023000-ab12.zip
```

The restore works in this order:

1. Opens the archive (with `ECOMODULAR_BACKUP_PASSWORD` if encrypted) and validates the manifest. Entry paths are checked for traversal.
2. Extracts every entry to a private temporary directory and **verifies its SHA-256**. A damaged or modified archive is refused before anything changes.
3. Asks you to type `restore` (or pass `--force`), then puts the store in maintenance mode.
4. Moves the current file directories aside and moves the restored ones into place. The backups directory itself is never touched.
5. Imports the database. If the import fails, the previous files are put back.
6. Runs `migrate --force` (a backup from an older version is upgraded to the current schema) and clears caches.
7. Leaves maintenance mode and records `system.backup_restored` in the audit log.

Then run `php artisan queue:restart`.

The database driver must match: a MySQL backup restores into MySQL. To move between servers, install the same release, copy `.env` (same `APP_KEY`) and the archive, then restore.

### Verification after a restore

1. `php artisan ecomodular:health`: database, migrations and storage are `ok`.
2. Sign in as the owner. Two-factor authentication must still work, which proves `APP_KEY` matches.
3. Open a recent order, a product with images and Admin → Payments (credentials still decrypt).
4. Check the audit log for the most recent expected entries.

## Restore drill record

| Date         | Where                                                     | What                                                                                                          | Result                                           |
| ------------ | --------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------- | ------------------------------------------------ |
| 2026-10-13   | Local, SQLite, separate processes (`ProductionPathsTest`) | Install → product + image → encrypted backup → data overwritten, image and themes deleted → verify → restore  | Data, image and themes restored; owner intact    |
| 2026-10-13   | Local, SQLite, in process (`HealthAndBackupTest`)         | Round trip; tampered archive refused with no changes; wrong password refused; unsafe paths refused; retention | Passed                                           |
| 2026-10-13   | Dev store (SQLite)                                        | `ecomodular:backup`, then `ecomodular:restore --verify-only`                                                  | 63 files verified                                |
| Every CI run | GitHub Actions, MySQL 8.4                                 | `ecomodular:backup --database-only` → change → `ecomodular:restore --force` → change gone                     | Not yet run (no remote on the reference machine) |

Repeat a full restore into a staging server at least every quarter, and after changing database versions.
