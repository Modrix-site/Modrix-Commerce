# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added (storefront interface translations)

- The storefront's wording (header, product, cart, checkout, order, account and sign-in pages, server messages) is translatable.
- Spanish, French and German are shipped, including Laravel validation, sign-in and password-reset messages.
- Admin → Languages → Storefront text: change any text or translate other languages (search, "not translated" and "changed" filters).
- `php artisan ecomodular:storefront-strings` rebuilds the source catalogue. A test keeps code and catalogue in sync and checks placeholders.
- Country names follow the shopper's language; dates on order pages follow the page language.
- Sign-in and registration pages follow the shopper's language.
- ADR-042.

### Added (media library and translations)

- Media library (Catalog → Media library):
    - multi-image upload, re-encoded like every other upload;
    - search and filters (source, unused);
    - alt text editing, copy URL, "used by" (products and theme settings);
    - deletion blocked while an image is in use.

    Product images can be chosen from the library, and library images can be used in theme image settings. Existing product and theme images are registered automatically. New `media.manage` permission (store and catalog managers).

- Storefront translations:
    - Admin → Languages: default language plus additional languages.
    - Product and category translation editors that show the default text next to each field, reached from the product page, the category list and Catalog → Translations (progress and missing items per language).
    - A storefront language switcher; the language is detected from the link, a cookie or the browser.
    - Translated names and descriptions on product, listing, category, home and cart pages, and in search, with fallback to the default language.
- ADRs 040–041.

### Added (Phase 8: post-launch ecosystem)

- Developer SDK:
    - `ecomodular:make:module` and `ecomodular:make:theme` scaffolders;
    - `ecomodular:package`, which validates, scans and builds the marketplace archive;
    - getting-started guide for extension developers.
- Package security scanner (code execution, shell, obfuscation, dynamic includes, raw SQL, network, environment, forbidden files) on every marketplace upload and in the CLI.
- Signed security advisories for marketplace products, shown to affected stores in the admin and the health check.
- Third-party sellers:
    - applications and verification by the owner;
    - vendor-name enforcement and a seller portal (products, releases, statement);
    - owner review and signing;
    - commission and payout ledger;
    - suspension.
- Marketplace product reviews by license holders, with moderation, and support contacts on product pages.
- Harvest theme (food and grocery) and the newsletter sign-up module (consent, unsubscribe, export, erasure).
- Personal data export for customers (My account → Download my data), with `PersonalDataExport::contribute()` for modules.
- ADRs 038 (sellers) and 039 (multi-store deferred).

## [1.0.0-rc.1] - 2026-10-13

### Added (Phase 7: production hardening)

- Health monitoring:
    - `/health` readiness endpoint (overall status, details with `ECOMODULAR_HEALTH_TOKEN`, 503 on failure);
    - `ecomodular:health` command;
    - health card on System status, checking database, migrations, cache, storage, disk, queue, scheduler heartbeat, backup age, webhook failures and production configuration.
- Backups:
    - `ecomodular:backup` and `ecomodular:restore`: database dump (SQLite, MySQL/MariaDB, PostgreSQL), media, private files, modules and themes;
    - SHA-256 manifest, optional AES-256 encryption, retention, daily schedule;
    - verified, path-safe restores with file rollback on failure.
- `SecurityHeaders` middleware: nonce-based Content-Security-Policy, frame denial, `nosniff`, referrer and permissions policies, COOP, HSTS on HTTPS (`ECOMODULAR_CSP`, `ECOMODULAR_CSP_IMG_SOURCES`, `ECOMODULAR_HSTS`).
- Installer: setup tokens expire after 24 hours (`ECOMODULAR_INSTALLER_TOKEN_HOURS`); `ECOMODULAR_WEB_INSTALLER=false` for CLI-only installs.
- Docs:
    - production deployment, monitoring, troubleshooting and backup guides;
    - `SECURITY.md` with the incident and extension takedown process;
    - security review report; ADRs 036–037.
- Tests: process-level fresh-install, upgrade-path and restore drills; query budgets; security headers. CI runs a MySQL backup and restore drill and audits build tooling.

### Changed

- Nginx sample: no duplicate application security headers; `/storage/` served with `nosniff` and a sandbox CSP; long-lived caching for `/build/`.
- Lazy loading and silently discarded attributes now throw outside production.

### Security

- Per-IP rate limit on password reset, reset submission and registration.
- The last active owner can no longer delete their own account.
- `tinypool` pinned to 2.1.2 (GHSA-5gmw-xhrv-c9v3, GHSA-85c8-ppgw-ccpr; build tooling only).

### Fixed

- Account deletion for users with marketplace licenses or orders shows a message instead of failing.
- Cart pages no longer write every line on each view.
- Destructive alert text contrast (WCAG AA); wide tables are keyboard-scrollable; long values wrap in alerts on phones.

### Added (Phase 6: official marketplace and licensing)

- Marketplace server role (`ECOMODULAR_MARKETPLACE_SERVER`, off by default): product admin, release upload with archive scanning and manifest validation, review and Ed25519-signed release metadata, withdrawal; `ecomodular:marketplace:keygen`.
- Marketplace checkout with separate Stripe credentials and webhook, idempotent fulfilment, free products, refunds.
- Licenses with hashed and encrypted keys, states, activation limits and update entitlement; append-only license history; license admin (suspend, revoke, reinstate, extend).
- License API v1 (catalog, activate, check, deactivate, updates, downloads) with signed tokens and single-use download links.
- Customer portal: licenses, keys, activations, downloads and orders.
- Store client (Admin → Extensions → Marketplace): catalog, license activation, verified install and update with backup, rollback, update history, daily `ecomodular:marketplace:check` with a grace-period warning; installed extensions are never disabled.
- Licensing guide rewritten; ADRs 033–035.

### Changed

- Stripe REST calls and webhook verification moved into a shared `StripeApi`, used by the merchant gateway and the marketplace.

### Added (Phase 5: theme engine)

- Data-only themes (`themes/<vendor>/<name>/theme.json` with images and WOFF2 fonts). The validator rejects code or markup files, path escapes, unsafe links and unknown values (JSON Schema in `docs/extensions/theme.schema.json`).
- Design settings (colors, fonts, radius, spacing, width, logo, favicon, announcement, footer text), layout variants (header, footer, product card, grid, product page) and typed home sections, all rendered by core components.
- Admin → Appearance: preview (staff session only), activate, customize (design, image library, home section editor), apply updates with changelog, reset, demo content import.
- Merchant customizations stored apart from theme defaults; the storefront renders the applied theme version; fallbacks to the default and built-in designs.
- Bundled themes: Essence (default, activated on install), Noir and Bloom (premium-ready samples with demo content).
- Theme developer guide; ADRs 031–032.

### Changed

- The storefront home page is now built from theme sections.
- Product images and theme uploads share one image sanitizer.

### Added (Phase 4: module engine)

- Module packages under `modules/<vendor>/<name>/` with a validated, data-only `module.json` (JSON Schema in `docs/extensions/module.schema.json`).
- Lifecycle: discover, install (migrations with rollback on failure), enable (trust confirmation, dependency checks, trial boot), configure (typed settings), disable (keeps data), update (update-pending files are not loaded until migrated), uninstall (typed confirmation); dependency and version resolution with `composer/semver`; lifecycle log and audit.
- Guarded loading: a module that throws while booting is quarantined and the store keeps running; `ECOMODULAR_SAFE_MODE`; `ecomodular:modules` command.
- Module routes under fixed prefixes and middleware; module admin routes must declare a permission; permissions, navigation and settings from the manifest.
- Extension points: checkout validators, search provider, storefront slots, dashboard widgets; module React pages and components compiled with the app.
- Admin → Extensions: list, detail, lifecycle actions, generated settings form, lifecycle log.
- Sample modules: Product Reviews, Sales Trend Widget, Paid Order Alerts.
- Module developer guide, recovery guide, ADRs 028–030.

### Added (Phase 3: payment integrations)

- `PaymentGateway` contract with provider-neutral payment and refund facts; gateway registry.
- Stripe Checkout (hosted page): idempotent session creation, signed webhook verification, server-side reconciliation, refunds, Stripe minor-unit handling.
- Encrypted, write-only gateway credentials behind password confirmation; connection test.
- Webhook inbox with per-event deduplication, queued retrying processing, and an admin payment event log with retry.
- Customer flow: redirect to payment, "confirming"/"processing"/"complete your payment" states, retry with session reuse, cancellation of abandoned payments with stock release.
- Gateway refunds from the order page; refund webhooks confirm, fail or import refunds.
- `ecomodular:payments:reconcile` command, scheduled every five minutes.
- Stripe setup guide (`docs/payments/stripe.md`), ADRs 025–027.

### Changed

- Card orders send the order confirmation and staff notification when payment is confirmed, not when the order is placed.
- Manual payment recording is no longer offered for orders paid through a gateway.

### Added (Phase 2: commerce core)

- Catalog: nested categories, products with variants and options, per-currency prices, SEO fields, re-encoded image uploads, admin search/filter/sort.
- Inventory: per-variant stock, immutable movement ledger, row-locked all-or-nothing allocation, backorder policy, low-stock events and store alerts.
- Customers and address book; profile on registration; guest orders linked after email verification.
- Storefront: home, category, search, product pages; session/user carts with merge on login; price-change and availability notices.
- Server-side pricing pipeline: coupons (percentage/fixed, dates, limits, minimum subtotal, product/category scope), flat-rate shipping with free-over threshold and country restrictions, rate-table tax (inclusive or exclusive, optional shipping tax).
- Checkout with idempotency token and changed-total detection; order snapshots (items, money, addresses); human-readable order numbers.
- Order workflow: record manual payment, ship with tracking, deliver, cancel with restock, partial/full refunds with restock; order history and notes.
- Manual payment methods (bank transfer, cash on delivery), guest checkout toggle.
- Queued, idempotent order emails and new-order notification to the store.
- Reports (sales over time, orders by status, top products, AOV, refunds) and dashboard metrics in the store timezone.
- Product CSV export and all-or-nothing import keyed by SKU, with a row-level error report and CSV-injection protection.
- Admin: orders, customers, discounts, shipping, taxes, payments, reports.

## [0.1.0] - 2026-10-12

### Added

- Phase 0 documentation: architecture overview, ADRs 001–011, database schema, testing strategy, roadmap and third-party notices.
- Laravel 13 / React 19 / TypeScript / Inertia 3 / Vite 8 application scaffold (from `laravel/react-starter-kit`).
- Installer: the `ecomodular:install` command and a token-gated `/install` web installer that locks itself after installation. `ecomodular:installer-token` command.
- RBAC: `roles`, `permissions`, `permission_role` and `role_user` tables, a `PermissionRegistry`, seven system roles, custom roles, `EscalationGuard`, and the `ecomodular:sync-permissions` command.
- Staff user management (invite by password-setup link, role assignment, disable/enable) behind password re-confirmation.
- Append-only audit log with secret redaction. Authentication events are audited.
- Store settings (name, email, currency, timezone, units) with a cached settings repository.
- Admin shell: permission-filtered server navigation, dashboard, users, roles, store settings, audit log and system status pages.
- Disabled-account enforcement at login and on every request.
- Docker Compose development stack (PHP-FPM 8.4, Nginx, MySQL 8.4, Redis, Mailpit, queue worker, scheduler).
- CI: PHP 8.3/8.4 matrix, MySQL 8.4 test job, dependency audit.

### Changed

- `/dashboard` now sends staff to `/admin` and customers to their account settings.
- `/settings` now uses an explicit GET redirect instead of `Route::redirect`, which fixes the Wayfinder TypeScript error (ADR-011).

### Removed

- Unused starter-kit placeholder dashboard, header layout and external starter-kit links.
