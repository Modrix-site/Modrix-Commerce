# Core Platform Updates Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show the Modrix Commerce version in admin and let a store check, download, verify and install core releases published on Modrix Cloud, with backup, maintenance mode, migrations, rollback and audit.

**Architecture:** Two apps. ModrixCloud (`C:\Users\Eleydo Family\Documents\ChatGPT\ModrixCloud`, namespace `Marketplace\`) stores core releases, signs them with `SigningKey`, lists them publicly and serves single-use downloads to connected stores. ecomodular (`C:\Users\Eleydo Family\Documents\ChatGPT\ecommodular`, this repo) gets `app/Core/Updates/*`: a client, a check, a detached installer, a packaging command and a System status card.

**Tech Stack:** Laravel 13, PHP 8.4 (`export PATH="/c/php84:$PATH"`), Inertia React (ecomodular admin), Blade (ModrixCloud admin), libsodium Ed25519 via the existing `Signing` classes, composer/semver, ZipArchive, PHPUnit on SQLite.

**Spec:** `docs/superpowers/specs/2026-11-08-core-updates-design.md`

## Global Constraints

- Version source of truth: `App\Core\Platform::VERSION`; no VERSION file.
- Signed core metadata keys exactly: `kind` (= `"core"`), `release_id`, `version`, `min_php`, `sha256`, `size`, `key_id`.
- API base `/api/marketplace/v1`: `GET core/releases?current=&php=`, `POST core/downloads {release_id, instance_id}` (Bearer connection token; 401 `not_connected`, 404 `release_not_found`), `GET core/download/{token}` (single use, 410 `link_expired`), link valid `marketplace.download_minutes` (10).
- Store reads connection token from `SecretSettings` key `cloud.connection_token` and instance id from setting `marketplace.instance_id`.
- Managed paths (config `ecomodular.updates.paths`): `app`, `bootstrap/app.php`, `bootstrap/providers.php`, `config`, `database`, `lang`, `public/build`, `public/index.php`, `resources`, `routes`, `vendor`, `artisan`, `composer.json`, `composer.lock`, `package.json`. Never touch `.env`, `storage/`, `modules/`, `themes/`, `public/storage`, `bootstrap/cache`.
- History: `UpdateHistory` with `type` `core`, `extension_name` `modrixcommerce/core`, `action` `update`. Audit actions: `core.update_completed`, `core.update_failed`, `core.update_rolled_back`.
- New permission `system.update` ("Install core updates"), granted to the owner role.
- Settings keys: `core_update.available` (array|null), `core_update.checked_at`, `core_update.last_error`.
- Tests run serially (no `--parallel`). Don't commit unless the user asks (no remote). In ecomodular touch only core/admin/marketplace files, not themes.

## Review Focus

1. Zip entries with `../`, absolute paths or backslashes (zip-slip) in a release, on both the cloud upload and the store extract → refused. Tests: Task 2 (`test_upload_refuses_unsafe_entries`), Task 7 (`test_unsafe_entry_is_refused_before_any_change`).
2. A download larger than the signed size, or a different sha256 → `failed`, nothing on disk changed. Test: Task 7 (`test_checksum_mismatch_leaves_files_untouched`).
3. Signature valid but metadata `version` ≠ extracted `Platform.php` version (cloud re-labelling) → refused. Test: Task 7 (`test_version_mismatch_inside_package_is_refused`).
4. Crash mid-install leaving maintenance mode on → `artisan up` always runs in `finally`. Test: Task 7 (`test_failed_migration_rolls_back_code_and_database` asserts `app()->isDownForMaintenance()` is false).
5. Two installs started at once (double-click) → second run is refused by the lock / an active run. Test: Task 8 (`test_second_install_is_refused_while_one_is_running`).

---

## Part A — ModrixCloud (run commands in `C:\Users\Eleydo Family\Documents\ChatGPT\ModrixCloud`)

### Task 1: Core release storage and signing service

**Files:**

- Create: `database/migrations/2026_11_08_000001_create_core_releases.php` (`core_releases`, `core_download_tokens` per spec §3 Data)
- Create: `app/Models/CoreRelease.php`, `app/Models/CoreDownloadToken.php` (PK `token_hash`, non-incrementing, like `DownloadToken`)
- Create: `app/Services/CoreReleases.php`, `app/Services/CoreReleaseException.php`
- Modify: `config/marketplace.php` (add `'core_max_mb' => (int) env('MARKETPLACE_CORE_MAX_MB', 200)`)
- Modify: `tests/Support/Fixtures.php` (add `coreZip(string $version, array $extra = []): UploadedFile` containing `artisan`, `vendor/autoload.php`, `app/Core/Platform.php` with `public const VERSION = '<version>';`)
- Test: `tests/Feature/CoreReleasesTest.php`

**Interfaces:**

- Produces:
    - `CoreRelease` constants `DRAFT='draft'`, `PUBLISHED='published'`, `WITHDRAWN='withdrawn'`; casts `signed_metadata` array, `published_at` datetime, `size` int.
    - `CoreReleases::upload(string $zipPath, string $version, ?string $notes, string $minPhp): CoreRelease` (throws `CoreReleaseException` with a user message)
    - `CoreReleases::publish(CoreRelease $r): CoreRelease`, `withdraw(CoreRelease $r): void`
    - `CoreReleases::newerThan(?string $current, ?string $php): Collection<CoreRelease>` (published, newest first)
    - `CoreReleases::payload(CoreRelease $r): array` — `{id, version, min_php, notes, published_at, verification: {metadata, signature}}`
    - `CoreReleases::path(CoreRelease $r): string`

- [ ] **Step 1: Write failing tests** in `CoreReleasesTest` (RefreshDatabase, Fixtures):
    - `test_upload_stores_a_draft` — `upload(coreZip('1.1.0'), '1.1.0', 'Notes', '8.3')` → status `draft`, sha256 = `hash_file` of stored file, file exists at `path()`.
    - `test_upload_refuses_wrong_declared_version` — zip says `1.0.9`, version `1.1.0` → `CoreReleaseException`.
    - `test_upload_refuses_missing_vendor_autoload`, `test_upload_refuses_duplicate_version`, `test_upload_refuses_invalid_semver` (`'abc'`), `test_upload_refuses_unsafe_entries` (extra entry `../evil.php`).
    - `test_publish_signs_verifiable_metadata` — after `publish`, `Signing::verify($r->signed_metadata, $r->signature, [app(SigningKey::class)->publicKey()])` true; metadata `kind` = `core`, `min_php` = `8.3`, `release_id` = id.
    - `test_newer_than_filters` — published 1.1.0 (min 8.3), 1.2.0 (min 9.0), draft 1.3.0, withdrawn 1.0.5: `newerThan('1.0.0','8.4.1')` → [`1.1.0`]; `newerThan(null, null)` → [`1.2.0`,`1.1.0`].
- [ ] **Step 2:** `php artisan test --filter=CoreReleasesTest` → FAIL (class not found).
- [ ] **Step 3:** Implement migration, models, service. Declared-version check: read `app/Core/Platform.php` from the zip, regex `/const\s+VERSION\s*=\s*'([^']+)'/`. Semver validity via `Composer\Semver\VersionParser::normalize` in try/catch. Store at `<marketplace.storage>/core/<version>.zip`. `min_php` must match `/^\d+\.\d+(\.\d+)?$/`; compare with `version_compare`.
- [ ] **Step 4:** Rerun → PASS.

### Task 2: Admin "Core releases" page

**Files:**

- Create: `app/Http/Controllers/Admin/CoreReleaseController.php` (`index`, `store`, `publish`, `withdraw`)
- Create: `resources/views/admin/core.blade.php` (follow `admin/updates.blade.php` style)
- Modify: `routes/web.php` inside the `EnsureAdmin` group: `GET core` (`admin.core`), `POST core`, `POST core/{release}/publish`, `POST core/{release}/withdraw`
- Modify: `resources/views/layouts/admin.blade.php` (nav link "Core releases")
- Test: `tests/Feature/Pages/AdminCoreReleasesTest.php`

**Interfaces:** Consumes Task 1 `CoreReleases`. Store request: `version` required string max 32, `notes` nullable string max 20000, `min_php` required, `package` required file `mimes:zip` max `core_max_mb*1024`.

- [ ] **Step 1: Failing tests:** `test_admin_uploads_and_publishes_a_core_release` (POST with `coreZip('1.1.0')` → redirect, row exists; POST publish → status published; GET `/admin/core` shows `1.1.0`), `test_customer_cannot_open_core_releases` (403 or redirect to admin login, matching how `EnsureAdmin` answers), `test_upload_error_is_shown` (wrong declared version → session error on `package`).
- [ ] **Step 2:** Run → FAIL. **Step 3:** Implement; catch `CoreReleaseException` → `back()->withErrors(['package' => $e->getMessage()])`. **Step 4:** Run → PASS.

### Task 3: Core release API

**Files:**

- Create: `app/Http/Controllers/Api/CoreReleaseController.php` (`index`, `request`, `download`)
- Modify: `routes/api.php` (`core/releases` → `api.core.releases`, `core/downloads` → `api.core.downloads` throttle 20,1, `core/download/{token}` → `api.core.download`)
- Test: `tests/Feature/Api/CoreReleaseApiTest.php`

**Interfaces:** Consumes `CoreReleases::newerThan/payload/path`, `Connections::authenticate(?string $token): ?StoreConnection`, `ApiError::validate/response`. Response JSON exactly per spec §3 API.

- [ ] **Step 1: Failing tests:**
    - `test_lists_newer_published_releases` — `GET /api/marketplace/v1/core/releases?current=1.0.0&php=8.4.0` → `releases.0.version` = `1.1.0`, `releases.0.verification.metadata.kind` = `core`.
    - `test_download_requires_a_connection` — no bearer → 401 `error=not_connected`; revoked connection → 401.
    - `test_connected_store_downloads_once` — use the connection helper pattern from `ConnectionApiTest::connected()`; POST → `url`; GET url → 200, body sha256 = release sha256; GET again → 410 `link_expired`.
    - `test_withdrawn_release_is_not_served` — link issued, then withdraw → GET 410; POST for withdrawn → 404 `release_not_found`.
- [ ] **Step 2:** Run → FAIL. **Step 3:** Implement (token claim in a transaction with `lockForUpdate`, like `DownloadController::download`). **Step 4:** Run → PASS; also run full ModrixCloud suite `php artisan test` → no new failures.

---

## Part B — ecomodular (this repo)

### Task 4: Version display

**Files:**

- Modify: `app/Http/Middleware/HandleInertiaRequests.php` (share `'platform' => ['version' => Platform::VERSION]`)
- Modify: `app/Http/Controllers/Admin/SystemStatusController.php` (first environment row `['label' => 'Modrix Commerce', 'value' => Platform::VERSION]`)
- Modify: `resources/js/components/app-sidebar.tsx` (footer text `v{platform.version}` under `NavUser`, muted, hidden when collapsed); `resources/js/types` shared props type
- Test: `tests/Feature/Admin/CoreVersionTest.php`

- [ ] **Step 1: Failing test** `test_version_is_shown_in_system_status_and_shared` — owner GET `admin.system.status` → `assertInertia` has `environment.0.label` = `Modrix Commerce`, `environment.0.value` = `Platform::VERSION`, and `platform.version` = `Platform::VERSION`.
- [ ] **Step 2:** Run `php artisan test --filter=CoreVersionTest` → FAIL. **Step 3:** Implement. **Step 4:** PASS; `npm run build` succeeds.

### Task 5: Trusted keys in core, update client and config

**Files:**

- Create: `app/Core/Marketplace/TrustedKeys.php` — `TrustedKeys::OFFICIAL` (value moved from `CloudClient::TRUSTED_KEY`), `static all(): list<string>` (official + `ecomodular.marketplace.trusted_keys`, unique)
- Modify: `modules/modrixcommerce/cloud-connector/src/CloudClient.php` — `TRUSTED_KEY = TrustedKeys::OFFICIAL`; `trustedKeys()` returns `TrustedKeys::all()`
- Create: `app/Core/Updates/CoreUpdateClient.php`, `app/Core/Updates/CoreUpdateError.php` (`RuntimeException`, `bool $notConnected`)
- Modify: `config/ecomodular.php` — `'updates' => ['root' => env('ECOMODULAR_UPDATES_ROOT', base_path()), 'php' => env('ECOMODULAR_UPDATES_PHP', PHP_BINARY), 'paths' => [...Global Constraints list], 'max_mb' => 300]`
- Test: `tests/Feature/Updates/CoreUpdateClientTest.php`

**Interfaces — Produces:**

- `CoreUpdateClient::connected(): bool`
- `CoreUpdateClient::releases(string $current, string $php): list<array>` (raw items; throws `CoreUpdateError`)
- `CoreUpdateClient::requestDownload(int $releaseId): array{url: string}` (throws `CoreUpdateError(notConnected: true)` without a token or on 401)
- `CoreUpdateClient::download(string $url, string $target, int $expectedSize): void` (same rules as `CloudClient::download`: URL must start with base URL, cap `updates.max_mb`, exact size)

- [ ] **Step 1: Failing tests** with `Http::fake`: `test_lists_releases`, `test_download_request_without_connection_is_refused` (no HTTP call made: `Http::assertNothingSent()`), `test_download_refuses_foreign_host`, `test_download_refuses_oversize_body`, `test_bearer_token_is_sent` (`Http::assertSent` with `Authorization: Bearer <token>`).
- [ ] **Step 2:** FAIL. **Step 3:** Implement. **Step 4:** PASS; `php artisan test tests/Feature/Marketplace/CloudConnectionTest.php tests/Feature/Marketplace/MarketplaceClientTest.php` still pass.

### Task 6: Update check, schedule and health

**Files:**

- Create: `app/Core/Updates/CoreReleaseVerifier.php` — `verify(array $item): ?array` returns metadata + `notes`/`published_at` when signature (via `TrustedKeys::all()`), `kind`, sha256 format, int size, version > `Platform::VERSION` and `version_compare(PHP_VERSION, min_php, '>=')` all hold; else null. Takes current version as optional param `verify(array $item, ?string $current = null)` for tests.
- Create: `app/Core/Updates/CoreUpdateCheck.php` — `run(): ?array` stores `core_update.available` (newest verified), `core_update.checked_at`, clears/sets `core_update.last_error`; `available(): ?array`
- Create: `app/Console/Commands/CoreCheckCommand.php` (`ecomodular:core-check`)
- Modify: `routes/console.php` (`Schedule::command('ecomodular:core-check')->daily()->withoutOverlapping()->onOneServer()`)
- Modify: `app/Core/Operations/HealthCheck.php` (new `coreUpdates()` in `run()`: label `Core updates`; OK `Up to date`; WARNING `Modrix Commerce X is available`; WARNING when latest `core_update_runs` row is `rolled_back`; guard `Schema::hasTable`)
- Test: `tests/Feature/Updates/CoreUpdateCheckTest.php`

Test helper (shared with Tasks 7–8): `tests/Support/CoreReleaseFixtures.php` trait — generates a key pair with `Signing::generateKeyPair()`, sets `ecomodular.marketplace.trusted_keys`, `signedItem(string $version, string $sha256, int $size, string $minPhp = '8.3', array $override = []): array` in the spec's contract shape.

- [ ] **Step 1: Failing tests:** `test_check_stores_newest_verified_release`, `test_check_ignores_bad_signature`, `test_check_ignores_non_core_kind`, `test_check_ignores_older_version`, `test_check_ignores_too_high_min_php` (`min_php` `99.0`), `test_check_records_error_when_cloud_is_down` (`Http::fake` 500 → `last_error` set, no exception), `test_health_warns_when_update_is_available`.
- [ ] **Step 2:** FAIL. **Step 3:** Implement. **Step 4:** PASS.

### Task 7: Packaging command and the installer

**Files:**

- Create: `database/migrations/2026_11_08_000001_create_core_update_runs_table.php`
- Create: `app/Core/Updates/CoreUpdateRun.php` (model, status constants per spec, `release` json cast, `isActive(): bool` for non-terminal statuses)
- Create: `app/Core/Updates/CorePackage.php` — `build(string $root, string $output): string` (managed paths, exclusions per spec §2); `static safeEntry(string $name): bool`
- Create: `app/Console/Commands/CorePackageCommand.php` (`ecomodular:core-package {--output=}`)
- Create: `app/Core/Updates/CoreUpdater.php`
- Create: `app/Console/Commands/CoreUpdateCommand.php` (`ecomodular:core-update {run}`)
- Test: `tests/Feature/Updates/CorePackageCommandTest.php`, `tests/Feature/Updates/CoreUpdaterTest.php`

**Interfaces — Produces:**

- `CoreUpdater::run(CoreUpdateRun $run): CoreUpdateRun` (never throws; terminal status `completed|failed|rolled_back`)
- `public ?Closure $runArtisan` — `fn (string $root, array $args): int`; default runs `[config('ecomodular.updates.php'), $root.'/artisan', ...$args]` via `Process::path($root)->timeout(1800)->run()`. Tests set it to call `Artisan::call` with `--path`/`--realpath` for the scratch `database/migrations`.
- Consumes: `CoreUpdateClient`, `CoreReleaseVerifier`, `Backups::create(withFiles: false)` / `Backups::restore`-equivalent database import, `AuditLogger::record`, `UpdateHistory`.

Algorithm per spec §4 steps 1–10. Rollback restores database by importing the step-5 dump: add `Backups::restoreDatabase(string $path): void` (extract + `importDatabase`, no files, no migrate) — small public method in `app/Core/Operations/Backups.php`. Maintenance mode via `$this->runArtisan($root, ['down', '--secret=...'])` in production; in tests the closure calls `Artisan::call('down')`; `up` always in `finally`.

- [ ] **Step 1: Failing tests** (scratch root under `storage/framework/testing/core-root-<rand>`, config `ecomodular.updates.root` → scratch, containing every managed path with an old `app/Core/Platform.php` at `1.0.0` and a marker file):
    - `CorePackageCommandTest::test_package_contains_managed_paths_only` — scratch root also has `.env`, `storage/x`, `modules/a/module.json`, `themes/b/theme.json`, `node_modules/y`, `tests/z`; zip lists `app/Core/Platform.php`, `vendor/autoload.php`, `artisan`; none of the excluded.
    - `test_installs_a_verified_release` — release zip has `Platform.php` at `1.1.0` + migration `database/migrations/2026_12_01_000000_create_core_probe_table.php`; `Http::fake` for `core/downloads` and the URL → zip bytes. Asserts: run `completed`; scratch `Platform.php` contains `1.1.0`; `Schema::hasTable('core_probe')`; `UpdateHistory` row (type `core`, success true, from `1.0.0`, to `1.1.0`); audit `core.update_completed`; no `.old-*` left; maintenance off; `.env`/`storage` marker untouched.
    - `test_checksum_mismatch_leaves_files_untouched` → `failed`, scratch `Platform.php` still `1.0.0`, no backup taken.
    - `test_version_mismatch_inside_package_is_refused` → signed `1.1.0`, zip declares `1.2.0` → `failed`.
    - `test_unsafe_entry_is_refused_before_any_change` → zip with `../x.php` → `failed`, file outside root not created.
    - `test_failed_migration_rolls_back_code_and_database` → migration throws after creating a table; asserts `rolled_back`, `Platform.php` back to `1.0.0`, table absent, a pre-existing row still present, maintenance off, audit `core.update_rolled_back`.
    - `test_not_connected_fails_with_connect_message` → `failed`, message contains `Connect`.
- [ ] **Step 2:** `php artisan test tests/Feature/Updates` → FAIL. **Step 3:** Implement. **Step 4:** PASS.

### Task 8: Admin endpoints, permission and System status card

**Files:**

- Modify: `app/Core/Authorization/CorePermissions.php` (`system.update` => `Install core updates`, owner role)
- Create: `app/Http/Controllers/Admin/CoreUpdateController.php` — `check` (POST `admin/system/core-update/check`, `can:system.view`), `start` (POST `admin/system/core-update`, `can:system.update`), `show` (GET `admin/system/core-update/{run}`, JSON `{status, message, to_version}`)
- Create: `app/Core/Updates/CoreUpdateLauncher.php` — `launch(CoreUpdateRun $run): void` starts `ecomodular:core-update {id}` detached (`Process::start`; Windows `cmd /c start /B`). Bound in container so tests swap it with a fake that calls `CoreUpdater::run` synchronously.
- Modify: `routes/admin.php` next to `system` route; `SystemStatusController` (prop `coreUpdate`: `{current, available, checked_at, last_error, connected, can_install, connect_url, runs: last 5}`)
- Modify: `resources/js/pages/admin/system/status.tsx` — "Core platform" card per spec; polls `show` every 3 s while a run is active
- Test: `tests/Feature/Updates/CoreUpdateAdminTest.php`, plus `tests/Feature/Updates/CoreUpdateEndToEndTest.php`

- [ ] **Step 1: Failing tests:**
    - `test_status_page_shows_available_update` — setting `core_update.available` → `coreUpdate.available.version` = `1.1.0`.
    - `test_staff_without_permission_cannot_install` → 403.
    - `test_second_install_is_refused_while_one_is_running` — an existing `downloading` run → POST start → session error, no new run.
    - `CoreUpdateEndToEndTest::test_check_then_install_from_admin` — `Http::fake` serves `core/releases` (signed item built from a real zip), `core/downloads`, the download URL; owner POST check → available; POST start (fake launcher runs synchronously) → GET show → `completed`; scratch `Platform.php` at new version; history + audit present.
- [ ] **Step 2:** FAIL. **Step 3:** Implement. **Step 4:** PASS; `npm run build` ok; open `/admin/system` on localhost:8017 and check the card and version render.

### Task 9: Docs and full verification

**Files:** Create `docs/operations/core-updates.md` (how to package, upload, publish, check, install, roll back manually from `storage/app/private/core-updates/*.zip`).

- [ ] Run ecomodular `php artisan test tests/Feature/Updates tests/Feature/Admin/CoreVersionTest.php tests/Feature/Operations tests/Feature/Marketplace` → all pass.
- [ ] Run full ecomodular suite serially; compare failures with the ~58 pre-existing environmental ones (no new names).
- [ ] Run full ModrixCloud suite → pass.
- [ ] Ask the user before committing either repo.
