# Installer Deployment Modes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** One `/install` wizard that detects auto-deploy (database already migrated: owner form only, proof = database password) versus manual install (cPanel-style: requirements, document-root check, database step that writes `.env` and migrates, then owner form), and locks itself afterwards.

**Architecture:** `InstallationState` gains a lock file so "installed" is known without a database. A new `InstallEnvironment` decides the mode; a new `DatabaseSetup` (ported from `ModrixCloud/app/Services/Installer.php`) writes `.env` and migrates with no shell. A new `PrepareInstallEnvironment` middleware, prepended to the `web` group, makes the first request work without a database. `InstallController` grows database routes and a step-aware `show()`.

**Tech Stack:** Laravel 13, PHP 8.4 (`export PATH="/c/php84:$PATH"`), Inertia React + Wayfinder, PHPUnit feature tests on in-memory SQLite.

**Spec:** `docs/superpowers/specs/2026-11-09-installer-deployment-modes-design.md`

## Global Constraints

- Manual wizard databases: MySQL `5.7.0`+ and MariaDB `10.3.0`+ only.
- Mode override: `ECOMODULAR_INSTALL_MODE` = `detect` (default) | `auto` | `manual`, read via `config('ecomodular.installer.mode')`.
- Lock file default: `storage_path('app/installed')`, configurable as `config('ecomodular.installer.lock_file')`.
- Auto-mode proof: `hash_equals` against the configured `DB_PASSWORD`; when that is empty, the setup token is required.
- Manual installs require the front controller to be `public/index.php`; no root forwarder.
- No shell commands at runtime: only `Artisan::call` and PHP functions.
- Never render, flash, or log the token value or any password. Add `db_password` and `database_password` to `dontFlash` in `bootstrap/app.php`.
- Shared folder: touch only installer and deployment files (plus `tests/TestCase.php` for the lock-file path). Do not commit; the user has not asked. Tests run serially: `php artisan test --filter=<Name>`.

## Review Focus

1. Test runs and the dev server share `storage/`: a test that marks installed must never create the real `storage/app/installed`. Task 1 makes `TestCase` point the lock file at a temp path, with a test asserting the real path is untouched.
2. Existing installs (settings row, no lock file) must stay installed after the upgrade. Task 1 test `test_settings_row_alone_still_counts_as_installed`.
3. `.env` values with `#`, `$`, quotes, spaces or backslashes (common in hosting-generated passwords) must read back exactly. Task 3 round-trip test through `Dotenv\Dotenv::parse`.
4. A database error message must never contain the submitted password, even when the driver echoes it. Task 3 test `test_connection_errors_never_contain_the_password`.
5. `/install` with no database at all (missing settings table, `database` session driver) must render, not 500. Task 4 test `test_installer_renders_with_no_database_and_database_session_driver`.

---

### Task 1: Install lock file

**Files:**
- Modify: `app/Core/Installation/InstallationState.php`
- Modify: `config/ecomodular.php` (installer block, add `lock_file`, `mode`)
- Modify: `tests/TestCase.php` (`setUp`: point `ecomodular.installer.lock_file` at a unique temp file; `tearDown`: delete it)
- Test: `tests/Feature/Install/InstallLockTest.php`

**Interfaces:**
- Produces: `InstallationState::isInstalled(): bool` (lock file, else settings row, any `Throwable` → false), `markInstalled(): void` (writes both), `lockFile(): string`.
- Config: `'lock_file' => env('ECOMODULAR_INSTALL_LOCK', storage_path('app/installed'))`, `'mode' => env('ECOMODULAR_INSTALL_MODE', 'detect')`.

- [ ] **Step 1: Write failing tests** in `InstallLockTest` (uses `RefreshDatabase`):
  - `test_lock_file_alone_makes_the_installer_404`: `file_put_contents(config('ecomodular.installer.lock_file'), 'x')`, then `get('/install')->assertNotFound()`.
  - `test_settings_row_alone_still_counts_as_installed`: `app(SettingsRepository::class)->set(InstallationState::KEY, now()->toIso8601String())`, assert `isInstalled()` true and the lock file does not exist.
  - `test_mark_installed_writes_the_lock_file`: assert `assertFileExists(lockFile())`.
  - `test_missing_settings_table_means_not_installed`: `Schema::drop('settings')`, assert `isInstalled()` false (no exception).
  - `test_tests_never_touch_the_real_lock_file`: assert `config(...lock_file) !== storage_path('app/installed')` and `assertFileDoesNotExist(storage_path('app/installed'))` after `installPlatform()`.
  - `test_cli_install_writes_the_lock_file`: run `ecomodular:install` as in `InstallCommandTest`, assert the file exists.
- [ ] **Step 2:** Run `php artisan test --filter=InstallLockTest`. Expect failures (no lock file).
- [ ] **Step 3:** Implement. Lock file created with `File::ensureDirectoryExists`, `LOCK_EX`.
- [ ] **Step 4:** Run `--filter=InstallLockTest` and `--filter=Install` (existing installer tests). Expect PASS.

### Task 2: Mode detection

**Files:**
- Create: `app/Core/Installation/InstallMode.php` (enum `InstallMode: string { case Auto = 'auto'; case Manual = 'manual'; }`)
- Create: `app/Core/Installation/InstallEnvironment.php`
- Test: `tests/Feature/Install/InstallModeDetectionTest.php`

**Interfaces:**
- Consumes: `RequirementChecker::pendingMigrations(): ?int`.
- Produces:
  - `InstallEnvironment::mode(): InstallMode`: forced value if config is `auto`/`manual`; else `Auto` iff `databaseProblem() === null`.
  - `databaseProblem(): ?string`: `null` when ready, else one of `'not_configured'`, `'unreachable'`, `'not_migrated'` (exact strings; the page maps them to copy).
  - `databaseReady(): bool` (`databaseProblem() === null`).
  - `servedFromPublic(Request $request): bool`: `realpath(dirname($request->server('SCRIPT_FILENAME')))` equals `realpath(public_path())`.
  - `databasePasswordConfigured(): string`: the default connection's password ('' if none).
- "Configured" = connection has a `driver` and non-empty `database`. "Migrated" = `Schema::hasTable('migrations')` and `pendingMigrations() === 0`.

- [ ] **Step 1: Write failing tests:**
  - `test_migrated_database_detects_auto` (RefreshDatabase in-memory → `InstallMode::Auto`).
  - `test_pending_migrations_detect_manual`: `DB::table('migrations')->delete()` → `Manual`, `databaseProblem() === 'not_migrated'`.
  - `test_unreachable_database_detects_manual`: `config(['database.connections.sqlite.database' => '/no/such/dir/x.sqlite'])`, `DB::purge()` → `'unreachable'`.
  - `test_empty_database_name_is_not_configured` → `'not_configured'`.
  - `test_mode_can_be_forced` for both `auto` and `manual`; forced `auto` with pending migrations still returns `Auto` and `databaseProblem() === 'not_migrated'`.
  - `test_served_from_public_checks_the_front_controller`: `Request::create('/install', server: ['SCRIPT_FILENAME' => public_path('index.php')])` → true; `base_path('index.php')` → false.
- [ ] **Step 2:** Run `--filter=InstallModeDetectionTest`, expect FAIL (class missing).
- [ ] **Step 3:** Implement; catch `Throwable` around connect and schema checks.
- [ ] **Step 4:** Run, expect PASS.

### Task 3: DatabaseSetup (env writing, version check, migrate)

**Files:**
- Create: `app/Core/Installation/InstallerException.php` (`final class InstallerException extends RuntimeException`)
- Create: `app/Core/Installation/DatabaseSetup.php`
- Test: `tests/Unit/Installation/DatabaseSetupTest.php` (Laravel `Tests\TestCase`, since it needs the app)

**Interfaces:**
- Produces (port of ModrixCloud `Installer`, same behaviour unless stated):
  - `prepareEnvironment(): void`: copy `.env.example` → `app()->environmentFilePath()` if missing; set `APP_KEY` (`base64:` + 32 random bytes) if `config('app.key')` empty, and set it in config.
  - `test(array $db): string`: `$db` = `{host, port, database, username, password}`; returns `driverFor(connect($db))`.
  - `protected connect(array $db): string`: PDO `mysql:` DSN, `ATTR_TIMEOUT => 5`, returns `SELECT VERSION()`. Wraps `PDOException` in `InstallerException('Could not connect to the database: '.$message)` with the password `str_replace`d by `'********'` when non-empty.
  - `driverFor(string $version): string`: `'mysql'` | `'mariadb'`, throws below the Global Constraints minimums.
  - `apply(array $db): array{driver: string, storage_linked: bool}`: test → `writeEnv` (`DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, APP_URL`) → `protected useDatabase(array $db, string $driver): void` → `config:clear` if cached → `Artisan::call('migrate', ['--force' => true, '--seed' => true])` → `protected linkStorage(): bool` (skip if `public/storage` exists; `Artisan::call('storage:link')` in try, false on failure or if the link still doesn't exist).
  - `writeEnv(array<string,string> $values): void`, `static envValue(string $value): string`: as in ModrixCloud.
- Test seam: tests bind an anonymous subclass overriding `connect()` (returns a version string or throws) and `useDatabase()` (no-op, keeps in-memory SQLite) and `linkStorage()`.
- Tests use `$this->app->useEnvironmentPath($tmpDir)` with a temp `.env`; never the real one.

- [ ] **Step 1: Write failing tests:**
  - `test_env_values_round_trip` with a data provider: `'plain'`, `'p@ss#word'`, `'dollar$sign'`, `"it's"`, `'quote"and\'both'`, `'back\\slash'`, `'with space'`, `''`. Write `DB_PASSWORD`, then `Dotenv::parse(file_get_contents(...))['DB_PASSWORD']` equals the input.
  - `test_write_env_replaces_existing_keys_and_keeps_others`.
  - `test_unwritable_env_throws_installer_exception` (point at a path inside a missing directory).
  - `test_driver_thresholds`: `'5.7.44'` → mysql, `'5.6.51'` throws, `'10.3.39-MariaDB'` → mariadb, `'10.2.44-MariaDB'` throws, `'8.0.36'` → mysql.
  - `test_connection_errors_never_contain_the_password`: subclass `connect` throws a PDOException containing `'S3cret!'`, then call the real error wrapping. The `InstallerException` message has no `'S3cret!'`. (Put the redaction in a `protected redact(string $message, string $password): string` called by `connect`, so the test covers it directly.)
  - `test_prepare_environment_creates_env_and_key`: temp dir with only `.env.example`, `config(['app.key' => ''])` → `.env` exists with `APP_KEY=base64:`.
  - `test_apply_writes_env_and_runs_migrations`: fake returns `'8.0.36'`; after `apply()`, `.env` has `DB_CONNECTION=mysql` and `DB_HOST=db.example.test`, and `Role::where('name', SystemRoles::OWNER)->exists()` (the seeder ran).
- [ ] **Step 2:** Run `--filter=DatabaseSetupTest`, expect FAIL.
- [ ] **Step 3:** Implement.
- [ ] **Step 4:** Run, expect PASS.

### Task 4: PrepareInstallEnvironment middleware

**Files:**
- Create: `app/Http/Middleware/PrepareInstallEnvironment.php`
- Modify: `bootstrap/app.php`: `$middleware->web(prepend: [PrepareInstallEnvironment::class])`; add `db_password`, `database_password` to `dontFlash`.
- Test: `tests/Feature/Install/PrepareInstallEnvironmentTest.php`

**Interfaces:**
- Consumes: `InstallationState::isInstalled()`, `DatabaseSetup::prepareEnvironment()`.
- Behaviour: installed → pass through. Not installed → `prepareEnvironment()` (on `InstallerException` return a plain-text 500 with the message); set `session.driver` and `cache.default` to `file` when they are `database`.

- [ ] **Step 1: Write failing tests:**
  - `test_installer_renders_with_no_database_and_database_session_driver`: `config(['session.driver' => 'database', 'cache.default' => 'database'])`, `Schema::drop('settings')`, `Schema::drop('sessions')`, then `get('/install')->assertOk()`. If a `web` middleware throws, fix it (wrap in `rescue`/skip when not installed) in this task.
  - `test_drivers_are_untouched_once_installed`: `installPlatform()`, `config(['session.driver' => 'database'])`, `get('/')`, assert `config('session.driver') === 'database'`.
  - `test_unwritable_env_returns_a_readable_500`: bind a `DatabaseSetup` whose `prepareEnvironment` throws `InstallerException('Make .env writable')`, then `get('/install')->assertStatus(500)->assertSee('Make .env writable')`.
- [ ] **Step 2:** Run, expect FAIL.
- [ ] **Step 3:** Implement and register.
- [ ] **Step 4:** Run this test plus `--filter=Install` and `--filter=ExampleTest`, expect PASS.

### Task 5: Controller, requests and routes for both flows

**Files:**
- Modify: `app/Http/Controllers/Install/InstallController.php`
- Create: `app/Http/Requests/Install/DatabaseRequest.php` (rules: `host` required string max 255, `port` required integer 1–65535, `database` required string max 64, `username` required string max 80, `db_password` nullable string max 255, `setup_token` required string max 128)
- Modify: `app/Http/Requests/Install/InstallRequest.php`: `setup_token` and `database_password` become `nullable|string|max:255`; the controller decides which proof is required.
- Modify: `routes/install.php`:
  - `POST /install/database/test` → `testDatabase`, name `install.database.test`, `throttle:10,1`
  - `POST /install/database` → `database`, name `install.database`, `throttle:5,1`
- Modify: `tests/Feature/Install/WebInstallerTest.php` only where props changed.
- Test: `tests/Feature/Install/AutoDeployInstallTest.php`, `tests/Feature/Install/ManualInstallTest.php`

**Interfaces:**
- Consumes: Tasks 1–3.
- `show()` adds props:
  - `mode` (`'auto'|'manual'`)
  - `step`: `'requirements'|'database'|'owner'`
    - Auto: `'owner'` when `databaseReady()`; otherwise `'requirements'`, with `databaseProblem` shown.
    - Manual: `'owner'` when `databaseReady() && session('install.verified')`; else `'database'` when requirements pass (manual requirements = `RequirementChecker::check()` minus the `database` and `migrations` checks, plus `pdo_mysql` loaded, `.env` writable or creatable, and `servedFromPublic`); else `'requirements'`.
  - `databaseProblem`
  - `proof`: `'session'` if `install.verified`, else `'database_password'` if `databasePasswordConfigured() !== ''`, else `'setup_token'`
  - `connectionTest` (flash: `{ok: bool, message: string}`)
  - `storageLinked` (flash)
  - `servedFromPublic`
- `testDatabase(DatabaseRequest)`: verify token, then `DatabaseSetup::test()`. Redirect back with flash `connectionTest`: ok → `"Connected to {MySQL|MariaDB} {version}"`, error → the exception message.
- `database(DatabaseRequest)`: abort 404 unless mode is Manual. Verify token (field error `setup_token`), check manual requirements, then `apply()`. On `InstallerException` the field error goes on `host`. On success: `session(['install.verified' => true])`, flash `storageLinked`, redirect to `install.show`.
- `store()`: proof check by `proof`. `database_password` compared with `hash_equals`; error key `database_password` → `'The database password is incorrect.'`. Then the existing requirements check (full `check()`, which needs the database ready), `InstallPlatform::handle()`, `session()->forget('install.verified')`, login, redirect `admin.dashboard`.

- [ ] **Step 1: Write failing tests**

`AutoDeployInstallTest` (RefreshDatabase; set `config(['database.connections.sqlite.password' => 'deploy-pass'])` where needed):
  - `test_auto_mode_shows_only_the_owner_step`: Inertia `mode` = `'auto'`, `step` = `'owner'`, `proof` = `'database_password'`.
  - `test_wrong_database_password_is_rejected`: POST `install.store` with `database_password => 'nope'`, assert session error `database_password`, `User::count() === 0`.
  - `test_empty_database_password_requires_the_setup_token`: no password configured → `proof` = `'setup_token'`; POST without token gets error `setup_token`.
  - `test_correct_database_password_installs_and_locks`: owner exists with the owner role, lock file exists, `assertAuthenticated()`, then `get('/install')->assertNotFound()`, and a second POST gets 404.
  - `test_forced_auto_with_pending_migrations_explains_the_problem`: `step` = `'requirements'`, `databaseProblem` = `'not_migrated'`.

`ManualInstallTest` (`config(['ecomodular.installer.mode' => 'manual'])`; temp env path; bind the Task 3 fake `DatabaseSetup`; requests use `withServerVariables(['SCRIPT_FILENAME' => public_path('index.php')])` unless testing the block):
  - `test_document_root_outside_public_blocks_the_database_step`: SCRIPT_FILENAME `base_path('index.php')` → `step` = `'requirements'`, `servedFromPublic` false; POST `install.database` gets error and `.env` is unchanged.
  - `test_database_step_requires_the_setup_token`.
  - `test_connection_test_reports_success_and_failure`: flash `connectionTest.ok` true with `'MySQL 8.0.36'`; fake throwing → ok false with message.
  - `test_database_step_writes_env_and_unlocks_owner_step`: `.env` has `DB_DATABASE=shop`, then `get('/install')` shows `step` = `'owner'`, `proof` = `'session'`.
  - `test_full_manual_flow_creates_the_owner`: the database step, then POST `install.store` with no proof fields → installed, lock file, owner logged in.
  - `test_database_step_is_404_in_auto_mode` (mode `detect`, migrated).
  - `test_database_step_is_404_after_installation`.
- [ ] **Step 2:** Run `--filter=AutoDeployInstallTest` and `--filter=ManualInstallTest`, expect FAIL.
- [ ] **Step 3:** Implement the controller, requests and routes.
- [ ] **Step 4:** Run both, plus `--filter=WebInstallerTest` and `--filter=InstallCommandTest`, expect PASS.

### Task 6: Wizard page

**Files:**
- Modify: `resources/js/pages/install/index.tsx` (split into `resources/js/pages/install/` components if it passes ~300 lines: `requirements-step.tsx`, `database-step.tsx`, `owner-step.tsx`)
- Modify: `resources/js/types` (add `InstallMode`, `InstallStep`, `InstallProof`)

**Interfaces:**
- Consumes: Task 5 props and the Wayfinder actions `InstallController.testDatabase`, `.database`, `.store` (regenerated by the Vite plugin on build).
- Step indicator: auto = "Requirements → Owner"; manual = "Requirements → Database → Owner".
- `databaseProblem` copy:
  - `not_configured`: "No database is configured. Set the DB_* variables."
  - `unreachable`: "The configured database cannot be reached."
  - `not_migrated`: "Migrations have not run. Add `php artisan migrate --force` to your deploy script."
- Document-root block copy: "This site is served from the project folder. Point your domain's document root at the `public/` folder in your control panel, then reload."
- Database form fields: `host` (default `localhost`), `port` (default `3306`), `database`, `username`, `db_password`, `setup_token`, with "Test connection" (posts to `testDatabase`, `preserveState`) and "Save and create tables". `resetOnError` covers `db_password` and `setup_token`.
- Owner form: proof field per `proof` (`database_password` labelled "Database password (from your hosting environment)"; `setup_token` with the token location; none for `session`).

- [ ] **Step 1:** Implement.
- [ ] **Step 2:** `export PATH="/c/php84:$PATH" && npm run build` succeeds, and `npx tsc --noEmit` passes for `resources/js/pages/install`.
- [ ] **Step 3:** Browser check with the dev server on localhost:8017 is not possible (that instance is installed). Verify instead with `ManualInstallTest` and `AutoDeployInstallTest`, which assert the Inertia props, and report that the page was not exercised in a browser.

### Task 7: Deployment guide and env example

**Files:**
- Create: `docs/installation/deployment-modes.md`
- Modify: `docs/installation/production.md` (link near "Build, migrate, install"; add `ECOMODULAR_INSTALL_MODE` and `ECOMODULAR_INSTALL_LOCK` to the env table)
- Modify: `.env.example` (add `ECOMODULAR_INSTALL_MODE=detect` beside `ECOMODULAR_WEB_INSTALLER`)

Content as in the spec's Documentation section: detection and forcing; auto-deploy env vars, deploy script, persistent `storage/`, queue and scheduler, the database-password step; cPanel and other panels (upload, document root `public/`, create the database and user in the panel, read the token with File Manager, run the wizard, add the scheduler cron `* * * * * php /home/USER/app/artisan schedule:run`). Also note that `php artisan ecomodular:install` remains the CLI path.

- [ ] **Step 1:** Write the docs.
- [ ] **Step 2:** Final verification: `php artisan test --filter=Install` plus `--filter=DatabaseSetupTest` all pass; `vendor/bin/pint --test` on the changed PHP files; `vendor/bin/phpstan analyse` on `app/Core/Installation app/Http/Controllers/Install app/Http/Middleware/PrepareInstallEnvironment.php`.
