# Core platform versioning and updates: design

Status: approved in conversation 2026-11-08.

## Goal

Modrix Commerce (this repository, "core") gets one visible version and can
update itself from Modrix Cloud: the admin checks for a newer core release,
reads its notes, and installs it. The install is verified, backed up, runs
under maintenance mode, migrates, rolls back on failure and is audited.
Modrix Cloud (separate app, `C:\Users\Eleydo Family\Documents\ChatGPT\ModrixCloud`)
hosts the releases.

## Decisions

| Question | Decision |
|---|---|
| Version source | `App\Core\Platform::VERSION` (already used by manifests and backups). No VERSION file. |
| Who may list / download | Listing is public, signed metadata. Download needs the store's Modrix Cloud **connection token** (bearer). Unconnected stores see the update and are told to connect. |
| Where the updater lives | Core: `app/Core/Updates/*`. Works when the Cloud Connector module is disabled or broken. |
| How install runs | Detached `php artisan ecomodular:core-update <run>` process; the page polls a status row. Also runnable over SSH. |
| What a release replaces | Core code only. Never `.env`, `storage/`, `modules/`, `themes/`, `public/storage`, `bootstrap/cache`, or database files (`database/` itself is not managed, only its code subfolders). Bundled extensions keep updating through Modrix Cloud. |
| Cloud server | Changes go into the ModrixCloud app only. The legacy in-repo `app/Domains/Marketplace` server is not touched. |

## 1. Version display (core)

- `Platform::VERSION` stays the single source of truth; releases bump it.
- Shared to Inertia as `platform.version` (HandleInertiaRequests).
- System status → Environment card: first row **Modrix Commerce `<version>`**,
  above Laravel and PHP.
- Admin sidebar footer: `v<version>` under the user menu.

## 2. Release packages (core)

`php artisan ecomodular:core-package [--output=path]` builds
`modrix-commerce-<version>.zip` from the current checkout:

- includes the managed paths (below) plus `vendor/` and the built
  `public/build/`; refuses when `vendor/autoload.php` or
  `public/build/manifest.json` is missing;
- excludes `.env*` (except `.env.example`), `storage/`, `modules/`, `themes/`,
  `public/storage`, `bootstrap/cache/*.php`, `node_modules/`, `tests/`, `.git/`,
  and SQLite files anywhere (`*.sqlite`, `*.sqlite3`, `*.db`, `*-journal`,
  `*-wal`, `*-shm`); extraction refuses a package that contains one;
- entries are relative to the app root (no wrapping folder).

**Managed paths** (config `ecomodular.updates.paths`, the only paths an
install replaces): `app`, `bootstrap/app.php`, `bootstrap/providers.php`,
`config`, `database/migrations`, `database/seeders`, `database/factories`,
`lang`, `public/build`, `public/index.php`, `resources`, `routes`, `vendor`,
`artisan`, `composer.json`, `composer.lock`, `package.json`. Not `database`
as a whole: it holds the live SQLite file (`database/database.sqlite`, the
`.env.example` default). Paths missing from the package are left alone
(never deleted).

## 3. Modrix Cloud (ModrixCloud app)

### Data

- `core_releases`: id, version (unique), notes (text), min_php (string),
  path, sha256, size, status (`draft` | `published` | `withdrawn`),
  signed_metadata (json, nullable), signature (nullable), published_at,
  timestamps.
- `core_download_tokens`: token_hash (PK), core_release_id,
  store_connection_id, instance_id, expires_at, used_at, timestamps.
  Separate from license `download_tokens`, which stay unchanged.

### Service `Marketplace\Services\CoreReleases`

- `upload(UploadedFile|path, version, notes, minPhp)`: version must be valid
  semver and unused; min_php like `8.3` or `8.3.0`. The zip is checked: no
  absolute / `..` / backslash entries, contains `artisan`,
  `vendor/autoload.php` and `app/Core/Platform.php`, and that file declares
  `VERSION = '<version>'`; no SQLite database files (same patterns as the
  packager). Size cap `marketplace.core_max_mb` (default 200).
  Stored at `<marketplace.storage>/core/<version>.zip` as a draft.
- `publish(release)`: drafts only (`Only draft releases can be published.`);
  re-runs the upload checks on the stored file, re-hashes it and signs with
  `SigningKey::sign`:
  `{kind: "core", release_id, version, min_php, sha256, size, key_id}`.
- `withdraw(release)`: from `draft` or `published` only; status `withdrawn`;
  it is no longer listed or downloadable.
- `newerThan(?current, ?php)`: published releases with version > current
  and min_php ≤ php, newest first.

### Admin

`/admin/core` (EnsureAdmin), linked in the admin navigation: upload form
(version, release notes, minimum PHP, zip), table of releases with status,
size, sha256 prefix, Publish and Withdraw buttons.

### API (`/api/marketplace/v1`, throttled like the rest)

- `GET core/releases?current=<v>&php=<v>` (public)
  ```json
  {"releases": [{"id": 3, "version": "1.1.0", "min_php": "8.3",
    "notes": "…", "published_at": "2026-11-08T10:00:00+00:00",
    "verification": {"metadata": {"kind": "core", "release_id": 3,
      "version": "1.1.0", "min_php": "8.3", "sha256": "…", "size": 12345,
      "key_id": "…"}, "signature": "…"}}]}
  ```
- `POST core/downloads {release_id, instance_id}` with
  `Authorization: Bearer <connection token>`.
  No / revoked token → 401 `{"error": "not_connected"}`; unknown or not
  published release → 404 `release_not_found`. Success:
  `{"url": ".../api/marketplace/v1/core/download/<64 chars>", "expires_at": "…", "release": {…same item…}}`.
- `GET core/download/{token}`: streams the zip once (410 `link_expired`
  when used, expired or the release was withdrawn).

The JSON above is the contract both test suites pin.

## 4. Store updater (core, `app/Core/Updates`)

- **`TrustedKeys`** (core): the official public key (moved from
  `CloudClient::TRUSTED_KEY`) plus `ecomodular.marketplace.trusted_keys`.
  The Cloud Connector's `trustedKeys()` delegates to it.
- **`CoreUpdateClient`**: base URL `ecomodular.marketplace.url`; reads the
  connection token from `SecretSettings` key `cloud.connection_token` and
  the instance id from setting `marketplace.instance_id` (same keys the
  connector uses). `releases()`, `requestDownload()`, `download()` (streams,
  stops past the signed size, same rules as the connector).
- **`CoreUpdateCheck`**: calls `releases(current, PHP_VERSION)`, keeps only
  items whose signature verifies, `kind` is `core`, version > current and
  `min_php` ≤ `PHP_VERSION`; stores the newest in setting
  `core_update.available` with `checked_at`, or null. Errors are stored as
  `core_update.last_error`, never thrown to the page.
  Command `ecomodular:core-check`, scheduled daily; also "Check now".
- **`core_update_runs`** table: id, from_version, to_version, release (json,
  the verified metadata + notes), status (`queued`, `downloading`,
  `verifying`, `backing_up`, `installing`, `migrating`, `completed`,
  `failed`, `rolled_back`), message, backup_name, code_backup_path,
  needs_attention (bool: a `failed` run whose rollback could not undo
  everything), actor_id, timestamps.
- **`CoreUpdater::run(CoreUpdateRun)`** (command `ecomodular:core-update {run}`):
  1. cache lock `ecomodular:core-update`; one run at a time;
  2. re-verify the stored metadata (signature, kind, version > current,
     min_php);
  3. request a download link (needs the connection), download, compare size
     and sha256;
  4. extract into `storage/app/private/core-updates/tmp/<random>` with safe
     entry checks (every write checked, entry sizes must match); the
     extracted `app/Core/Platform.php` must declare the signed version and
     `vendor/autoload.php` must exist; refuse when the resolved
     `database.connections.sqlite.database` file lies inside a managed path;
  5. zip the current managed paths to
     `storage/app/private/core-updates/core-<old version>-<timestamp>.zip`
     (slow, no downtime yet);
  6. `artisan down` (maintenance mode, with a secret bypass);
  7. database backup with `Backups::create(withFiles: false)`, taken in
     maintenance mode so no write can follow it (the update never touches
     storage, modules or themes; code is covered by step 5);
  8. per managed path: rename current to `<path>.old-<rand>`, move the new
     one in;
  9. `artisan migrate --force` and `optimize:clear` in a fresh PHP process
     from the updates root (so the new code runs);
  10. `artisan up` (if it fails, delete `storage/framework/down` and say so
      in the message); delete the `.old-*` paths; clear
      `core_update.available` when it is not newer than the installed
      version; status `completed`.

  On failure before step 8: nothing changed; status `failed`. On failure in
  steps 8–9: move the `.old-*` paths back, restore the database from the
  step 7 backup (`Backups::restore` database part), `artisan up`, status
  `rolled_back`; if that undo has problems, status `failed` with
  `needs_attention`. Maintenance mode is always lifted in a `finally`.
  A run that loses the cache lock is failed only while it is still `queued`.
  Every outcome writes `UpdateHistory` (type `core`, extension_name
  `modrixcommerce/core`, action `update`) and audit `core.update_completed`,
  `core.update_failed` or `core.update_rolled_back`.
- **Updates root** `ecomodular.updates.root` (default `base_path()`) and the
  PHP CLI binary `ecomodular.updates.php` (default empty: `PHP_BINARY` from
  the CLI, otherwise the CLI found by `PhpExecutableFinder` / `PHP_BINDIR`,
  never php-fpm or php-cgi); tests point the
  root at a scratch tree and run step 9 in-process through a seam
  (`CoreUpdater::$runArtisan`, a closure, default spawns the process).
- **Starting from admin**: `POST admin/system/core-update` (permission
  `system.update`, new, granted to the owner role, plus `password.confirm`
  like extension installs) creates a `queued` run and starts the detached
  command with a short synchronous shell call that returns at once
  (`sh -c 'setsid nohup … &'`, without setsid when missing; Windows
  `cmd /c start "" /B …`), so no process handle outlives the request.
  `GET admin/system/core-update/{run}` returns its status (and a `hint` when
  a run is still `queued` after 60 s: "The update did not start. Run php
  artisan ecomodular:core-update <id> on the server.") for polling.
- **System status page**: new "Core platform" card: current version,
  available version + notes + min PHP, "Check now", "Install update"
  (or "Connect to Modrix Cloud to download" when not connected, linking to
  the connector page), last error, and the last 5 runs. Only an available
  version newer than `Platform::VERSION` is offered. While a run is active,
  installers see the maintenance bypass link (`/<secret>`), which the poller
  also uses on a 503. A `needs_attention` run is shown as a red alert.
- **HealthCheck**: new check "Core updates": failed when a `needs_attention`
  run is newer than the last completed one; warn when a non-terminal run has
  not changed for 10 minutes; warn when the last run `rolled_back`; warn
  "Modrix Commerce X is available" (newer than installed only); ok
  "Up to date (checked …)".

## 5. Testing

ModrixCloud (`tests/Feature/CoreReleasesTest.php`): upload validation
(bad zip, wrong declared version, duplicate), publish signs verifiable
metadata, list filters by current/php/status, download needs a connection,
the link is single-use, withdrawn releases are not served.

ecomodular:
- `CorePackageCommandTest`: zip includes managed paths, excludes the
  forbidden ones.
- `CoreUpdateCheckTest`: stores a verified newer release; ignores bad
  signatures, `kind` ≠ core, older versions and too-high min_php.
- `CoreUpdateEndToEndTest`: builds a release zip for a scratch app tree
  (new `Platform.php`, one new migration), signs metadata with a test key
  added to trusted keys, `Http::fake` serves the contract JSON and the zip;
  check → start run → `CoreUpdater::run` → asserts the new `Platform.php` is
  in the scratch root, the migration ran, `UpdateHistory` and audit entries,
  maintenance mode is off, run `completed`.
- `CoreUpdateFailureTest`: checksum mismatch leaves files untouched
  (`failed`); a failing migration restores code and database
  (`rolled_back`); no connection → `failed` with the connect message.
- `SystemStatusTest`: shows the version, the available update and the card.

## Out of scope

Release channels (beta/stable), delta packages, automatic installs,
updating bundled modules/themes with core, multi-server deployments.
