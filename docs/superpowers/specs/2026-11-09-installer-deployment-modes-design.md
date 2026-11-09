# Installer deployment modes: design

Date: 2026-11-09. Status: approved design, awaiting spec review.

## Goal

Modrix Commerce installs in one of two ways, and the `/install` wizard decides which applies:

- **Auto-deploy**: platforms such as Uplary deploy from GitHub, provide the database and run migrations in the deploy script. The installer skips the database step and only asks for the owner account and store basics.
- **Manual install**: cPanel, Plesk, DirectAdmin, CyberPanel and similar shared hosting with no SSH. The wizard runs a requirements check, a database connection step that writes `.env`, migrations and seeding, then the owner account. No shell commands are used.

After either flow completes, the installer locks itself: `/install` returns 404.

## Decisions

| Topic | Decision |
| --- | --- |
| Proof of ownership, auto mode | The merchant types the configured database password (`DB_PASSWORD`). If it is empty, the setup token file is required instead. |
| Proof of ownership, manual mode | The existing setup token file (`storage/app/private/installer.token`), readable through the control panel's file manager. |
| Document root | Manual installs require the document root to be `public/`. If the site is served from the project root, the installer blocks and explains how to change the document root. No root forwarder is shipped. |
| Databases for the manual wizard | MySQL 5.7+ and MariaDB 10.3+. Auto mode accepts any configured, migrated connection. |
| Mode override | `ECOMODULAR_INSTALL_MODE=detect` (default), `auto` or `manual`. |

## Existing pieces reused

- `InstallPlatform`: creates the owner, syncs roles, saves store settings, marks installed, deletes the token, activates the default theme. Unchanged except that marking installed also writes the lock file (through `InstallationState`).
- `InstallerToken`: unchanged.
- `RequirementChecker`: reused for the requirements step and `pendingMigrations()` for detection; gains `pdo_mysql` and `.env` writability checks for manual mode only.
- `BlockAfterInstallation` (`not-installed`) and `RequireInstallation` (`installed`) middleware: kept, extended as below.
- `InstallCommand` (`ecomodular:install`): unchanged behaviour; it benefits from the lock file automatically.
- From the Modrix Cloud installer (`ModrixCloud/app/Services/Installer.php`): `prepareEnvironment()`, `connect()`, `driverFor()`, `writeEnv()`/`envValue()` and the session/cache file fallback are ported, adapted to this codebase's namespaces and style.

## Components

### `InstallationState` (changed)

- `isInstalled()`: true when the lock file `storage/app/installed` exists; otherwise falls back to the settings row `system.installed_at`, catching any database error (no database or no settings table means "not installed").
- `markInstalled()`: writes the settings row and the lock file.
- Existing installs (settings row, no lock file) stay installed.

### `InstallEnvironment` (new, `app/Core/Installation`)

- `mode(): InstallMode`, an enum with `Auto` and `Manual`.
  - `ECOMODULAR_INSTALL_MODE=auto|manual` forces the mode.
  - `detect` returns `Auto` when the default connection has a driver and database configured, connects, has a `migrations` table, and `RequirementChecker::pendingMigrations() === 0`. Otherwise `Manual`.
- `databaseReady(): bool` and `databaseProblem(): ?string`: used to explain why a forced `auto` cannot proceed (not configured, cannot connect, N migrations pending). A forced `auto` never falls back to manual.
- `servedFromPublic(Request): bool`: true when the front controller is `public/index.php` (by `SCRIPT_FILENAME` resolved against `public_path()`).

### `DatabaseSetup` (new, `app/Core/Installation`)

Port of the Modrix Cloud installer's database half:

- `prepareEnvironment()`: copy `.env.example` to `.env` if missing; generate `APP_KEY` if empty.
- `test(array $db): string`: connect with PDO (5-second timeout) and return the server version; throws `InstallerException` with the driver's message, never echoing the password.
- `driverFor(string $version): string`: `mysql` or `mariadb`, enforcing the minimum versions.
- `apply(array $db)`: test, write `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `APP_URL` to `.env`, point the running request at the new connection, clear a cached config, run `migrate --force` and `db:seed --force` through `Artisan::call`, then attempt `storage:link`.
- `writeEnv(array $values)` and `envValue(string)`: safe `.env` updates (quoting `#`, `$`, quotes, backslashes, spaces), with atomic write via `LOCK_EX`; a clear error when `.env` is not writable.
- Connection creation sits behind a small seam (`connector` callable / overridable method) so tests can run the full `apply()` path against SQLite.

`storage:link` failure (symlinks disabled on some shared hosts) is reported as a warning on the next step; the install continues.

### `PrepareInstallEnvironment` middleware (new)

`RequireInstallation` is a route alias (`installed`), not part of the `web` group, so the pre-install bootstrap is a separate middleware **prepended to the `web` group** (ahead of `EncryptCookies` and `StartSession`). It does nothing once installed. Before installation:

- Calls `DatabaseSetup::prepareEnvironment()` so the first request has an `APP_KEY`. If `.env` cannot be created or written, it responds with a plain-text 500 explaining which file to make writable.
- Switches `session.driver` and `cache.default` from `database` to `file`, so the wizard works with no database. The installer's cache lock (`InstallPlatform`) uses the same fallback.

The `web` group's appended middleware (`SetStorefrontLocale`, `StorefrontMaintenance`, `HandleInertiaRequests`, `LogoutDisabledUsers`, `EnsureRegistrationEnabled`) must not crash with no database on `/install` routes. Each is checked; any that reads settings or the database before installation is made to skip, or catch the failure, when not installed. `RequireInstallation` and `BlockAfterInstallation` keep their roles and use the lock-file-aware `InstallationState`.

### `InstallController` (changed) and routes

Routes under `not-installed` and `throttle` on POSTs:

| Route | Purpose |
| --- | --- |
| `GET /install` | Renders the wizard for the current mode and step |
| `POST /install/database/test` | Manual mode: test a connection, return version or error |
| `POST /install/database` | Manual mode: verify the token, run `DatabaseSetup::apply()` |
| `POST /install` | Both modes: create the owner and store (existing `store`) |

Step logic in `show()`:

- **Auto**: requirements, then the owner and store form. The form asks for the database password, or the setup token when `DB_PASSWORD` is empty.
- **Manual, database not ready**: requirements (including `public/` document root, `pdo_mysql`, writable `.env`), then the database form with the setup token. A failing document-root check blocks the form with instructions.
- **Manual, after the database step**: the database is now migrated, so detection returns `Auto`. The session records that the token was proven in the database step (`install.verified`), so the owner form does not ask for proof again. A merchant who reloads in a new session is asked for the database password (or token), which they just entered.

`store()` verifies proof (session flag, database password compared with `hash_equals`, or token), checks requirements, then `InstallPlatform::handle()` (which already syncs permissions and system roles, so no separate seeder run is needed), logs the owner in and redirects to the admin dashboard.

### Wizard page (`resources/js/pages/install/index.tsx`, changed)

One Inertia page with a step indicator. It renders the requirements card, the document-root block, the database form with a "Test connection" button, and the existing owner and store form, based on `mode` and `step` props. Errors appear inline per field. The page never receives the token value or the database password.

## Error handling

- Database errors show the driver's message (host unreachable, access denied, unknown database) without the submitted password.
- Migration failures stop the step with the error; `.env` keeps the new values so a retry reuses them.
- An unwritable `.env`, `storage` or `bootstrap/cache` fails the requirements step before any form is shown.
- A forced `auto` mode with an unready database shows the specific problem and the deploy commands to run.
- Concurrent installs remain serialised by `InstallPlatform`'s cache lock.

## Security

- `/install` returns 404 once installed (lock file or settings row) and when `ECOMODULAR_WEB_INSTALLER=false`.
- The token file is deleted on completion.
- Proof of ownership is required on every state-changing POST.
- Submitted passwords are never logged, flashed back or rendered.
- Manual installs refuse to run unless served from `public/`, so `.env` is never inside the web root.

## Testing

Feature tests (SQLite, run serially), using a temporary `.env` path and storage path per test:

- Detection: forced `auto`, forced `manual`, migrated database → auto, pending migrations → manual, unreachable database → manual, forced `auto` with unready database → error shown.
- Auto flow: wrong database password rejected; empty `DB_PASSWORD` requires the token; correct proof creates the owner, sets the lock file and logs in.
- Manual flow: document root not `public/` blocks; invalid token rejected; database step writes `.env` correctly (escaping cases), runs migrations through the SQLite seam, sets the session flag; owner step then completes.
- Lock: lock file alone makes `/install` 404; installing twice is refused; existing settings-row installs stay installed; the CLI install writes the lock file.
- Unit: `envValue()` round-trips through Laravel's dotenv parser; `driverFor()` version thresholds.

## Documentation

New `docs/installation/deployment-modes.md`, linked from `production.md`:

- How the mode is detected and how to force it.
- Auto-deploy: required environment variables (`APP_KEY`, `APP_URL`, `APP_ENV`, `DB_*`, session/cache/queue drivers), a deploy script (`composer install --no-dev`, `npm ci && npm run build`, `php artisan migrate --force`, `php artisan storage:link`, `php artisan optimize`), persistent `storage/`, queue worker and scheduler, then the browser step with the database password.
- cPanel and other panels: upload, point the document root at `public/`, create the database and user in the panel, read the setup token with the file manager, run the wizard, then the cron entry for the scheduler.

## Out of scope

PostgreSQL in the manual wizard, creating databases from the installer, a project-root forwarder, and changes to the CLI installer's prompts.
