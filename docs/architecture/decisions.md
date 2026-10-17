# Architecture Decision Record Log

Each entry records a decision, its context, and its consequences. Entries are append-only; superseded decisions are marked rather than deleted.

---

## ADR-001 — Laravel 13 on PHP 8.4

- **Date:** 2026-10-12
- **Status:** Accepted
- **Context:** The specification mandates Laravel 13. `laravel/framework` v13.35.0 requires `php ^8.3`. The development machine's default `php` on `PATH` is 8.2.29 (incompatible); PHP 8.4.25 is installed at `C:\php84` with all required extensions (`pdo_mysql`, `pdo_sqlite`, `mbstring`, `intl`, `bcmath`, `gd`, `zip`, `redis`, `sodium` is bundled, `openssl`, `fileinfo`).
- **Decision:** Target PHP 8.3 as the minimum (`composer.json` `php: ^8.3`) and use PHP 8.4 for development, Docker images, and CI. CI tests both 8.3 and 8.4.
- **Consequences:** Developers on Windows must put PHP 8.4 first on `PATH` (documented in README). PHP 8.2 is explicitly unsupported.

## ADR-002 — Bootstrap from the official Laravel React starter kit

- **Date:** 2026-10-12
- **Status:** Accepted
- **Context:** The repository was empty apart from the specification. The spec requires React + TypeScript + Inertia + Vite and a Laravel-supported authentication approach.
- **Decision:** Scaffold from `laravel/react-starter-kit` (MIT). It provides Inertia 3, React 19, TypeScript 5, Vite 8 (via `vite-plus`), Tailwind CSS 4, Radix/shadcn-style components, and Laravel Fortify (login, registration, email verification, password reset, 2FA, passkeys) with an existing PHPUnit suite.
- **Consequences:** We inherit and maintain the kit's components. Wayfinder generates typed route helpers into `resources/js/actions` and `resources/js/routes` (git-ignored, regenerated at build time).

## ADR-003 — Modular monolith with `app/Core` and `app/Domains`

- **Date:** 2026-10-12
- **Status:** Accepted
- **Decision:** Platform infrastructure (authorization, audit, settings, installation, admin navigation, and later extensions/themes/marketplace/licensing) lives in `app/Core/*`. Commerce bounded contexts (Catalog, Customers, Cart, Checkout, Orders, Payments, Inventory, Shipping, Discounts, Tax) live in `app/Domains/*`. HTTP controllers stay thin and delegate to Actions/Services. Domain code may not import `Inertia\*` or React concerns — enforced by `tests/Architecture`.
- **Consequences:** Clear seams for modules; a little more ceremony than default Laravel layout.

## ADR-004 — Custom RBAC instead of a third-party permission package

- **Date:** 2026-10-12
- **Status:** Accepted
- **Context:** The spec requires granular permissions, protection against role escalation, and — critically — modules must be able to declare permissions (`module.json` → `permissions`). A permission package would work but adds a dependency whose Laravel 13 compatibility and module-registration ergonomics must be tracked.
- **Decision:** Implement a small RBAC core: `roles`, `permissions`, `permission_role`, `role_user` tables; a code-level `PermissionRegistry` (core + module permissions) synced to the DB; `Gate::before` grants everything to the Owner role; every other check resolves through the user's roles. Escalation guard: an actor may only grant roles whose permissions are a subset of their own; only Owners may grant/revoke Owner; an actor cannot change their own roles; the last Owner cannot be removed or disabled.
- **Consequences:** ~400 lines of owned code with full test coverage; no external dependency; permission cache is per-request (memoised on the user model).

## ADR-005 — Single `users` auth table; staff vs. customers by role membership

- **Date:** 2026-10-12
- **Status:** Accepted
- **Decision:** All authenticated people are `users`. A user with at least one role is _staff_ and may enter `/admin` (subject to per-permission checks). Users without roles are storefront customers. The Phase 2 `customers` table will hold commerce profile data (including guest customers with no `users` row) and link optionally to `users.id`.
- **Consequences:** One login page, one password-reset flow, one 2FA implementation. Public registration never grants a role. Disabled users (`disabled_at`) cannot log in and are logged out on their next request.

## ADR-006 — Installer: CLI first, token-gated web installer

- **Date:** 2026-10-12
- **Status:** Accepted
- **Context:** The spec requires secure initial owner creation and that the installer cannot be exposed or rerun unsafely. A public web installer on a freshly deployed server can be hijacked by whoever reaches it first.
- **Decision:** `php artisan ecomodular:install` is the primary path (interactive or flags). The web installer at `/install` additionally requires a one-time _setup token_ that is written to `storage/app/private/installer.token` (never rendered in a page or logged) — only someone with server shell access can read it. Once installed, the installed marker is stored in the `settings` table and every `/install` route returns 404; the token file is deleted. Database credentials are **not** written from the web; `.env` is configured by the operator.
- **Consequences:** Slightly more friction than "click next" installers, in exchange for no first-visitor takeover.

## ADR-007 — Money as DECIMAL(19,4) + ISO-4217 currency code; brick/math for arithmetic

- **Date:** 2026-10-12
- **Status:** Accepted (applies from Phase 2)
- **Decision:** Persist monetary values as `DECIMAL(19,4)` with an explicit `CHAR(3)` currency column. In PHP, money is never a float: use a `Money` value object backed by `brick/math` `BigDecimal` (already a transitive dependency of Laravel, MIT).
- **Consequences:** No floating point rounding; rounding mode documented per operation (HALF_UP for display/tax lines unless the tax strategy specifies otherwise).

## ADR-008 — Database: MySQL 8 (InnoDB) in production; SQLite in-memory for the fast test suite

- **Date:** 2026-10-12
- **Status:** Accepted
- **Context:** No MySQL server is installed locally and the Docker daemon is not running on the development machine.
- **Decision:** Production target is MySQL 8.0+/8.4 LTS with InnoDB, `utf8mb4`. Local dev uses Docker Compose MySQL. The default test suite runs on SQLite `:memory:` for speed; CI additionally runs the full suite against MySQL 8.4. Tests that depend on row locking semantics (`SELECT … FOR UPDATE`, concurrent checkout) are tagged `#[Group('mysql')]` and only meaningful on MySQL.
- **Consequences:** Migrations must stay portable (no MySQL-only DDL without a driver guard). Concurrency guarantees are only certified by the MySQL CI job.

## ADR-009 — Admin navigation is server-defined and permission-filtered

- **Date:** 2026-10-12
- **Status:** Accepted
- **Decision:** An `AdminNavigation` registry (core items + module-contributed items) is resolved per request, filtered by the current user's permissions, and shared to Inertia as `adminNavigation`. The React sidebar renders only what the server sends. Route middleware (`can:` / policies) remains the actual enforcement.
- **Consequences:** Modules add menu items without touching React core files; hidden ≠ authorized is preserved.

## ADR-010 — Audit log redaction

- **Date:** 2026-10-12
- **Status:** Accepted
- **Decision:** `AuditLogger` writes to `audit_logs` (actor, action, subject, IP, user agent, JSON metadata). Metadata keys matching `password|token|secret|key|card|cvv|cvc|recovery|two_factor` are replaced with `[REDACTED]` recursively before persistence. Audit rows are append-only (no update/delete routes; model blocks updates).

## ADR-011 — Wayfinder `Route::redirect` incompatibility

- **Date:** 2026-10-12
- **Status:** Accepted
- **Context:** Baseline `npm run types:check` failed: `Route::redirect()` registers all verbs including Laravel 13's `QUERY`, which Wayfinder emits but `@inertiajs/core@3.9.1`'s `Method` type does not include.
- **Decision:** Replace `Route::redirect('settings', …)` with an explicit `GET` route. Avoid `Route::redirect`/`Route::any` in application routes.

## ADR-012 — Every product has variants; simple products have exactly one

- **Date:** 2026-10-12
- **Status:** Accepted
- **Decision:** SKUs, prices, stock and weight live on `product_variants`. A simple product has one default variant; a variable product has one variant per combination of up to three option attributes (`product_attributes` / `product_attribute_values`). Carts, orders and inventory always reference a variant.
- **Consequences:** One code path for pricing and stock. Option combinations are validated server-side (one value per option, unique combinations). Variants removed from a product are soft-deleted so order and stock history stay intact.

## ADR-013 — Stock is decremented when an order is placed (no separate reservation table)

- **Date:** 2026-10-12
- **Status:** Accepted
- **Decision:** `InventoryService::allocate()` locks the affected `inventory_stocks` rows (`SELECT … FOR UPDATE`, ascending variant id to avoid deadlocks) and decrements `on_hand` inside the order-placement transaction. Cancellation and refund restocks release stock with their own ledger rows. Variants with `allow_backorder` may go negative; untracked variants are ignored.
- **Consequences:** Simple, race-free overselling protection. Unpaid manual-payment orders hold stock until they are cancelled. Time-limited holds for redirect-based payment flows will be revisited in Phase 3 if needed.

## ADR-014 — One store currency in version 1

- **Date:** 2026-10-12
- **Status:** Accepted
- **Decision:** `product_prices` is keyed by `(variant_id, currency)`, so multi-currency pricing is supported by the schema, but v1 prices, carts and orders all use the store's default currency. A variant without a price in that currency is not purchasable. Changing the default currency never converts existing prices.

## ADR-015 — Product translations deferred

- **Date:** 2026-10-12
- **Status:** Accepted
- **Context:** The spec's suggested schema lists `product_translations`.
- **Decision:** Not built in v1 (single locale). The planned design is a `product_translations (product_id, locale, name, description, …)` table with the base row as the default locale; no existing columns need to change.

## ADR-016 — Uploaded images are re-encoded

- **Date:** 2026-10-12
- **Status:** Accepted
- **Decision:** Product images are type-checked (`getimagesize`), size-limited (pixels checked before decoding), decoded with GD and re-encoded before storage under a random name with a type-derived extension. This strips EXIF/GPS metadata and any appended payloads (polyglot files).

## ADR-017 — Money values from the database are always strings

- **Date:** 2026-10-12
- **Status:** Accepted
- **Context:** A test showed SQLite returning DECIMAL columns as PHP floats; PHP then silently coerced `24.5` to the `int` branch of `Money::of(BigNumber|int|string)`, producing `24`.
- **Decision:** All money columns use the `decimal:4` Eloquent cast (exact strings on every driver), and `Money::of()` throws on floats instead of letting PHP coerce them.

## ADR-018 — Pricing is a server-side pipeline; checkout re-prices and compares

- **Date:** 2026-10-12
- **Status:** Accepted
- **Decision:** `CartPricer` prices a cart from catalog data, then runs registered `PricingStep`s in order: discounts → shipping → tax. The same pipeline produces the cart page, the checkout summary and the order. Checkout accepts only identifiers (variant ids, shipping method code, payment method code, coupon code). The total the shopper saw is submitted as `expected_total` solely to detect a price change; if it differs, the order is refused with the new total instead of charging a different amount.
- **Consequences:** Tampered amounts have no effect (tested). Modules can add pricing steps.

## ADR-019 — Discount allocation and rounding

- **Date:** 2026-10-12
- **Status:** Accepted
- **Decision:** Line subtotals are rounded to minor units; the coupon discount is computed on the eligible subtotal, capped at it, and spread across eligible lines proportionally (rounded down), with the last line absorbing the remainder so line discounts always sum to the order discount. Tax is computed per line on (subtotal − discount) and summed.

## ADR-020 — Separate order, payment and fulfilment states with an explicit workflow

- **Date:** 2026-10-12
- **Status:** Accepted
- **Decision:** `orders.status` (pending, processing, completed, cancelled), `payment_status` (unpaid, paid, partially_refunded, refunded) and `fulfillment_status` (unfulfilled, shipped, delivered) change only through `OrderWorkflow` operations (record payment, ship, mark delivered, cancel, refund), each locking the order row and appending history. There is no generic status setter. Cancellation requires an unshipped order without an unrefunded payment and returns stock; refunds may restock specific quantities.
- **Consequences:** Invalid transitions are rejected with explanations (tested). Gateway-driven payments (Phase 3) will call the same workflow from verified webhooks.

## ADR-021 — Manual payment methods in Phase 2

- **Date:** 2026-10-12
- **Status:** Accepted
- **Decision:** Two built-in, merchant-enabled methods: bank transfer (ship after payment) and cash on delivery (may ship before payment). Orders are always placed unpaid; staff with `orders.manage` record the payment after verifying it. This is the "explicitly configured payment method" of the Phase 2 exit criteria; it is not a simulated card payment.

## ADR-022 — Guest orders are linked to an account only after email verification

- **Date:** 2026-10-12
- **Status:** Accepted
- **Decision:** Guest checkouts create guest `customers` rows. When a user verifies an email address, earlier guest orders with that email are attached to the account. Linking on registration alone would let anyone read another person's order history by registering with their email.

## ADR-023 — Reports aggregate in PHP for v1

- **Date:** 2026-10-12
- **Status:** Accepted
- **Decision:** Sales reports load the orders of the period (max 366 days) and aggregate with `Money` in the store timezone. This is exact and portable (SQLite sums DECIMAL as floating point). Larger stores will need summary tables; tracked in the roadmap.

## ADR-024 — Idempotent order emails

- **Date:** 2026-10-12
- **Status:** Accepted
- **Decision:** Order emails are sent by a queued subscriber. Each sent email is recorded as an `order_events` row with a key (e.g. `placed`, `refunded:<total>`); a cache lock prevents concurrent duplicates and the record prevents repeats after retries or duplicate event delivery.

## ADR-025 — Stripe Checkout over the REST API, without the SDK

- **Date:** 2026-10-14
- **Status:** Accepted
- **Decision:** The reference gateway uses Stripe Checkout (hosted payment page), so card data never reaches the application (PCI SAQ-A scope). It calls the Stripe REST API with Laravel's HTTP client and a pinned `Stripe-Version` rather than `stripe/stripe-php`. Every creating request carries an idempotency key derived from our own record (`payment-<id>`, or the order state for refunds).
- **Consequences:** Every provider call can be faked in tests with `Http::fake()`, and there is no extra dependency. The adapter implements the small part of the API it needs, plus webhook signature verification (HMAC-SHA256 over `timestamp.payload`, 5-minute tolerance). Upgrading the API version is a deliberate change to one constant, covered by tests. Checkout, orders and the gateway contract must not import the adapter (architecture test).

## ADR-026 — Gateway credentials are encrypted, write-only settings

- **Date:** 2026-10-14
- **Status:** Accepted
- **Decision:** Secret keys and webhook signing secrets are entered in the admin UI, encrypted with `APP_KEY` (`SecretSettings`), and never returned to the browser; only the last four characters are shown. Changing them requires `payments.manage` and a recent password confirmation, because whoever controls the key controls where customers' money goes. Changes are audited without the values.
- **Consequences:** Merchants can configure payments without shell access. Rotating `APP_KEY` makes stored secrets unreadable; they then read as missing and must be re-entered (documented in `docs/payments/stripe.md`).

## ADR-027 — One reconciliation path; a redirect never marks an order paid

- **Date:** 2026-10-14
- **Status:** Accepted
- **Decision:** Provider facts reach orders only through `PaymentReconciler` → `OrderWorkflow`. The facts come from three sources: a verified webhook, a server-side retrieval of the session (on the customer's return and in the five-minute `ecomodular:payments:reconcile` job), or the response to our own refund request. Each transition locks the order and payment rows, is idempotent, and checks that the amount and currency match the payment we created. Webhooks are verified before anything is stored and are stored once per `(provider, event_id)`. Late, duplicate and out-of-order facts are recorded rather than dropped: a payment after cancellation, or a second payment, is flagged for refund. A session that expires unpaid cancels the order and releases its stock.
- **Consequences:** The return URL carries no authority; forging it changes nothing. Card orders are announced by email only once paid, so abandoned checkouts do not produce confirmation or cancellation emails. Refunds against an order paid twice go against the latest payment; refunding beyond that payment is refused by the provider.

## ADR-028 — Module packages on disk, lifecycle state in the database

- **Date:** 2026-10-15
- **Status:** Accepted
- **Decision:** A module is a directory `modules/<vendor>/<name>/` with a data-only `module.json`, validated by a PHP validator (with a published JSON Schema for authors), never executed. The `extensions` table records the merchant's decisions (state, installed version, timestamps, last valid manifest), and `extension_events` is an append-only lifecycle log. States: discovered, invalid, installed, enabled, disabled, update_pending and failed; only enabled modules are loaded. Newer files on disk put a module into update_pending and stop it from loading until its migrations have run. `ModuleManager` is the only code that changes state, one operation at a time under a lock, audited including refusals. The spec's suggested `extension_versions`, `extension_dependencies`, `extension_settings` and `extension_permissions` tables are covered by the manifest snapshot, the settings namespace and `permissions.source`, so they are not separate tables.
- **Consequences:** Deployment (git, Composer, rsync) remains the way code reaches a server, and installing, enabling and updating are explicit administrator actions. Package upload and signed downloads arrive with the marketplace (Phase 6) and will feed the same lifecycle.

## ADR-029 — Modules run in-process; guard, quarantine and safe mode instead of a sandbox

- **Date:** 2026-10-15
- **Status:** Accepted
- **Decision:** Module PHP runs in the application process with full privileges; PHP offers no practical sandbox. Mitigations:
    - explicit trust confirmation and password re-confirmation to enable;
    - declared capabilities shown to the administrator;
    - path, namespace, permission and migration confinement in the validator;
    - platform-controlled route prefixes and middleware, and a refusal of module admin routes without a `can:` middleware;
    - a guard that quarantines a module whose provider throws during register or boot;
    - `ECOMODULAR_SAFE_MODE` for fatal errors the guard cannot catch.

    Modules load in the application's `booting` phase, after every provider registered (deferred services such as the cache are then resolvable) and before any provider boots. They use a dedicated Composer class loader.

- **Consequences:** Documented plainly in the developer guide: only trusted code may be enabled, and public third-party submissions need review and isolation first (Phase 8). Process-level tests (`ModuleBootTest`) cover the fresh-boot path, which in-process tests cannot.

## ADR-030 — Module UI is compiled with the application

- **Date:** 2026-10-15
- **Status:** Accepted
- **Decision:** Module React pages and slot components live in the module package and are included in the application's Vite build through `import.meta.glob`. Pages are addressed as `module/<vendor>/<name>/<page>`; slot and widget components are referenced by the server and rendered inside an error boundary. Modules must not import generated route helpers; URLs come from server props.
- **Consequences:** One build pipeline, one design system, type-checked and linted with the core. Adding or updating a module with UI requires `npm run build`. Runtime-loaded, separately built module bundles may come later for marketplace packages.

## ADR-031 — Themes are data-only packages rendered by core components

- **Date:** 2026-10-16
- **Status:** Accepted (supersedes the Phase 0 sketch of compiled React theme bundles)
- **Context:** The spec requires that themes never execute arbitrary code and that marketplace uploads be restricted, while still allowing premium themes. A theme bundle of React components would run in every shopper's browser, which carries the same trust problem as module PHP.
- **Decision:** A theme is `theme.json` plus images and WOFF2 fonts; any other file type rejects the package. Themes choose values from a fixed, core-owned vocabulary:
    - design settings (colors, fonts, radius, density, page width, logo, favicon, announcement, footer text);
    - layout variants (header, footer, product card, grid, product page);
    - typed home sections (hero, featured products, category grid, image with text, text, promo banner).

    The core validates every value and generates the stylesheet only from validated tokens. Section content is plain text, and links are internal paths or `https://`. Assets are served by an allow-listing route with `nosniff` and a sandbox CSP. Cart, checkout, account and payment presentation are core components that only receive tokens.

- **Consequences:** A theme from any source is safe to install, and theme switching cannot affect commerce (tested per bundled theme). Theme authors are limited to the vocabulary; richer designs come from new core variants and section types, or from modules through slots. The vocabulary follows the compatibility policy, so it is only extended within a major version. No drag-and-drop page builder until it is stable.

## ADR-032 — Applied theme snapshot, separate merchant overrides

- **Date:** 2026-10-16
- **Status:** Accepted
- **Decision:**
    - **Storage:** themes reuse `extensions` (`type = theme`, unique per type), with `applied_manifest` holding the version the merchant applied. The storefront renders that snapshot, so new files on disk never change a live store until the merchant applies the update after seeing the changelog.
    - **Overrides:** merchant changes live in settings (`theme.<vendor>.<name>.design` and `.sections`) and are re-validated on every read. Overrides the current version no longer accepts are ignored and reported, never deleted. Overrides survive updates and switching themes; only an explicit, typed reset removes them.
    - **Preview:** session-scoped, and honoured only for users with `themes.manage`.
    - **Fallback:** if the active theme's files disappear, the bundled default theme is used, then a built-in design.
- **Consequences:** Theme updates cannot silently overwrite merchant content or settings (tested). The spec's suggested `themes`, `theme_settings` and `theme_assignments` tables are covered by `extensions` and settings. A single storefront needs no assignment table; multi-storefront assignment is post-1.0.

## ADR-033 — Marketplace server and client in one codebase

- **Date:** 2026-10-17
- **Status:** Accepted
- **Context:** The official marketplace sells extensions with the platform owner's own payments, while every merchant store must be able to buy, verify and install them. A separate service would be a second stack to secure and maintain.
- **Decision:** One codebase, two roles. The server role (catalog, release review and signing, marketplace checkout, licenses, license API, downloads) is enabled only by `ECOMODULAR_MARKETPLACE_SERVER=true`; its routes sit behind the `marketplace.server` middleware and return 404 everywhere else. The client role (Admin → Extensions → Marketplace) is part of every store. Marketplace orders, payments and Stripe credentials (`MARKETPLACE_STRIPE_*`, webhook `/webhooks/marketplace/stripe`) are separate from merchant orders and gateway settings; webhook deduplication reuses the payment event inbox with provider `marketplace-stripe`. Version 1 has one publisher (the owner); `marketplace_publishers` keeps room for vendors later.
- **Consequences:** The marketplace is tested end to end in one suite (the client is routed into the server code in tests). Merchant stores carry dormant server code that is unreachable unless explicitly enabled. Third-party vendors need verification, review and payout processes first (Phase 8).

## ADR-034 — Signed release metadata, verified before any file changes

- **Date:** 2026-10-17
- **Status:** Accepted
- **Decision:**
    - Releases are signed with **Ed25519** (libsodium, or `paragonie/sodium_compat` where the extension is missing) over canonical JSON metadata: product, package, type, version, platform constraint, SHA-256, size, publication date and key id. The key id (first 16 hex characters of the SHA-256 of the public key) selects the trusted key, so keys can be rotated with an overlap.
    - Upload runs an archive scan (no traversal, absolute paths or symlinks; size and file-count limits; manual extraction) and the module or theme validator. A release is `pending_review` and is signed only when the owner approves it, after the archive is hashed again.
    - The client verifies, in order: signature against a trusted key; product, package and type against the license; platform compatibility; downloaded size and SHA-256; archive scan and manifest validation; manifest name and version. Only then does it back up the current files, swap directories by rename and resync the registry. It never runs migrations or enables anything; the normal lifecycle does that with the merchant's confirmation.
- **Consequences:** Tampered archives, tampered metadata, unknown keys and corrupted downloads are rejected before `modules/` or `themes/` change (tested). A signature proves origin, not safety: module code still needs review and the merchant's trust confirmation (ADR-029). Rollback restores files only; schema changes need a database backup.

## ADR-035 — Licenses, signed tokens and outage behaviour

- **Date:** 2026-10-17
- **Status:** Accepted
- **Decision:**
    - **Keys** (`EMK-XXXXX-XXXXX-XXXXX-XXXXX`) are stored as a SHA-256 hash for lookup and encrypted for display in the customer portal.
    - **States:** `active`, `expired`, `revoked`, `refunded`, `suspended`, with an optional activation limit and an `updates_until` entitlement date. Activations are per random instance id.
    - **Tokens:** the license API answers with a signed token (license, product, status, instance id, `updates_until`, `valid_until`); the store verifies the signature and its own instance id.
    - **Downloads:** need an active, entitled license activated on that instance, through a hashed, single-use token valid for 10 minutes.
    - **Status changes:** refunds, revocations and suspensions take effect at once on the server, expire unused download tokens, and are logged in `license_events` (append-only) and the audit log.
    - **Outages:** the storefront and checkout never call the marketplace, and the client never disables installed extensions. When token validity plus a 14-day grace period has passed without a successful check, the admin shows a warning only.
- **Consequences:** A licensing outage cannot take a store down (tested with the server unreachable). The trade-off: a refunded or revoked extension keeps running where it is installed; enforcement covers updates, downloads and new activations, in line with the rule that extensions never disable core features.

## ADR-036 — Security headers and a nonce-based CSP from the application

- **Date:** 2026-10-13
- **Status:** Accepted
- **Context:** Headers configured only in the web server are lost on other hosts, and they conflict when both layers set them.
- **Decision:** A global `SecurityHeaders` middleware sends `nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`, COOP, and HSTS on HTTPS. It also sends a Content-Security-Policy with:
    - `script-src 'self' 'nonce-…'`, where Vite tags and the inline appearance script carry the per-request nonce;
    - `style-src 'self' 'unsafe-inline'`;
    - `img-src` limited to the app, the media disk URL and `ECOMODULAR_CSP_IMG_SOURCES`;
    - `connect-src 'self'`, `object-src 'none'` and `frame-ancestors 'none'`;
    - `form-action 'self' https://checkout.stripe.com`.

    Responses that set their own policy (theme assets) keep it. `ECOMODULAR_CSP` switches between `enforce`, `report-only` and `off`. The CSP is skipped while the Vite dev server runs. Nginx no longer sets these headers.

- **Consequences:** Injected markup cannot run script. Inline styles stay allowed, because theme tokens and UI libraries need them, and theme values are validated (ADR-031). Integrations that load third-party scripts (analytics, chat) must be added to the policy deliberately. A module that needs that is a future extension point.

## ADR-037 — Built-in backups: one checksummed, optionally encrypted archive

- **Date:** 2026-10-13
- **Status:** Accepted
- **Decision:** `ecomodular:backup` writes one zip per run, containing:
    - the database dump: `mysqldump --single-transaction` (credentials in a temporary file), `pg_dump`, or SQLite `VACUUM INTO`;
    - `storage/app/public`;
    - `storage/app/private`, without the backups themselves;
    - `modules/` and `themes/`;
    - a manifest with the platform version, the driver and the SHA-256 of every entry.

    With `ECOMODULAR_BACKUP_PASSWORD`, every entry is AES-256 encrypted. Retention keeps the newest N archives, and the backup runs daily from the scheduler. `ecomodular:restore`:
    - verifies every checksum and entry path before changing anything;
    - runs under maintenance mode;
    - swaps file directories aside, imports the database and puts the files back if the import fails;
    - migrates forward and clears caches.

    `.env` is not included: `APP_KEY` belongs in a secret manager.

- **Consequences:** Small and medium stores get working, tested backups without extra tooling, and the health check warns when backups stop. Large stores should add database point-in-time recovery and object storage versioning. Cross-driver restores are refused.

## ADR-038 — Third-party sellers: verified publishers, owner review and an append-only ledger

- **Date:** 2026-10-13
- **Status:** Accepted
- **Context:** Spec §12.5 opens the marketplace to vendors only after seller verification, package review, signing and incident response exist. Phases 6–8.3 delivered review, signing, scanning and advisories.
- **Decision:**
    - **Publishers:** the `marketplace_publishers` table gains seller status, commission, legal and payout details. An account belongs to at most one publisher.
    - **Verification:** sellers apply, and the owner verifies and approves them, choosing the commission.
    - **Package names:** a seller's packages must start with its vendor slug, and reserved slugs cannot be claimed, so no seller can ship under another vendor's or the platform's name.
    - **Review:** sellers upload through the same pipeline (archive checks, validation, scan). Only the owner approves, signs and lists, and the single marketplace signing key is kept.
    - **Accounting:** an append-only `marketplace_ledger_entries` table records sales at fulfilment and refunds (reversals). Both are idempotent per order item. Payouts are recorded with a reference and cannot exceed the balance.
    - **Payouts:** made outside the application.
- **Consequences:**
    - The owner stays the merchant of record and pays sellers. Automated payouts (Stripe Connect), tax handling per seller country, and per-seller signing keys are future work.
    - Suspension cuts off sales without breaking the licenses buyers already hold.
    - Commission changes apply to new sales only, because ledger entries are immutable.

## ADR-039 — Multi-store and multi-channel: deferred, with a planned design

- **Date:** 2026-10-13
- **Status:** Accepted (deferral)
- **Context:** Spec Phase 8 lists an optional multi-store or multi-channel architecture. Every table today belongs to a single store, and settings, themes, payments and taxes are global.
- **Decision:** Multi-store is not built now. Adding it would touch every query, cache key, setting and permission. That risk outweighs its value for the self-hosted single-store users targeted by 1.0, and running one installation per store gives full isolation today. When it is built:
    - **Data:** a `stores` table and a nullable `store_id` on channel-specific tables (orders, carts, prices, settings, theme assignments, shipping and tax zones), with catalog and customers shared by default.
    - **Scoping:** a `CurrentStore` resolved from the host name, applied by a global scope on store-owned models, with tests that fail on any unscoped query.
    - **Theming:** theme assignment per store (the `theme_assignments` table from spec §13).
    - **Access:** roles granted per store.

    Migrations will default existing rows to store 1, so single-store installations upgrade without changes.

- **Consequences:** Operators who need several storefronts run several installations. They can share a marketplace account, and licenses count one activation per installation.

## ADR-040 — Content translations in side tables with fallback

- **Date:** 2026-10-13
- **Status:** Accepted
- **Decision:**
    - **Storage:** product and category texts stay in their tables, written in the store's default language (Admin → Languages). Translations live in `product_translations` and `category_translations`, one row per locale. They cover name, descriptions and SEO fields; slugs are not translated.
    - **Storefront locale:** resolved per request, in order:
        1. a `?lang=` parameter, remembered in a cookie;
        2. that cookie;
        3. the browser's Accept-Language header;
        4. the default.

        Only enabled languages are accepted.

    - **Reading texts:** storefront code eager-loads only the current locale (`withCurrentTranslation()`) and reads fields through `localized()`. An empty translated field falls back to the default text, and no translation queries run in the default language.
    - **Search** also matches translated names in the shopper's language.
- **Consequences:**
    - The admin keeps editing one canonical text, and missing translations never produce empty pages.
    - Order snapshots keep the default-language product name, so staff see one language.
    - Not yet covered: variant and option names, interface strings ("Add to cart"), localized URLs and `hreflang` tags. Interface strings need a front-end translation catalogue (planned).

## ADR-041 — One media library over the existing image storage

- **Date:** 2026-10-13
- **Status:** Accepted
- **Decision:**
    - **Registry:** a `media` table registers every stored image: library uploads, product uploads (registered when uploaded) and theme uploads. Existing images are backfilled by the migration.
    - **Sharing:** product images chosen from the library point to the same file; nothing is copied.
    - **Deletion:** an image cannot be deleted while a product or a theme setting uses it.
    - **Cleanup:** removing a product image deletes the file only when it was a product upload that nothing else uses. Library images stay.
    - **Themes:** library images are offered in theme image settings (`media:library/...`).
    - **Uploads:** all of them go through the ImageSanitizer.
- **Consequences:** Staff manage images in one place without breaking pages that use them. Unused product uploads can be found with the "Not used by products" filter. Files are stored once per upload, so moving to S3 later stays a disk setting.

## ADR-042 — Storefront interface translations keyed by the English text

- **Date:** 2026-10-13
- **Status:** Accepted
- **Decision:**
    - **Keys:** the storefront's own wording is wrapped in `t('…')` (React) and `st('…')` (PHP). The key is the English text, with `:name` placeholders; static layout titles use `msg('…')` and are translated by the layout.
    - **Source catalogue:** `php artisan ecomodular:storefront-strings` extracts every key into `lang/storefront/en.json`. A test fails when the code and the catalogue disagree.
    - **Shipped translations:** `lang/storefront/{es,fr,de}.json`, checked by tests for completeness and matching placeholders.
    - **Merchant wording:** merchants override or complete any language under Admin → Languages → Storefront text, stored in `interface_translations`.
    - **Delivery:** the shopper's messages are shared as `i18n.messages`, which stays empty for English and on admin pages. The cache key carries the catalogue files' modification times, so deploys need no cache clearing.
    - **Locale detection:** it moved into `SetStorefrontLocale`, so sign-in and registration pages follow the shopper's language too.
    - **Laravel messages:** Laravel's validation, sign-in and password-reset messages ship for es, fr and de in `lang/{locale}/*.php`, with localized field names; anything missing falls back to English.
    - **Country names:** they come from ICU in the current locale.
- **Consequences:** Adding storefront text means wrapping it and re-running the command; the test enforces it. The admin, order history entries, emails, theme section content and module wording are not covered:
    - the admin is English;
    - theme texts are merchant content;
    - modules need their own catalogues (planned).

## ADR-043 — Order documents and guarded hold/packed statuses

- **Date:** 2026-10-13
- **Status:** Accepted
- **Decision:**
    - **PDFs:** invoices, payment receipts and packing slips are Blade templates rendered with dompdf (LGPL-2.1, used as a library). Rendering is locked down:
        - remote loading, PHP and JavaScript are off;
        - local reads are confined to `storage/framework/dompdf`;
        - the logo is embedded as a data URI;
        - all merchant text is escaped.
    - **Invoices are stored snapshots** (`invoices`): seller, buyer, lines and totals are frozen when issued, and the model refuses updates and deletes. Every paid order gets one automatically (`OrderPaid` listener); staff can issue one early.
    - **Numbering:** numbers come from a `document_counters` row locked inside the issuing transaction, so the sequence has no gaps or duplicates. The prefix (`{year}` placeholder), number of digits and starting number are set by the merchant; the next number can only go up.
    - **Receipts and packing slips are not stored:** they are rendered from payments, refunds and order items, which never change.
    - **Holds and packing are workflow moves:** `on_hold` order status and `packed` fulfilment status are entered only through `OrderWorkflow` (`hold`, `release`, `markPacked`) with the same locking, audit and rules as the other moves. There is no free-form status dropdown.
        - A held order cannot be packed or shipped.
        - Payment, cancellation and refunds still work while an order is held.
        - Release restores the previous status (processing if it was paid meanwhile).
    - **Customers never see "on hold" or "packed":** they see the status from before the hold, and "unfulfilled" until the order ships. Staff can tick "Email the customer" on hold, release, pack and ship.
- **Consequences:**
    - **Downloads:**
        - staff can download every document (`orders.view`);
        - signed-in customers can download their own invoice and receipts only (other orders return 404);
        - the invoice is attached to the payment email.
    - **Not covered yet:**
        - credit notes for refunds;
        - document wording follows the store's default language but no translations ship yet;
        - a webp logo is not supported.
        - the bundled DejaVu font covers Latin, Greek, Cyrillic and basic Arabic; Chinese, Japanese and Korean characters print as blanks, and Arabic and Hebrew are not shaped or right-to-left. A CJK-capable fallback font is a follow-up.

## ADR-044 — Detailed order statuses as three tracks plus a stored headline

- **Date:** 2026-10-13
- **Status:** Accepted
- **Decision:**
    - **Three tracks, extended:** merchants track orders through 19 statuses, from Pending Payment to Partially Refunded. Order, payment and fulfilment remain separate state machines, because one order can be delivered and partly refunded at once:
        - **order:** `pending_payment`, `pending` (awaiting review), `confirmed`, `on_hold`, `processing`, `completed`, `cancelled`;
        - **payment:** adds `failed`;
        - **fulfilment:** `picking`, `packing`, `packed`, `ready_for_dispatch`, `shipped`, `in_transit`, `out_for_delivery`, `delivered`, plus the side states `delivery_failed` and `returned`.
    - **One headline status:** `OrderDisplayStatus` derives it with a fixed priority:
        1. cancelled;
        2. refunded;
        3. returned;
        4. partially refunded;
        5. on hold;
        6. delivery failed;
        7. delivered;
        8. the current carrier or warehouse step;
        9. payment failed;
        10. pending payment;
        11. pending;
        12. confirmed;
        13. processing.

        It is stored in `orders.display_status` by a model `saving` hook, so every write keeps it current and lists can filter and count by it.

    - **Moves are guarded workflow transitions:**
        - Fulfilment moves forward only, and optional steps can be skipped.
        - Warehouse steps need an accepted, paid (or pay-on-delivery) order that is not on hold.
        - Carrier steps need a shipped order.
        - A failed delivery can be retried or delivered.
        - Returns can restock once, using `restocked_quantity`.
        - Cancellation is refused once shipped.
    - **Confirmation:**
        - Paid orders and pay-on-delivery orders are confirmed automatically.
        - The setting `orders.review_before_confirm` sends them to Pending until staff confirm.
    - **Customers see the headline,** except:
        - holds show the status from before the hold;
        - warehouse steps show as Processing.
    - **Status emails:** confirmed (after review only), payment failed, shipped, out for delivery, delivered, delivery failed and returned.
        - They go through `OrderStatusChanged` and the existing idempotent order-mail listener.
        - Each can be switched off (`orders.notify.*`, all on by default).
        - Staff can override the choice for a single change.
    - **Existing orders** are migrated:
        - `pending` becomes `pending_payment`;
        - paid but not yet fulfilled becomes `confirmed`.
- **Consequences:**
    - Every status change has to stay inside `OrderWorkflow`. There is no free status dropdown, by design.
    - Status emails are English for now.
    - Carrier tracking updates (in transit, out for delivery) are entered by staff until carrier integrations exist.

## ADR-045 — Partial manual payments and bulk order actions

- **Date:** 2026-10-13
- **Status:** Accepted
- **Decision:**
    - **Manual payments:** staff can record a payment on any order that still owes money, including online-payment orders.
        - **Amount:** at most the balance, at the currency's precision.
        - **Method:** cash, bank transfer, card terminal or other.
        - **Date:** today or earlier.
        - Recording one closes the order's open online payment sessions. If the customer still completes one, the existing duplicate-payment check flags the extra money for refund.
    - **Partly paid orders:**
        - `payment_status = partially_paid`, with a 20th headline status, Partially paid.
        - They are fulfilled only when fully paid, unless the method is pay on delivery.
        - The invoice and the payment email follow full payment.
        - Every payment gets its own receipt showing the balance.
    - **Bulk actions** run every selected order (at most 100) through the same `OrderWorkflow` moves as single changes. Orders that cannot move are skipped and listed in the result, never forced.
    - **Combined PDFs:** packing slips and invoices for up to 30 orders come as one PDF, one document per page. They are built from the same body templates as single documents.
    - **CSV export:** one row per order, for the selected orders or everything matching the list filters (`OrderFilters`, shared with the list). It is capped at 10 000 rows, streamed, and protected against formula injection.
- **Consequences:**
    - **Refunds don't reopen the balance due:** balance due = total minus payments.
    - **Bulk shipping records no tracking numbers.**
    - **Bundles stay small:** they render while the browser waits (about a second per page), so they are capped at 30 orders with a 120 s time limit. A queued export for larger batches is a follow-up.
    - **Payments arriving after a manual part payment:** an online payment that completes after one still settles the order. Any excess is flagged with its exact amount.
    - **Refunds follow the money:** a refund goes back to the online payment while some of it is unrefunded. Otherwise it is recorded as a manual refund (cash, bank, terminal).

## ADR-046 — Brands, tags and the single-page product editor

- **Date:** 2026-10-13
- **Status:** Accepted
- **Decision:**
    - **Brands are a managed list** (`brands`: name, slug, description, logo from the media library) with one brand per product.
        - Deleting a brand keeps its products, which are left without a brand.
        - Each brand has a public page, `/b/{slug}`, that lists its visible products.
    - **Tags are free text, stored normalised** (trimmed, single spaces, lowercase) in `product_tags`, at most 20 per product and 60 characters each.
        - They drive the admin filter and storefront search, both by exact match, so `%` and `_` are literal.
        - They are not shown to shoppers.
    - **New variant fields:**
        - **cost**, in the store currency and admin-only, never part of storefront props;
        - **length, width and height**, in the store dimension unit.
    - **Featured products:** `products.is_featured` feeds a new `featured` source for the home "Featured products" section and an admin filter.
    - **One-page editor:** the product editor stays one page with a sticky section menu, a sidebar (status and visibility, organisation) and a sticky save bar.
        - The save bar shows unsaved changes and warns before the browser leaves or Inertia navigates away while the form is dirty.
        - Variable products use a compact variants table with expandable details.
        - Margin is calculated live from price and cost.
- **Consequences:**
    - **Not covered yet:**
        - the CSV import and export don't carry brand, tags, cost or dimensions;
        - brand names and descriptions are not translatable;
        - shipping rates don't use dimensions.

## ADR-047 — Catalog sale prices and the Flash Sales module

- **Date:** 2026-10-16
- **Status:** Accepted
- **Decision:**
    - **Sale prices are a core extension point.** Modules implement `SalePriceProvider` and register it with the `SalePrices` registry (capability `catalog.sale_prices`).
        - For each product, the offer giving the lowest price wins. A price never goes below zero.
        - A fixed-amount offer applies only to prices in its own currency.
        - The same registry prices storefront cards, the product page and cart lines, so the price shown is the price charged.
        - Coupons and other pricing steps apply on top of the sale price.
        - A provider that throws is logged and ignored, so a broken module never stops the store selling at regular prices.
    - **Admin product-editor slot** `admin.product.sidebar`: modules add panels to the editor sidebar. The panels save through their own routes and permissions.
    - **Flash Sales** (`modules/ecomodular/flash-sales`) is the first user of both:
        - sales with a name, a start and end time, and a percentage (1–90) or fixed-amount discount;
        - products added from the sale page or with the "Flash sale" toggle in the product editor, each with an optional own discount;
        - a "−20%" badge and the original price crossed out, a countdown on the product page, and an optional home page banner.
- **Consequences:**
    - **Sorting uses the regular price:** listings sort and filter by price using the SQL `min_price`, which ignores sale prices.
    - **Orders keep the price charged:** they store the sale unit price, not the regular price or the sale name.
    - **Other modules still show regular prices:** frequently-bought-together bundle prices, the Tabby and Tamara instalment widgets, and the product-label "On sale" label read `priceIn()` directly. The cart still charges the sale price. They should move to `SalePrices`.
    - **Offer cache:** offers are cached per request and for at most 30 seconds in long-running workers. An offer that has ended is never applied.

## ADR-048 — Product tools and digital products

- **Date:** 2026-10-16
- **Status:** Accepted
- **Decision:**
    - **Digital products are set per product** (`products.is_digital`), not per variant. Their files are `product_files` on the private `local` disk (`storage/app/private/downloads/{product}`), never web-served.
    - **Checkout:** a cart whose items are all digital gets a free "No shipping needed" option (code `digital`) from `ShippingStep`, so checkout needs no shipping method. The address is still collected, for billing and tax.
    - **Download links:**
        - `download_grants` are created when an order is fully paid (`OrderPaid`, idempotent through `DownloadGrants::issueFor`): one per digital order item and file, with the store's limit (default 5) and expiry (default 30 days) at that moment;
        - the payment email issues missing links itself, so it never depends on listener order;
        - the 40-character random token is the secret, so links work for guests without signing in;
        - each download is counted and logged, and rate-limited per IP;
        - links stop when the order is cancelled or fully refunded (`RevokeDownloads`), and a link that no longer works shows a 410 page.
    - **Delivery:** a paid order with only digital items is completed automatically (`OrderWorkflow::deliverDigital`), with no shipment.
    - **Related products:** `product_relations` (related or upsell, ordered, at most 12 each). With no related products chosen, the page falls back to same-category products.
    - **Bulk actions:** one endpoint, at most 200 products, with permission checked per action (update, delete or view). Each action runs in one transaction with one audit entry; skipped products are listed. Prices change in every currency and never go below the currency's smallest unit.
    - **Duplicates** are hidden drafts with unique `-copy` slugs and `-COPY` SKUs. They share image media (files are only removed when unused) but get their own copies of digital files.
- **Consequences:**
    - **No license keys, file versions or bundles yet.**
    - **Fixed amounts are not converted:** a fixed bulk price change uses the same number in every currency.
    - **Downloads are not protected beyond the token:** anyone with a link can use it until it runs out or is revoked.

## ADR-049 — Customizer tabs and per-page styles

- **Date:** 2026-10-16
- **Status:** Accepted
- **Decision:**
    - **Tabs:** every design setting has a customizer tab, decided server-side by `CustomizerTabs::for`.
        - An explicit `tab` on the field wins, then the `style.{area}.` key, then keywords in the group name.
        - The page shows one tab at a time over a single form.
    - **Style fields:** per-area fields `style.{general|home|catalog|product|cart}.{prop}` (headings, text, buttons, prices) are core design fields for every theme, validated by the existing `ThemeSchema` rules.
    - **CSS:** `ThemeStyleFields::css` writes rules only for values that are set.
        - General rules are scoped to `.storefront`, and page rules to `.storefront[data-page="…"]`, which the storefront layout sets from the page.
        - Rules use `!important` and target core classes (`store-button`, `store-price`, `store-price-sale`) plus the bundled themes' own button and price classes.
        - So no theme stylesheet has to change, and an unconfigured store is unchanged.
- **Consequences:**
    - **New themes:** a theme with new button or price classes needs adding to `ThemeStyleFields` (or must use the core classes) to follow these settings.
    - **`!important`:** merchant styles override theme styles, including theme hover states that are not covered.
    - **Text size:** "Text size" sets the inherited size; elements a theme sizes explicitly keep their size.

## ADR-050 — Store notifications

- **Date:** 2026-10-17
- **Status:** Accepted
- **Decision:**
    - **Storage:** Laravel database notifications (`notifications` table) through one `StoreNotification` class; `NotificationKinds` maps each kind to its audience, permission, icon and English templates, and modules can register more.
    - **Sending:** `StoreNotifier::toStaff` reaches owners and staff whose role has the kind's permission, skips the person who caused the event, and honours each person's bell/email choices (`users.notification_preferences`); `toCustomer` writes to the customer's bell only. A dedupe key in the data stops the same event twice.
    - **Triggers:** two event subscribers (`NotifyStaff`, `NotifyCustomer`) listen to the existing order, stock and customer events; failures are reported, never thrown into checkout.
    - **Real time:** the admin bell polls `admin/notifications/feed` every 30 seconds while the tab is visible (no websockets); the storefront refreshes counts on navigation only.
    - **Links:** only internal paths are stored and followed.
    - **Translation:** customer texts are re-rendered in the browser from `kind` and `params` through the storefront catalogue; staff texts stay English like the admin.
- **Consequences:**
    - **Delay:** staff see a new notification up to 30 seconds late; a push channel can replace the poll later without changing the data.
    - **Clean-up:** `notifications:prune` (daily) deletes read notifications after 90 days and all after 365.
    - **Customer settings:** customers cannot turn notifications off in this version; order emails are unchanged.
