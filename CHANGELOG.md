# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

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
