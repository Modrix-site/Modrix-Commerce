# Testing Strategy

## Suites

| Suite        | Location                       | Purpose                                                                                                                                                                  |
| ------------ | ------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Unit         | `tests/Unit`                   | Pure logic with no framework boot: permission registry, redaction, role defaults. Later: pricing, discounts, state machines, manifest validation, dependency resolution. |
| Feature      | `tests/Feature`                | HTTP and console behaviour against a real database: auth, installer, RBAC matrix, escalation guards, audit, settings.                                                    |
| Architecture | `tests/Architecture`           | Layer boundaries: `app/Core` and `app/Domains` must not import `Inertia\*` or `App\Http\*`.                                                                              |
| MySQL group  | `#[Group('mysql')]` (Phase 2+) | Locking and concurrency tests (`SELECT … FOR UPDATE`, concurrent checkout) that are only meaningful on MySQL. Run in CI's MySQL job.                                     |
| End-to-end   | planned (Phase 2/7)            | Browser flows: test-mode purchase, module lifecycle, theme switching, marketplace purchase.                                                                              |

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
