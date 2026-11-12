# Phase 6 Plan: Official Marketplace and Licensing

Branch: `feature/phase-6-marketplace` (based on `feature/phase-5-themes`). Each slice ships with tests and docs and is committed once the quality gates pass.

## Two roles, one codebase (ADR-033)

- **Marketplace server:** the platform owner runs an EcoModular instance with `ECOMODULAR_MARKETPLACE_SERVER=true`. It hosts:
    - the catalog, releases and marketplace checkout, paid with the owner's own Stripe account and kept completely separate from merchant orders and payments;
    - licenses and activations;
    - the license API and signed downloads.
- **Marketplace client:** every merchant store (enabled by default) talks to the server at `ECOMODULAR_MARKETPLACE_URL`. It:
    - browses the catalog and activates licenses;
    - checks entitled updates;
    - downloads packages through short-lived authorized links;
    - verifies the signature and checksum, backs up, installs into `modules/` or `themes/` and records the history;
    - never enables anything automatically.

Version 1 has a single publisher (the platform owner). The data model keeps a `publisher` concept so vendors can be added later; no public uploads.

## Trust and integrity (ADR-034)

- **Signing:** releases are signed with Ed25519 (libsodium, or `paragonie/sodium_compat` where the extension is missing). The owner keeps the private key in the server's environment; merchant stores ship the trusted public keys in config.
- **Signed metadata:** product, type, version, SHA-256, size, platform constraint, publication date and key id.
- **Client checks:** the client verifies the signature against a trusted key, then the SHA-256 and size of the downloaded file, then the manifest inside (its name and version must match the signed metadata). A failure at any step aborts before anything is written to `modules/` or `themes/`.
- **Archive safety:** release archives are scanned on upload and again on install for traversal, absolute paths, symlinks, size and file count limits. Theme archives must contain only data files.
- **Publishing:** a release is published only after validation passes and the owner approves the review.

## Licensing (ADR-035)

- **Keys:** shown once in the customer portal, stored encrypted and looked up by hash.
- **States:** `active`, `expired`, `revoked`, `refunded`, `suspended`.
- **Limits and entitlement:** each license has an optional activation limit and an update entitlement date (`updates_until`). Releases published after that date are not offered.
- **Activation:** each installation identifies itself with a random instance id (and its domain, for display). The server returns a signed license token valid for a configurable period.
- **Outages:** if the licensing service cannot be reached, nothing that already works stops:
    - the storefront and checkout never call the marketplace;
    - installed extensions keep running;
    - only new activations, downloads and updates need the server, and they report a clear error;
    - after the grace period the admin shows a warning; nothing is disabled automatically.
- **Refunds:** a refund, revocation or suspension stops downloads, updates and new activations at once; the merchant admin shows the license status on the next check.

## Slices

| #   | Slice                      | Key areas                                                                                                                                                                         |
| --- | -------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 6.1 | Catalog, releases, signing | Tables, publisher model, product admin, release upload → archive scan → manifest validation → checksum → review → publish → signed metadata; `ecomodular:marketplace:keygen`      |
| 6.2 | Marketplace checkout       | Storefront catalog and product pages, Stripe Checkout with separate credentials and webhook, idempotent order fulfilment, license issuance, free products, refunds and revocation |
| 6.3 | License API and downloads  | Activate, deactivate and check with signed tokens; update and entitlement checks; short-lived single-use download tokens; license events; rate limits                             |
| 6.4 | Merchant client            | Admin → Marketplace: catalog, license activation, install and update with verification, backup and rollback, update history, periodic checks with grace period                    |
| 6.5 | Portal, docs, tests        | Customer purchases, licenses, activations and downloads; marketplace admin for orders and licenses; end-to-end buy → license → activate → download → update test; docs and ADRs   |

## Acceptance criteria

- Unauthorized users and licenses (wrong key, wrong instance, refunded, revoked, expired entitlement, expired or reused download token) cannot download paid packages (tested).
- Tampered packages, tampered metadata, unknown signing keys and checksum mismatches are rejected before installation (tested).
- A marketplace or licensing outage does not affect the storefront, checkout or installed extensions (tested with the server unreachable).
- Refund and revocation block downloads, updates and activations (tested).
