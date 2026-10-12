# Roadmap & Progress Log

Status legend: ✅ Complete · 🟡 In progress · ⬜ Not started · ⛔ Blocked

| Phase | Scope                                                                                                   | Status |
| ----- | ------------------------------------------------------------------------------------------------------- | ------ |
| 0     | Discovery and technical plan                                                                            | ✅     |
| 1     | Application foundation (auth, owner install, RBAC, audit, settings, admin shell, CI)                    | ✅     |
| 2     | Commerce core (catalog, customers, cart, checkout, orders, inventory, discounts, shipping/tax, reports) | ⬜     |
| 3     | Payment integrations (gateway contract, Stripe test mode, webhooks, refunds)                            | ⬜     |
| 4     | Module engine (manifest, lifecycle, dependencies, sample modules)                                       | ⬜     |
| 5     | Theme engine (manifests, contracts, default + 2 sample themes)                                          | ⬜     |
| 6     | Official marketplace & licensing                                                                        | ⬜     |
| 7     | Production hardening                                                                                    | ⬜     |
| 8     | Post-launch ecosystem (does not block 1.0)                                                              | ⬜     |

## MVP (v1.0) scope

Exactly the "Definition of Done for Version 1.0" in the master specification (§20). Anything in Phase 8 is out of scope for 1.0.

## Phase acceptance criteria

### Phase 1 — Application foundation

- [x] Fresh `migrate:fresh --seed` works on SQLite (MySQL verified in CI job).
- [x] Owner can be created by `php artisan ecomodular:install` or the token-gated web installer; installer is unreachable afterwards.
- [x] Seven system roles and granular permissions exist; server enforces them on every admin route.
- [x] Role escalation guard (subset rule, owner-only owner grants, no self-edit, last-owner protection) is tested.
- [x] Sensitive admin actions require password re-confirmation.
- [x] Audit log records security-relevant actions with secret redaction.
- [x] Store settings persist and are permission-protected.
- [x] Admin shell: permission-filtered navigation, responsive sidebar, no gradients.
- [x] CI: Pint, Larastan, `tsc`, lint, build, PHPUnit on SQLite and MySQL.

### Phase 2 — Commerce core (next)

- Catalog: products (simple + variants), SKUs, nested categories, attributes, media ordering, draft/published, slugs, SEO fields, search/filter/sort/paginate, CSV import/export with row-level error report.
- Customers: profiles, address book, order history, guest checkout toggle.
- Cart: guest (session token) + persistent user cart, merge on login, server-side repricing, changed-price/unavailable notices.
- Checkout: shipping method selection (manual flat/weight-based), tax strategy (configurable rate table, inclusive/exclusive), coupon validation, stock reservation under `SELECT … FOR UPDATE`, order creation in one transaction.
- Orders: human-readable numbers, separate order/payment/fulfillment state machines, item/address/money snapshots, notes, audit history, cancellation rules, refund records.
- Inventory: per-variant stock, movements ledger, adjustments, low-stock alert event, overselling policy, concurrency tests (MySQL group).
- Discounts: coupon codes, % and fixed, dates, usage limits (global/per customer), minimum subtotal, product/category eligibility, rejection reasons.
- Manual payment method ("Bank transfer / Cash on delivery") explicitly configured — no simulated card payments.
- Notifications: order placed/paid/shipped/cancelled emails (queued).
- Reports: sales over time, order counts by status, top products, AOV, low stock, refund totals — all from persisted data.
- Exit: no client-supplied prices trusted; snapshot and inventory tests pass; storefront purchase end-to-end via manual payment.

## Progress log

### 2026-10-12 — Phase 0 & Phase 1

- Assessed runtime; selected PHP 8.4 (ADR-001); scaffolded Laravel 13 React starter kit (ADR-002).
- Wrote architecture overview, ADRs 001–011, schema docs, testing strategy, third-party notices.
- Implemented Phase 1 (see CHANGELOG `0.1.0`).
- Blockers/limitations: Docker daemon not running locally, so Docker Compose stack and MySQL-specific tests were validated only by `docker compose config` and the CI definition, not executed on this machine.

## Continuation instructions

1. Put PHP 8.4 first on PATH (`C:\php84` on the reference machine).
2. `composer install && npm install`, `cp .env.example .env`, `php artisan key:generate`, `php artisan migrate`.
3. Run quality gates: `composer test`, `npm run types:check`, `npm run check`, `npm run build`.
4. Start Phase 2 from the acceptance criteria above; create `app/Domains/Catalog` first, then Inventory, Cart, Checkout, Orders.
