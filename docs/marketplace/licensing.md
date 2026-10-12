# Licensing and the Official Marketplace

## The open-source core

The EcoModular core in this repository is licensed under the **MIT License** (Copyright (c) 2026 Eleydo). That covers the commerce core, the extension API, the basic module manager, the default theme and the developer documentation. You may use, modify and redistribute it, including commercially, under the MIT terms.

**The core never depends on a license server.** Storefront browsing, checkout, order management and every other core function work without contacting any EcoModular service.

## Commercial extensions (planned, Phase 6)

Premium themes, modules and integrations sold through the official marketplace may be distributed under **separate commercial license terms**. Those terms:

- apply only to the purchased extension, never to the MIT core
- may limit the number of activations (domains or installations) and the period of update entitlement
- never allow an extension to disable core storefront or checkout features

For version 1, the platform owner (Eleydo) is the **only publisher**. The data model is designed so third-party vendors can be added later, after seller verification, package review, signing and incident-response processes exist.

## License states and outages (planned)

License states are `active`, `expired`, `revoked`, `refunded` and `suspended`.

When the licensing service is temporarily unreachable, a configurable **grace period** applies:

| Capability                                   | Requires online validation                                    |
| -------------------------------------------- | ------------------------------------------------------------- |
| Core storefront and checkout                 | Never                                                         |
| Already-installed premium extension features | No; they keep working through outages within the grace period |
| Downloading packages and updates             | Yes (short-lived, authorized download links)                  |
| New activations                              | Yes                                                           |

## Package integrity (planned)

Releases ship with SHA-256 checksums and signed release metadata, verified against trusted public keys. A checksum detects corruption. Only a verified signature establishes who published the package. Neither proves that the code is safe.
