# Module Recovery Guide

Every module lifecycle step is recorded in **Admin → Extensions → (module) → Lifecycle log**, in the `extension_events` table, and in the audit log. Start there to see what happened. The `failure` message on the module explains the current problem.

## A module throws while the store boots

**What you see:** the module shows **Failed** with a message such as `RuntimeException: …` and a `quarantined` log entry. The store keeps working without the module.

The guard catches exceptions from the module's `register()`, `boot()` and route files and takes the module out of service automatically. No action is needed to restore the store.

1. Read the failure message and the application log (`storage/logs/laravel.log`).
2. Fix the cause: missing configuration, a broken package file, or a dependency.
3. **Admin → Extensions → (module) → Disable**, then **Enable**. Enabling runs the provider as a trial and refuses if it still fails.

## The store does not start at all (safe mode)

A fatal engine error, such as a PHP parse error in a file loaded early, memory exhaustion or a segfault in an extension, cannot be caught. If the store is down after enabling or updating a module:

1. Turn on safe mode, which boots without loading any module:

    ```bash
    ECOMODULAR_SAFE_MODE=true php artisan ecomodular:modules list
    ```

    For the web server, set `ECOMODULAR_SAFE_MODE=true` in `.env`, then run `php artisan config:clear` (or `config:cache` if you cache configuration).

2. Disable the module that caused it:

    ```bash
    ECOMODULAR_SAFE_MODE=true php artisan ecomodular:modules disable vendor/package
    ```

3. Remove `ECOMODULAR_SAFE_MODE` again and clear the configuration cache.

4. Fix or remove the package before enabling it again.

As a last resort, if even the CLI fails because the package's files themselves are broken, move the package directory out of `modules/`. The module is then marked **Failed** (files missing) and is not loaded. Put the files back to update or uninstall it.

## A failed install

The migrations that ran are rolled back automatically, and the module stays **Failed** without an installed version. The failure message says whether the rollback succeeded.

- **Rolled back:** fix the cause, then click **Install** again.
- **Not fully rolled back** (MySQL cannot roll back schema changes):
    1. Compare the module's migration files with the `migrations` table and the tables that exist.
    2. Drop leftover tables created by the module, and delete their rows from `migrations`.
    3. Install again.

## A failed update

The module stays at its previous version, is **Failed**, and is not loaded, so new code never runs against the old schema. Migrations that succeeded before the failure stay applied.

1. Fix the cause: usually the failing migration, or data that violates a new constraint.
2. Click **Update** again. Only the migrations that have not run yet will run.

If the new release is broken and you need the store back on the old one, restore the previous package files. Downgrading through the lifecycle is refused, so if the update's migrations already ran, restore the database from backup first (see `docs/operations/backup-and-restore.md`).

## Files changed under an enabled module

Copying a newer release into `modules/` puts the module into **Update pending** at the next request. It is not loaded until you run **Update**. Copying an older release puts it into **Failed** ("older than the installed version"); restore the newer files.

## Caches

Lifecycle operations clear the route and event caches. In production, run `php artisan optimize` afterwards. If you deploy with cached routes and enable a module from the command line on another server, run `php artisan route:clear` on each web server.

## Uninstalling a module that is failing

Uninstall works on modules that are **Failed**, **Disabled** or **Update pending**, but it needs the package files: its migrations are rolled back from them. Uninstalling deletes the module's tables and data. Back up first.

## Clearing out broken modules after a deploy

A deploy can leave modules the server can no longer run: their files are missing or only partly there ("Autoload path src/ is not inside the package"), or older than the version recorded as installed. To list them, run:

```bash
php artisan ecomodular:modules:prune
```

To remove them, run it again with `--force`. Each listed module loses its record, its files and its data (its tables are dropped when the migrations are still on disk). Working modules and protected modules such as the Modrix Cloud Connector are left alone. Back up first, then install the modules you need again from Modrix Cloud.
