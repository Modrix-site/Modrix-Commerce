# Roadmap & Progress Log

Status legend: ✅ Complete · 🟡 In progress · ⬜ Not started · ⛔ Blocked

| Phase | Scope                                                                                                   | Status |
| ----- | ------------------------------------------------------------------------------------------------------- | ------ |
| 0     | Discovery and technical plan                                                                            | ✅     |
| 1     | Application foundation (auth, owner install, RBAC, audit, settings, admin shell, CI)                    | ✅     |
| 2     | Commerce core (catalog, customers, cart, checkout, orders, inventory, discounts, shipping/tax, reports) | ⬜     |
| 3     | Payment integrations (gateway contract, Stripe test mode, webhooks, refunds)                            | 🟡     |
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

### Phase 2 — Commerce core

- [x] Catalog: products (simple + variants), SKUs, nested categories, attributes, media ordering, draft/published, slugs, SEO fields, search/filter/sort/paginate, CSV import/export with row-level error report.
- [x] Customers: profiles, address book, order history, guest checkout toggle.
- [x] Cart: guest (session token) + persistent user cart, merge on login, server-side repricing, changed-price/unavailable notices.
- [x] Checkout: flat-rate shipping methods (free-over threshold, country restrictions), rate-table tax (inclusive/exclusive), coupon validation, stock allocation under `SELECT … FOR UPDATE`, order creation in one transaction.
- [x] Orders: human-readable numbers, separate order/payment/fulfillment state machines, item/address/money snapshots, notes, history, cancellation rules, refund records.
- [x] Inventory: per-variant stock, movements ledger, adjustments, low-stock alert event, overselling policy, concurrency test (MySQL group; runs in CI only).
- [x] Discounts: coupon codes, % and fixed, dates, usage limits (global/per customer), minimum subtotal, product/category eligibility, rejection reasons.
- [x] Manual payment methods (bank transfer, cash on delivery), explicitly configured; no simulated card payments.
- [x] Notifications: order placed/paid/shipped/cancelled emails (queued, idempotent).
- [x] Reports: sales over time, order counts by status, top products, AOV, low stock, refund totals, all from persisted data.
- [x] Exit: no client-supplied prices trusted; snapshot and inventory tests pass; storefront purchase verified end to end via bank transfer.

Known limitations carried forward: weight-based shipping rates are not implemented (flat rate only); CSV import cannot add variants to an existing product; reports aggregate in PHP, which suits small and medium stores but should move to SQL aggregation in Phase 7; the MySQL locking test has not been run on this machine.

### Phase 3 — Payment integrations

- [x] `PaymentGateway` contract extending the Phase 2 `PaymentMethod` extension point: create session, retrieve, refund, webhook verification and normalisation.
- [x] Stripe in test mode as the reference gateway; secret keys stored encrypted and never logged or sent to the browser (no publishable key is needed with hosted Checkout).
- [x] `payment_webhook_events` with unique `provider + event_id`; signature verification before anything is stored; queued, retrying processing; admin event log.
- [x] Payment status changes only through `OrderWorkflow`; amount and currency verified on every confirmation.
- [x] Gateway refunds wired into the refund flow; refund webhooks reconcile them.
- [x] Replayed and forged webhooks are rejected or ignored by tests; a redirect alone cannot mark an order paid.
- [ ] **Test-mode purchase end to end against a real Stripe account.** Blocked on the merchant's Stripe test keys; see `docs/payments/stripe.md` §5. Phase 3 stays 🟡 until this passes.

### Phase 4 — Module engine (next)

- Manifest schema and validation, module lifecycle (install, enable, disable, update, uninstall, recovery), dependency resolution, permissions and settings registered by modules, sample modules.

## Progress log

### 2026-10-12 — Phase 0 & Phase 1

- Assessed runtime; selected PHP 8.4 (ADR-001); scaffolded Laravel 13 React starter kit (ADR-002).
- Wrote architecture overview, ADRs 001–011, schema docs, testing strategy, third-party notices.
- Implemented Phase 1 (see CHANGELOG `0.1.0`).
- Blockers/limitations: Docker daemon not running locally, so Docker Compose stack and MySQL-specific tests were validated only by `docker compose config` and the CI definition, not executed on this machine.

### 2026-10-12 — Phase 2

- Slices 2.1–2.9 implemented (see CHANGELOG `[Unreleased]` and `docs/plans/phase-2-commerce-core.md`); ADRs 012–024.
- Fixed during the phase: SQLite returning DECIMAL as float (ADR-017), SQLite LIKE escaping, stale product form state after create, non-ISO ICU region codes in the country list.
- Blockers/limitations: still no local MySQL or Docker, so the MySQL locking test runs in CI only. No GitHub remote, so commits are local.

### 2026-10-14 — Phase 3

- Slices 3.1–3.4 implemented (see CHANGELOG and `docs/plans/phase-3-payments.md`); ADRs 025–027; Stripe setup guide.
- Blockers/limitations: no Stripe test account on this machine, so the real test-mode purchase is still to be run (all provider interaction is covered with faked HTTP and correctly signed webhook fixtures). Still no local MySQL or Docker.

## Continuation instructions

1. Put PHP 8.4 first on PATH (`C:\php84` on the reference machine).
2. `composer install && npm install`, `cp .env.example .env`, `php artisan key:generate`, `php artisan migrate`.
3. Run quality gates: `composer test`, `npm run types:check`, `npm run check`, `npm run build`.
4. Close Phase 3 by running the Stripe test-mode purchase (`docs/payments/stripe.md` §5) with real test keys. Then start Phase 4: write `docs/plans/phase-4-modules.md` first.
