# Phase 8 Plan: Post-launch Ecosystem

Branch: `feature/phase-8-ecosystem` (based on `v1.0.0-rc.1`). Phase 8 must not block 1.0: every slice is additive, off by default where it changes behaviour, and covered by tests. Each slice is committed once the quality gates pass.

| #   | Slice                         | Scope                                                                                                                                                                                                                        |
| --- | ----------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 8.1 | Developer SDK                 | Module and theme scaffolders, a package command that validates, scans and builds the archive the marketplace accepts, and a getting-started guide for extension developers                                                   |
| 8.2 | Package security scanning     | Static scanner for module PHP (code execution, shell, obfuscation, network, filesystem, raw SQL) and packaged files. It runs on marketplace upload and the reviewer sees the findings; it is also available as a CLI command |
| 8.3 | Security advisories           | Withdrawing a release for security reasons publishes a signed advisory. Stores receive it through the license API and show it in the admin and in the health check (closes F12 from the Phase 7 review)                      |
| 8.4 | Third-party sellers           | Seller applications and verification, approved publishers with members, a vendor portal (products, releases, sales, payouts), the owner's review queue, and commission and payout accounting in an append-only ledger        |
| 8.5 | Reviews and support           | License holders rate and review marketplace products (moderated); products show support contacts                                                                                                                             |
| 8.6 | Ecosystem samples and privacy | An industry theme and a marketing module (newsletter sign-up with consent); customers can download their personal data (closes R3)                                                                                           |
| 8.7 | Multi-store                   | An ADR records the decision to defer, and the intended architecture                                                                                                                                                          |

## Acceptance criteria

- A developer can scaffold a module and a theme that validate, install and enable without edits, then build a marketplace-ready package with one command (tested).
- Dangerous code patterns are reported with file and line on upload, and before any review decision (tested).
- A security advisory reaches a store through the signed API and is shown until the store updates; forged advisories are refused (tested).
- A seller cannot see or change another seller's products, releases, sales or payouts. Releases from sellers are published only after the owner's review. Sales, commissions, refunds and payouts balance in the ledger (tested).
- Only active license holders can review a product, once per product. Reviews appear only after moderation (tested).
- A customer's data export contains their profile, addresses and orders, and nothing about other customers (tested).
