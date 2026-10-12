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
