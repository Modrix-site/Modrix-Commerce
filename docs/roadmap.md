# Roadmap & Progress Log

Status legend: ✅ Complete · 🟡 In progress · ⬜ Not started · ⛔ Blocked

| Phase | Scope                                                                                                   | Status |
| ----- | ------------------------------------------------------------------------------------------------------- | ------ |
| 0     | Discovery and technical plan                                                                            | ✅     |
| 1     | Application foundation (auth, owner install, RBAC, audit, settings, admin shell, CI)                    | ✅     |
| 2     | Commerce core (catalog, customers, cart, checkout, orders, inventory, discounts, shipping/tax, reports) | ✅     |
| 3     | Payment integrations (gateway contract, Stripe test mode, webhooks, refunds)                            | 🟡     |
| 4     | Module engine (manifest, lifecycle, dependencies, sample modules)                                       | ✅     |
| 5     | Theme engine (manifests, contracts, default + 2 sample themes)                                          | ✅     |
| 6     | Official marketplace & licensing                                                                        | ✅     |
| 7     | Production hardening                                                                                    | ✅     |
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

### Phase 4 — Module engine

- [x] Manifest schema and validation (invalid manifests, unknown capabilities, conflicts, incompatible versions, cycles and path escapes rejected).
- [x] Module registry and lifecycle state machine with audit trail.
- [x] Dependency and version resolution.
- [x] Guarded service provider registration, quarantine, safe mode.
- [x] Contracts and events: checkout validators, search provider, storefront slots, dashboard widgets, plus the Phase 2–3 contracts; compatibility policy documented.
- [x] Settings and permissions from the manifest.
- [x] Migration lifecycle and documented error recovery (`docs/extensions/recovery.md`).
- [x] Extension admin interface.
- [x] Sample modules with a full install → enable → configure → disable → update lifecycle (Product Reviews).

Known limitations: no package upload yet (packages arrive through deployment; upload and signed downloads come with the marketplace); module UI requires an asset rebuild; product type/attribute providers are not yet an extension point.

### Phase 5 — Theme engine

- [x] Theme manifests and compatibility checks (invalid paths and packages rejected).
- [x] Layout and component contracts: design settings, layout variants and section types rendered by core components.
- [x] Storefront templates and sections (home sections; header, footer, card, grid and product page variants).
- [x] Theme configuration with merchant overrides kept separate from defaults and preserved across updates.
- [x] Preview / activate workflow, update with changelog, reset, demo content import with confirmation.
- [x] Default theme polish (Essence) and two premium-ready sample themes (Noir, Bloom).
- [x] Theme manager, developer guide, compatibility tests: switching themes does not break commerce.

Known limitations: section types cover the home page only (listing and product pages use variants, not sections); no drag-and-drop builder (deliberately, per the spec); themes cannot add components, by design (ADR-031).

### Phase 6 — Official marketplace and licensing

- [x] Marketplace server role behind `ECOMODULAR_MARKETPLACE_SERVER` (404 on merchant stores); single publisher (ADR-033).
- [x] Products, release upload with archive scan and manifest validation, review, Ed25519-signed metadata, withdrawal; `ecomodular:marketplace:keygen` (ADR-034).
- [x] Marketplace checkout with separate Stripe credentials and webhook; idempotent fulfilment with amount check; free products; refunds.
- [x] Licenses (hashed and encrypted keys, states, activation limits, update entitlement), signed tokens, license API, single-use download tokens, append-only license history (ADR-035).
- [x] Store client: catalog, activate/check/deactivate, verified install and update with backup, rollback, update history, daily check with grace warning.
- [x] Customer portal (keys, activations, downloads, orders) and marketplace admin (products, releases, orders and refunds, licenses).
- [x] Exit criteria tested: unauthorized users cannot download paid packages; integrity verification is enforced; a licensing outage does not break store operations; refund and revocation are tested.

Known limitations: no real Stripe test-mode purchase on the marketplace account (same blocker as Phase 3); rollback restores files, not database migrations; signing-key rotation is manual (documented); no third-party vendors (Phase 8).

### Phase 7 — Production hardening

- [x] Security review and dependency audit: 12 findings, all critical and high fixed (`docs/security/phase-7-review.md`); `composer audit` and `npm audit` clean.
- [x] Performance review and indexes: lazy-loading and discarded-attribute guards outside production; query-budget tests for list pages; cart write amplification removed; index coverage reviewed.
- [x] Queue and scheduler documentation (systemd, Supervisor, cron, heartbeat).
- [x] Backup and restore: `ecomodular:backup` and `ecomodular:restore`, drill demonstrated (process-level test), and run on MySQL in CI.
- [x] Installer hardening: expiring setup tokens, CLI-only option.
- [x] Error and health monitoring: `/health`, `ecomodular:health`, health card, log rotation and alert guidance.
- [x] Accessibility and responsive QA: axe-core WCAG 2.1 AA audit of key pages with no violations left; phone-width checks.
- [x] Production deployment guide, monitoring and troubleshooting guides, `SECURITY.md` with the takedown process.
- [x] License and attribution audit (`THIRD_PARTY_NOTICES.md`).
- [x] Release candidate `1.0.0-rc.1`.

Exit criteria:

- Critical tests pass (full suite).
- The restore process is demonstrated.
- No unresolved critical or high issue.
- Fresh installation and upgrade paths are tested (`ProductionPathsTest`).

Known limitations:

- The Stripe test-mode purchase with real keys is still pending (Phase 3), so the release stays a candidate until it passes.
- The MySQL backup drill and the MySQL test job run in CI only; there is no MySQL on the reference machine and no remote yet.
- No independent penetration test.
- No self-service personal-data export.
- Marketplace security advisories are not pushed to stores (Phase 8).
- Reports still aggregate in PHP (planned in Phase 2 for this phase): fine for small and medium stores; move to SQL aggregation before very large catalogs or order volumes.

### Phase 8 — Post-launch ecosystem (next)

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

### 2026-10-15 — Phase 4

- Slices 4.1–4.5 implemented (see CHANGELOG and `docs/plans/phase-4-modules.md`); ADRs 028–030; module developer and recovery guides.
- Found and fixed through a process-level test: enabled modules did not load on a fresh boot (deferred cache and the Eloquent resolver are not ready while providers register), and the module autoloader could attach to the wrong Composer loader.
- Phase 3 remains 🟡 until the Stripe test-mode purchase is run with real test keys.

### 2026-10-16 — Phase 5

- Slices 5.1–5.5 implemented (see CHANGELOG and `docs/plans/phase-5-themes.md`); ADRs 031–032; theme developer guide.
- Design change from Phase 0: themes are data-only (no compiled React bundles) so marketplace themes cannot run code.
- Phase 3 remains 🟡 until the Stripe test-mode purchase is run with real test keys.

### 2026-10-17 — Phase 6

- Slices 6.1–6.5 implemented (see CHANGELOG and `docs/plans/phase-6-marketplace.md`); ADRs 033–035; licensing guide rewritten.
- Added `paragonie/sodium_compat` because the reference machine's PHP has no sodium extension.
- Stripe REST access moved into a shared `StripeApi` used by the merchant gateway and the marketplace.
- Phase 3 remains 🟡 until the Stripe test-mode purchase is run with real test keys.

### 2026-10-13 — Phase 7

- Slices 7.1–7.7 implemented (see CHANGELOG `1.0.0-rc.1`); ADRs 036–037; security report; deployment, monitoring, troubleshooting and backup guides.
- Fixed during the phase:
    - pages had no CSP or frame protection;
    - the last owner could delete their own account;
    - conflicting Nginx headers;
    - no per-IP limit on password reset and registration;
    - installer tokens never expired;
    - a dev-tooling RCE advisory;
    - cart write amplification;
    - account deletion with marketplace licenses returned 500.
- Phase 3 remains 🟡 until the Stripe test-mode purchase is run with real test keys.

## Continuation instructions

1. Put PHP 8.4 first on PATH (`C:\php84` on the reference machine).
2. `composer install && npm install`, `cp .env.example .env`, `php artisan key:generate`, `php artisan migrate`.
3. Run quality gates: `composer test`, `npm run types:check`, `npm run check`, `npm run build`.
4. Close Phase 3 by running the Stripe test-mode purchase (`docs/payments/stripe.md` §5) with real test keys.
5. Start Phase 6: write `docs/plans/phase-6-marketplace.md` first; marketplace packages must feed the existing module and theme lifecycles, never auto-enable.
