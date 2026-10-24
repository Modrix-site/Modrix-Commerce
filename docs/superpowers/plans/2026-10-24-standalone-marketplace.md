# Standalone Marketplace Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild `marketplace-catalog/` as a standard Laravel 13 app with migrations, Eloquent models, the unchanged store license API, a Stripe webhook, a legacy importer, Blade pages and an install command, deployable on ordinary hosting.

**Architecture:** New code lives in `app/` (namespace `Marketplace\`), `database/migrations`, `routes/{web,api}.php` and `resources/views`. During the build the old catch-all route to `src/front.php` stays registered *last* as a fallback; the cut-over task removes it and `src/` once every page and endpoint has a Laravel route. Data moves from the old `storage/catalog.sqlite` with an import command at cut-over.

**Tech Stack:** Laravel 13, PHP ≥ 8.3, composer/semver, PHPUnit 12, Blade + plain CSS, ext-sodium, MySQL/MariaDB or SQLite.

**Spec:** `docs/superpowers/specs/2026-10-24-standalone-marketplace-design.md`

## Global Constraints

- All paths below are relative to `marketplace-catalog/`; run commands from there with `export PATH="/c/php84:$PATH"`.
- The store API contract table in the spec is fixed: same paths, request fields, token fields, error shape `{error, message}`.
- Signing: copy `app/Core/Marketplace/Signing.php` from the store platform byte-for-byte into `app/Services/Signing.php` (namespace `Marketplace\Services`); never change `canonical()`.
- Token `valid_until` = issued + 30 days; download tokens live 10 minutes and are single use; API rate limit 60/min per IP.
- Stripe webhook tolerance 300 seconds; events recorded once per (provider, event_id).
- Secrets (Stripe secret key, webhook secret, SMTP password, Ed25519 secret key) stored with `Crypt::encryptString` in `settings`; never rendered back in HTML.
- Uploads: images PNG/JPEG/WebP ≤ 3 MB (no SVG); ZIP ≤ 20 MB compressed / 100 MB unpacked, manifest at root, identifier+version must match.
- No npm/Node at runtime; CSS/JS are static files in `public/`.
- Tests use SQLite in memory; Stripe HTTP is faked with `Http::fake()`; mail with `Mail::fake()`.
- Commits end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

- A key already active on store A, activated from store B → `409 license_in_use`, store A unaffected (Task 4 test).
- The same Stripe event delivered twice, or a refund before the completion event → one license, no crash (Task 6 tests).
- A download token reused, expired, or for another license's release → 404/410 and no bytes (Task 5 test).
- Legacy import run twice → no duplicates; legacy license keys and the signing key still verify (Task 7 test).
- MySQL strictness: string lengths and indexes valid on MySQL 5.7/MariaDB 10.3 (191-char indexed strings) (Task 2 note + `docs/DEPLOY.md`).

---

### Task 1: Laravel skeleton and test harness

**Files:**
- Modify: `composer.json` (require `ext-sodium`; require-dev `phpunit/phpunit:^12`, `fakerphp/faker`, `mockery/mockery`; autoload-dev `Tests\\` → `tests/`)
- Create: `config/{app,auth,cache,filesystems,logging,mail,queue,session,services,marketplace}.php`, `phpunit.xml`, `tests/TestCase.php`, `tests/Feature/HealthTest.php`
- Modify: `.env.example` (APP_URL, DB_CONNECTION=sqlite, DB_DATABASE, SESSION_DRIVER=database, CACHE_STORE=database, QUEUE_CONNECTION=database, MAIL_*)
- Modify: `bootstrap/app.php` — add `api: __DIR__.'/../routes/api.php'`; keep the `then:` catch-all but register it with `Route::fallback(...)` so new routes win.

**Interfaces:** Produces `config('marketplace.currency')` (default `USD`), `config('marketplace.storage')` (default `storage_path('app/private')`).

- [ ] **Step 1:** `tests/Feature/HealthTest.php::test_the_app_boots` — `$this->get('/up')->assertOk()`.
- [ ] **Step 2:** `composer update` then `php vendor/bin/phpunit` → FAIL (no phpunit.xml/TestCase).
- [ ] **Step 3:** Add files above (standard Laravel 13 defaults; phpunit.xml sets `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `APP_KEY` test key).
- [ ] **Step 4:** `php vendor/bin/phpunit` → PASS; `php tests/workflow.php` still passes (old app untouched).
- [ ] **Step 5:** Commit "Marketplace: Laravel skeleton and test harness".

### Task 2: Migrations and models

**Files:**
- Create: `database/migrations/2026_10_24_0000{01..10}_*.php` for `users`, `password_reset_tokens`+`sessions`+`cache`+`jobs`, `settings`, `categories`+`category_package`, `packages`, `package_releases`, `submissions`, `orders`, `licenses`+`license_activations`, `download_tokens`, `webhook_events` — columns exactly as the spec's Data model; indexed strings ≤ 191 chars.
- Create: `app/Models/{User,Setting,Category,Package,PackageRelease,Submission,Order,License,LicenseActivation,DownloadToken,WebhookEvent}.php`
- Create: `database/seeders/CategorySeeder.php` (the six categories from `src/Store.php`: electronics, fashion, home-living, payments, marketing, operations).
- Test: `tests/Feature/SchemaTest.php`

**Interfaces:** Produces relations `Package::releases()`, `Package::categories()`, `Package::latestRelease(): ?PackageRelease`, `License::package()`, `License::user()`, `License::activations()`, `License::activeActivation(): ?LicenseActivation`, `Order::license()`; `User::$is_admin` bool; `Setting::get(string $key, mixed $default = null): mixed`, `Setting::put(string $key, mixed $value, bool $secret = false): void`.

- [ ] **Step 1:** `SchemaTest::test_a_license_belongs_to_one_user_and_package` — creating two licenses for the same (user, package) throws `QueryException`; `test_settings_store_secrets_encrypted` — `Setting::put('stripe_secret', 'sk_test_x', secret: true)` → raw DB value ≠ `sk_test_x`, `Setting::get('stripe_secret')` === `sk_test_x`; `test_categories_are_seeded` — 6 rows after `CategorySeeder`.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Write migrations, models (casts: json for `features`, `signed_metadata`, `data`; datetimes), seeder.
- [ ] **Step 4:** Run → PASS; also `php artisan migrate:fresh --seed` against a MySQL database if available (document result in `docs/DEPLOY.md` Task 9).
- [ ] **Step 5:** Commit "Marketplace: migrations and models".

### Task 3: Signing key and license service

**Files:**
- Create: `app/Services/Signing.php` (copied, see Global Constraints), `app/Services/SigningKey.php`, `app/Services/Licensing.php`
- Test: `tests/Feature/LicensingTest.php`, `tests/Unit/SigningCompatibilityTest.php`

**Interfaces:**
- `SigningKey::secret(): string` (creates and stores the key pair on first use under setting `signing_secret`, secret), `SigningKey::publicKey(): string`, `SigningKey::keyId(): string`.
- `Licensing::issue(User $user, Package $package, string $type): License` — one stable key per (user, package); key format `MRX-` + 4 groups of 5 uppercase base32 chars.
- `Licensing::token(License $license, string $instanceId): array{token: array, signature: string}` — token fields exactly as the spec.

- [ ] **Step 1:** Tests: `issue` twice returns the same key; key matches `/^MRX-([A-Z2-7]{5}-){3}[A-Z2-7]{5}$/`; `token()` signature verifies with `Signing::verify($token, $sig, [SigningKey::publicKey()])`; `token.valid_until` is 30 days after `issued_at`; `SigningCompatibilityTest` signs a fixture with a fixed secret and asserts the exact signature string produced by the store platform's class (copy the expected value by running the platform's `Signing::sign` on the same fixture once and pasting it).
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement.
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5:** Commit "Marketplace: signing key and licensing".

### Task 4: Store license API — activate, check, deactivate, catalog

**Files:**
- Create: `routes/api.php` (prefix `api/marketplace/v1`, `throttle:60,1`), `app/Http/Controllers/Api/LicenseController.php`, `app/Http/Controllers/Api/CatalogController.php`, `app/Http/Controllers/Api/ApiError.php` (helper returning `{error, message}`)
- Test: `tests/Feature/Api/LicenseApiTest.php`, `tests/Feature/Api/CatalogApiTest.php`

**Interfaces:** Consumes `Licensing::token`. Error codes: `invalid_key` (404), `license_inactive` (403), `license_in_use` (409), `validation_failed` (422).

- [ ] **Step 1:** Tests: activate returns `{token, signature}` with `token.activated === true`, `instance_id` echoed, domain stored; activate same instance twice → 200 and one activation row; activate from another instance while active → 409 `license_in_use` and first activation still active; check for a non-activated instance → `activated false`; deactivate then activate elsewhere → 200; revoked license → 403 `license_inactive`; unknown key → 404 `invalid_key`; missing `instance_id` → 422 `{error: validation_failed}`; catalog lists only `published` packages with `identifier, type, name, slug, price, currency, is_free, thumbnail_url, demo_url, latest_version, download_url (free only)`.
- [ ] **Step 2:** Run → FAIL.
- [ ] **Step 3:** Implement (activation in a DB transaction with `lockForUpdate` on the license).
- [ ] **Step 4:** Run → PASS.
- [ ] **Step 5:** Commit "Marketplace: store license API".

### Task 5: Releases, updates and downloads

**Files:**
- Create: `app/Services/Releases.php`, `app/Http/Controllers/Api/UpdateController.php`, `app/Http/Controllers/Api/DownloadController.php`, `app/Console/Commands/PruneDownloadTokens.php` (scheduled hourly in `routes/console.php`)
- Test: `tests/Feature/Api/UpdatesAndDownloadsTest.php`

**Interfaces:** `Releases::publish(Package $package, string $zipPath, string $version, string $platformVersion): PackageRelease` (stores ZIP under `config('marketplace.storage').'/releases/{identifier}/{version}.zip'`, sha256, size, signed metadata `{identifier, version, sha256, size, platform_version, release_id}`); `Releases::newestFor(Package $p, ?string $installed, ?string $platform): ?PackageRelease` (composer/semver).

- [ ] **Step 1:** Tests: updates returns the newest compatible release with `signature` verifying; nothing newer → `{release: null}`; platform constraint not satisfied → skipped; downloads for an activated license → `{url, expires_at, release}` with `url` starting with `config('app.url')`; `GET` the url streams exactly `size` bytes; second `GET` → 410; expired token → 410; release of another package → 404; not activated on that instance → 403.
- [ ] **Step 2:** Run → FAIL. **Step 3:** Implement. **Step 4:** Run → PASS.
- [ ] **Step 5:** Commit "Marketplace: updates and downloads".

### Task 6: Stripe checkout and webhook

**Files:**
- Create: `app/Services/StripeGateway.php`, `app/Http/Controllers/CheckoutController.php`, `app/Http/Controllers/StripeWebhookController.php`, `app/Mail/LicenseIssued.php` + `resources/views/mail/license-issued.blade.php`
- Modify: `routes/web.php` (`POST /checkout/{package:slug}`, `GET /checkout/success`, `POST /stripe/webhook` without CSRF)
- Test: `tests/Feature/StripeTest.php`

**Interfaces:** `StripeGateway::createSession(Order $order, string $successUrl, string $cancelUrl): string` (returns redirect URL; POST `https://api.stripe.com/v1/checkout/sessions`); `StripeGateway::verify(string $payload, ?string $header): array` (throws `InvalidWebhook`).

- [ ] **Step 1:** Tests: checkout for a paid package creates a pending order and redirects to the faked session URL; free package → 404 on checkout (uses "Get license" instead); webhook with bad signature → 400, nothing changes; `checkout.session.completed` (paid) → order paid, license issued, `LicenseIssued` mailed once; same event again → 200, still one license, one mail; `checkout.session.expired` → order expired; `charge.refunded` for that payment intent → order refunded, license `refunded`, `licenses/check` then reports `status refunded`; signature older than 300 s → 400.
- [ ] **Step 2:** Run → FAIL. **Step 3:** Implement (HMAC-SHA256 over `{t}.{payload}`, compare with `hash_equals`). **Step 4:** Run → PASS.
- [ ] **Step 5:** Commit "Marketplace: Stripe checkout and webhook".

### Task 7: Legacy import and install command

**Files:**
- Create: `app/Console/Commands/ImportLegacy.php` (`marketplace:import-legacy {path=storage/catalog.sqlite}`), `app/Console/Commands/Install.php` (`marketplace:install`), `app/Services/LegacyImporter.php`
- Test: `tests/Feature/LegacyImportTest.php` with fixture `tests/fixtures/legacy.sqlite` built in the test from the CREATE statements in `src/Store.php`

**Interfaces:** `LegacyImporter::import(string $path): array<string, int>` (counts per table). Maps: admins → users(is_admin), customers → users, offers+listings → packages, package_releases, submissions, orders, licenses, license_activations, settings (signing key into `signing_secret`).

- [ ] **Step 1:** Tests: import copies a customer (password hash unchanged → can log in), a paid order and its license key, an activation; the imported signing key yields the same public key as the legacy settings; running twice → same counts, no duplicates; install command with `--email --password` creates an admin and a signing key and is refused when an admin exists.
- [ ] **Step 2:** Run → FAIL. **Step 3:** Implement. **Step 4:** Run → PASS.
- [ ] **Step 5:** Commit "Marketplace: legacy import and install".

### Task 8: Pages — catalog, accounts, admin

**Files:**
- Create: `resources/views/layouts/{site,admin}.blade.php`, `resources/views/catalog/{index,show}.blade.php`, `resources/views/auth/{login,register}.blade.php`, `resources/views/account/{index,licenses}.blade.php`, `resources/views/admin/{login,setup,overview,packages/index,packages/edit,submissions/index,submissions/show,categories,licenses,orders,settings}.blade.php`, `resources/views/publish/{submit,update,submissions}.blade.php`
- Create: controllers `app/Http/Controllers/{CatalogController,AuthController,AccountController,PublishController,DownloadController}.php`, `app/Http/Controllers/Admin/{AuthController,OverviewController,PackageController,SubmissionController,CategoryController,LicenseController,OrderController,SettingsController}.php`; middleware `EnsureAdmin`.
- Reuse: `public/{catalog,account,marketplace}.css` and `.js` (same class names as `src/front.php` so the look is unchanged).
- Test: `tests/Feature/Pages/{CatalogPagesTest,AccountTest,AdminTest,PublishTest}.php`

**Interfaces:** Consumes `Licensing::issue`, `Releases::publish`, `StripeGateway`. Routes keep today's URLs: `/`, `/themes`, `/extensions`, `/p/{slug}`, `/login`, `/register`, `/account`, `/submit`, `/submit/update`, `/submissions`, `/download/{slug}`, `/admin`, `/admin/login`, `/admin/setup`, `/admin/updates`.

- [ ] **Step 1:** Tests: catalog shows published packages only (drafts/hidden/pending absent), filters by type, category, free/paid and search; detail page shows price and "Get license" (free) or "Buy" (paid); register → login → `/account` lists keys, "Generate a license" for a free package shows the key, release activation frees it; another user cannot release it (403); publisher submits a ZIP (manifest mismatch → error), admin approves → package published with a release; reject with reason → visible in `/submissions`; admin routes redirect guests to `/admin/login`; settings never echo the Stripe secret; SVG thumbnail rejected.
- [ ] **Step 2:** Run → FAIL. **Step 3:** Implement, porting markup from `src/front.php`/`MarketplacePages.php`/`RepositoryAdmin.php`. **Step 4:** Run → PASS.
- [ ] **Step 5:** Commit "Marketplace: Blade pages".

### Task 9: Cut-over and deployment guide

**Files:**
- Modify: `bootstrap/app.php` (remove the `src/front.php` fallback), `README.md` (new setup/import steps)
- Delete: `src/`, `public/router.php` (if only used by the old front controller), `tests/workflow.php`
- Create: `docs/DEPLOY.md` (cPanel/shared hosting: upload, document root `public/`, `.env`, `composer install --no-dev`, `php artisan migrate --force`, `marketplace:install` or `/admin/setup`, `marketplace:import-legacy`, cron; VPS: Nginx + PHP-FPM sample config; MySQL 5.7+/MariaDB 10.3+ or SQLite; backups of DB and `storage/app/private`)
- Test: `tests/Feature/RoutesTest.php`

- [ ] **Step 1:** `RoutesTest::test_every_legacy_url_has_a_laravel_route` — for each URL in the Task 8 list and each API path, `Route::getRoutes()->match(Request::create($url, $method))` succeeds and is not a fallback.
- [ ] **Step 2:** Run → FAIL while the fallback exists. **Step 3:** Remove fallback and `src/`. **Step 4:** Full suite → PASS; `php artisan migrate:fresh --seed && php artisan marketplace:install` works on SQLite.
- [ ] **Step 5:** Commit "Marketplace: cut over to Laravel routes; deployment guide".
