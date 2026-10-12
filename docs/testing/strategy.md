# Testing Strategy

## Suites

| Suite        | Location                                           | Purpose                                                                                                                                                                  |
| ------------ | -------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Unit         | `tests/Unit`                                       | Pure logic with no framework boot: permission registry, redaction, role defaults. Later: pricing, discounts, state machines, manifest validation, dependency resolution. |
| Feature      | `tests/Feature`                                    | HTTP and console behaviour against a real database: auth, installer, RBAC matrix, escalation guards, audit, settings.                                                    |
| Architecture | `tests/Architecture`                               | Layer boundaries: `app/Core` and `app/Domains` must not import `Inertia\*` or `App\Http\*`.                                                                              |
| MySQL group  | `#[Group('mysql')]`                                | Locking and concurrency tests (`SELECT … FOR UPDATE`, concurrent checkout) that are only meaningful on MySQL. Run in CI's MySQL job.                                     |
| Process      | `tests/Feature/*/…BootTest`, `ProductionPathsTest` | Separate PHP processes against throwaway databases: fresh boot with modules, fresh install, upgrade path, backup and restore drill.                                      |

## Rules

- Every access-controlled route has both a permitted test and a forbidden test (see the `AdminAccessTest::routeMatrix` data provider).
- Every security invariant has a test that tries to break it (`RoleEscalationTest`).
- Tests never use real credentials. Payment tests use provider test modes or signed fixture payloads.
- Seed and demo data (`DevelopmentSeeder`) stays separate from test fixtures (factories and helpers in `tests/TestCase.php`).
- Feature tests that render Inertia pages need built assets (`npm run build`), because the Vite manifest is resolved during rendering.

## Commands

```bash
php artisan test                  # all suites, SQLite in memory
php artisan test --testsuite=Unit
composer test                     # pint --test + larastan + phpunit
DB_CONNECTION=mysql DB_DATABASE=ecomodular_test php artisan test   # MySQL run
```

## Phase 1 results (2026-10-12, local, PHP 8.4.25, SQLite)

- `php artisan test`: 114 tests passed, 0 failed (including the starter kit's Fortify auth tests).
- `vendor/bin/phpstan analyse`: 0 errors (level 7).
- `vendor/bin/pint --test`: passed.
- `npx tsc --noEmit`: passed. `npm run check`: passed. `npm run build`: succeeded.
- MySQL job: defined in CI, **not executed locally** (no MySQL or Docker daemon on the development machine).

## Phase 2 results (2026-10-12, local, PHP 8.4.25, SQLite)

- `php artisan test`: 210 tests, 209 passed, 1 skipped (`StockLockingTest`, MySQL group), 0 failed.
- Commerce coverage: Money arithmetic and float rejection, inventory ledger and all-or-nothing allocation, cart repricing and merge, coupon rules and rejection reasons, shipping and tax (inclusive and exclusive), checkout idempotency and changed-total detection, order state machines, refunds with restock, emails, reports, CSV import/export (formula escaping, all-or-nothing import, stock permission), and the permitted/forbidden matrix for every new admin route.
- `vendor/bin/phpstan analyse`: 0 errors. `vendor/bin/pint --test`: passed.
- `npm run types:check`, `npm run check`, `npm run build`: passed.
- Browser check: admin product creation, storefront purchase by bank transfer, admin payment recording, dashboard metrics, CSV export.
- MySQL group: **not executed locally**; it runs in CI's MySQL job.

## Phase 3 results (2026-10-14, local, PHP 8.4.25, SQLite)

- `php artisan test`: 255 tests, 254 passed, 1 skipped (MySQL group), 0 failed.
- Payments coverage (`tests/Feature/Payments`, `tests/Unit/StripeAmountTest.php`): hosted-session creation (amount in minor units, idempotency key, pinned API version); a forged or unpaid return redirect leaves the order unpaid; server-side confirmation; amount and currency mismatches; duplicate and re-sent webhooks; forged, stale, unsigned and wrong-secret webhooks rejected before storage; foreign sessions and irrelevant events ignored; expiry cancels and restocks without emails; payment after cancellation is flagged; expiry after payment is a no-op; async (processing → succeeded/failed) flows; retry reuses or replaces sessions and closes the old one; Stripe outage at checkout; ownership checks; the reconcile command; gateway refunds (issued first, rejected, double-submitted, failed later, imported from the dashboard, webhook racing the admin request); encrypted write-only credentials behind password confirmation; secrets absent from pages, storage and audit; the event log and retry; the route permission matrix; and an architecture rule that checkout and orders do not import the Stripe adapter.
- Stripe is faked at the HTTP layer (`Http::fake`, `Http::preventStrayRequests`) and webhooks are signed exactly as Stripe signs them (`tests/Support/StripeFixtures.php`).
- `vendor/bin/phpstan analyse`: 0 errors. `vendor/bin/pint --test`: passed. `npm run types:check`, `npm run check`, `npm run build`: passed.
- **Not yet executed:** a test-mode purchase against a real Stripe account. It needs the merchant's Stripe test keys and webhook secret; follow `docs/payments/stripe.md` §5.

## Phase 4 results (2026-10-15, local, PHP 8.4.25, SQLite)

- `php artisan test`: 324 tests, 323 passed, 1 skipped (MySQL group), 0 failed.
- Module coverage (`tests/Feature/Extensions`, `tests/Unit/ManifestSchemaTest.php`):
    - **Validation:** 21 rejection cases, including unknown keys, incompatible platform, unknown or missing capabilities, path escapes, reserved namespaces, permission and migration conflicts, dependency cycles, malformed and oversized manifests.
    - **Lifecycle:** the full install → enable → configure → disable → update → uninstall cycle on a fixture module and on the bundled reviews module, including a schema change across versions.
    - **Dependencies:** installation, enabling and version constraints; a dependency cannot be disabled or uninstalled under an enabled or installed dependent.
    - **Failure handling:** trust confirmation; quarantine of a module failing at enable or at a later boot; rollback and retry of a failed install; failed and retried update; downgraded and missing files; safe mode; CLI.
    - **Permissions and admin:** module admin routes require a declared permission; module permissions are kept but ineffective while disabled; admin pages and the permission matrix.
    - **Extension points:** checkout validators (refuse, fail closed), search providers cannot reveal drafts, storefront slots and dashboard widgets (permission-filtered, failures isolated).
    - **Sample modules:** reviews, sales widget, and idempotent order alerts.
- `ModuleBootTest` boots separate PHP processes against a throwaway database. It found two fresh-boot defects that in-process tests could not see (now fixed; see ADR-029).
- `vendor/bin/phpstan analyse` (now including `modules/`): 0 errors. `pint`, `npm run types:check`, `npm run check`, `npm run build`: passed.
- Browser check: the reviews module was installed and enabled on the dev store. A review was submitted from the product page, moderated on the module's admin page, and then shown with its rating. The sales widget appears on the dashboard and the Reviews item in the admin navigation.

## Phase 5 results (2026-10-16, local, PHP 8.4.25, SQLite)

- `php artisan test`: 366 tests, 365 passed, 1 skipped (MySQL group), 0 failed.
- Theme coverage (`tests/Feature/Themes`, `tests/Unit/ThemeSchemaTest.php`):
    - **Rejected packages:** 13 invalid-package cases (path traversal, absolute paths, script and protocol-relative links, unknown sections, CSS injection in colors and fonts, unknown keys and capabilities, incompatible platform) plus code and markup files (PHP, SVG) inside a package.
    - **Fallbacks:** the default and built-in fallbacks, and fallback and recovery when the active theme's files go missing.
    - **Lifecycle:** activation with exactly one active theme.
    - **Customizations:** they survive an update and switching themes back and forth; overrides a new version rejects are reported, not deleted; invalid customizations are refused; reset requires typing the theme's name.
    - **Preview:** visible only to the previewing staff member; a forged preview session value is ignored.
    - **Assets and uploads:** asset serving (types, headers, traversal); uploads re-encoded so an appended PHP payload does not survive.
    - **Admin and schema:** admin permissions; JSON Schema kept in sync with the validator.
- Compatibility: Essence, Noir and Bloom each run home → category → search → product → cart → checkout (identical totals) → confirmation → account. Section images resolve. Demo import is additive, idempotent and requires confirmation.
- `phpstan`, `pint`, `npm run types:check`, `npm run check`, `npm run build`: passed.
- Browser check: Noir and Bloom were previewed on the dev store (banner, announcement, header variants, typography, sections, cards, column footer), and the preview was exited.

## Phase 6 results (2026-10-17, local, PHP 8.4.25, SQLite)

- `php artisan test`: 388 tests, 387 passed, 1 skipped (MySQL group), 0 failed.
- Marketplace coverage (`tests/Feature/Marketplace`, 22 tests):
    - **End to end:** buy → license → activate (verified signed token) → download → verify → install → enable → new release → update → roll back, against the real server code (the client's HTTP calls are routed into the application).
    - **Integrity:** unsafe archives rejected at upload; wrong package and older or duplicate versions rejected; releases offered only after review; tampered metadata, unknown signing keys and corrupted downloads refused before any file changes.
    - **Authorization:** activation limits and deactivation; download tokens are short-lived, single use and need an activated instance; entitlement ends at `updates_until`; customers see and download only their own licenses; server routes 404 on merchant stores; admin permissions.
    - **Payments:** free products; paid licenses only after a verified webhook; wrong amounts issue nothing; refund and revocation block downloads, updates and activations while installed files stay.
    - **Outage:** with the marketplace unreachable, license checks record the error, the admin page renders with a notice, and the storefront and checkout work; the grace warning appears after token validity plus 14 days.
- `phpstan`, `pint`, `npm run types:check`, `npm run check`, `npm run build`: passed.
- Browser check (server mode temporarily enabled on the dev store): product and release admin, storefront catalog and product page, free license claim → order page → portal with key, license admin with history, and the store-side Marketplace page with the outage and missing-key notices. Found and fixed: portal downloads needed a full browser visit (`Inertia::location`).

## Phase 7 results (2026-10-13, local, PHP 8.4.25, SQLite)

- `php artisan test`: 415 tests, 414 passed, 1 skipped (MySQL group), 0 failed.
- New coverage:
    - `tests/Feature/Security`: headers, CSP nonce on every script tag, CDN image origins, report-only and off modes, HSTS, per-IP limits on password reset and registration.
    - `tests/Feature/Performance/QueryBudgetTest`: home, category, search, cart, admin orders and products, and account orders issue the same number of queries for 2 or 16 rows.
    - `tests/Feature/Operations/HealthAndBackupTest`:
        - the health endpoint with and without the token, and 503 when a check fails;
        - production configuration checks and the status page;
        - backup round trip, encryption and wrong password, tampered archive refused with nothing changed, unsafe paths, retention, restore confirmation.
    - `tests/Feature/Operations/ProductionPathsTest` (separate processes, throwaway database and file tree):
        - fresh install from an empty database, including refusing a re-run;
        - upgrade from the Phase 2 schema with data;
        - encrypted backup → data and files damaged → verify → restore.
    - Installer: an expired token is refused and replaced; the web installer can be disabled.
    - Settings: the last owner cannot delete their account; license holders get a message instead of a 500.
- `preventLazyLoading` and `preventSilentlyDiscardingAttributes` are on in tests: no lazy loads were found, and one discarded attribute was fixed.
- `composer audit`: 0 advisories. `npm audit`: 0 vulnerabilities after the `tinypool` override.
- Browser checks:
    - CSP enforced on storefront, cart, checkout, admin, settings and Appearance with no violations.
    - axe-core 4.10 (WCAG 2.0/2.1 A and AA) on home, product, cart, account, admin dashboard, products form, orders, Appearance, Marketplace and System status: 2 violation types found and fixed, 0 remaining.
    - No horizontal overflow at 375 px.
