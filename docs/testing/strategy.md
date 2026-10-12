# Testing Strategy

## Suites

| Suite        | Location             | Purpose                                                                                                                                                                  |
| ------------ | -------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Unit         | `tests/Unit`         | Pure logic with no framework boot: permission registry, redaction, role defaults. Later: pricing, discounts, state machines, manifest validation, dependency resolution. |
| Feature      | `tests/Feature`      | HTTP and console behaviour against a real database: auth, installer, RBAC matrix, escalation guards, audit, settings.                                                    |
| Architecture | `tests/Architecture` | Layer boundaries: `app/Core` and `app/Domains` must not import `Inertia\*` or `App\Http\*`.                                                                              |
| MySQL group  | `#[Group('mysql')]`  | Locking and concurrency tests (`SELECT … FOR UPDATE`, concurrent checkout) that are only meaningful on MySQL. Run in CI's MySQL job.                                     |
| End-to-end   | planned (Phase 2/7)  | Browser flows: test-mode purchase, module lifecycle, theme switching, marketplace purchase.                                                                              |

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
