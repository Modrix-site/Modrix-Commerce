# Licensing and the Official Marketplace

## The open-source core

The Modrix Commerce core in this repository is licensed under the **MIT License** (Copyright (c) 2026 Orlando Eleydo). That covers the commerce core, the extension API, the module and theme managers, the marketplace client and server code, the default theme and the developer documentation. You may use, modify and redistribute it, including commercially, under the MIT terms.

**The core never depends on a license server.** Storefront browsing, checkout, order management and every other core function work without contacting any Modrix Commerce service. The storefront and checkout contain no marketplace calls at all (tested with the marketplace unreachable).

## Commercial extensions

Premium themes, modules and integrations sold through the official marketplace may be distributed under **separate commercial license terms**. Those terms:

- apply only to the purchased extension, never to the MIT core;
- may limit the number of activations (stores) and the period of update entitlement;
- never allow an extension to disable core storefront or checkout features.

The platform owner (Eleydo) publishes official extensions. Since Phase 8, verified third-party sellers can publish too (see [Third-party sellers](#third-party-sellers)). Every release, from anyone, is reviewed and signed by the marketplace before it is offered.

## Two roles (ADR-033)

| Role                      | Where                                                                                 | What it does                                                                                                                                                                                                                          |
| ------------------------- | ------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Marketplace server**    | The platform owner's instance, `ECOMODULAR_MARKETPLACE_SERVER=true`                   | Catalog, release review and signing, marketplace checkout (the owner's own Stripe account), licenses, activations, the license API and signed downloads. Off by default: its routes return 404 on every merchant store.               |
| **Modrix Cloud** (client) | Every store: Admin → Modrix Cloud, from the bundled **Modrix Cloud Connector** module | Connect the store to a Modrix Cloud account, browse the catalog, install free and owned packages in one click, activate license keys, install and update packages after verification, restore previous files, see the update history. |

## Buying and using a license

1. The customer buys (or gets, if free) a product on the marketplace. Paid orders are fulfilled only after Stripe confirms the payment and the amount matches; the license appears in **My licenses** (`/account/marketplace`) with its key.
2. In their store, the merchant opens **Admin → Modrix Cloud** and either chooses **Connect** (the account's licenses are added automatically, see [Connecting a store](#connecting-a-store)) or **Add license key** and enters the key (`EMK-XXXXX-XXXXX-XXXXX-XXXXX`). The store counts as one activation.
3. **Install** or **Update** downloads the release through a single-use link, verifies it and places the files in `modules/` or `themes/`. Nothing is installed in the database or enabled: the merchant then installs, migrates and enables it under **Extensions** or **Appearance**, as with any package.
4. **Restore previous files** puts back the files saved before an update. Database migrations that already ran are not undone; restore a database backup for that.

Customers can release an activation from **My licenses** (for example after moving a store); merchants can do the same with **Remove from this store**. Neither changes installed files.

## License states

| State       | Activate | Check / token | Updates and downloads                     | Set by                                   |
| ----------- | -------- | ------------- | ----------------------------------------- | ---------------------------------------- |
| `active`    | Yes      | Yes           | Yes, while `updates_until` has not passed | Purchase, reinstatement                  |
| `expired`   | No       | Reported      | No                                        | Expiry date                              |
| `suspended` | No       | Reported      | No                                        | Admin (reason required), reversible      |
| `revoked`   | No       | Reported      | No                                        | Admin (reason required)                  |
| `refunded`  | No       | Reported      | No                                        | Refund from the admin or a Stripe refund |

A release is offered only if it was published on or before the license's `updates_until` date. Every change (issue, activation, deactivation, download, status change, extension) is written to the append-only license history and the audit log.

## Outages and the grace period

The license API returns a **signed token** (Ed25519) valid for `ECOMODULAR_MARKETPLACE_TOKEN_DAYS` (30). The store verifies the token against its trusted keys and checks it was issued for its own instance id.

| Capability                          | Needs the marketplace                         |
| ----------------------------------- | --------------------------------------------- |
| Core storefront and checkout        | Never                                         |
| Installed extensions                | Never: nothing is disabled automatically      |
| Admin → Modrix Cloud page           | No: it shows a notice and the stored licenses |
| New activations, downloads, updates | Yes, with a clear error when unreachable      |

The daily `modrix:cloud:check` command (scheduled by the Modrix Cloud Connector) refreshes every license. If the token's validity plus `ECOMODULAR_MARKETPLACE_GRACE_DAYS` (14) has passed without a successful check, the admin shows a warning. That is all: installed files keep working.

## Connecting a store

The **Modrix Cloud Connector** module (bundled in `modules/modrixcommerce/cloud-connector`) links a store to a Modrix Cloud account, so packages install without copying keys.

1. **Admin → Modrix Cloud → Connect** sends the browser to Modrix Cloud's `/connect` page. The request carries the store's instance id, name, domain, return address, a random `state` and a PKCE `code_challenge` (S256). The verifier and `state` stay in the admin's session.
2. The customer signs in and approves. Modrix Cloud only sends the browser back to an address on the store's own domain (https, or http for local development), with a one-time code that is valid for 5 minutes.
3. The store checks `state`, then exchanges the code and its verifier server to server for a connection token. The token is stored encrypted on the store and never reaches the browser; Modrix Cloud keeps only its SHA-256.
4. Connected, the catalog shows **Owned**, **Free** or the price for each item. **Install** asks Modrix Cloud for the account's license (it issues one for a free package; unpurchased paid packages are refused) and activates and installs it through the same signed and verified flow as a typed key. A license active on another store offers **Move here**.
5. **Disconnect** (store) or **Account → Connected stores → Disconnect** (Modrix Cloud) revokes the token. Licenses already on the store keep working.

The connector has the official cloud's address and signing key built in, so it needs no configuration and an admin cannot be talked into trusting another server. `ECOMODULAR_MARKETPLACE_URL` (another server, for development) and `ECOMODULAR_MARKETPLACE_TRUSTED_KEYS` (extra keys) remain server-only settings.

## Package integrity (ADR-034)

- **On upload (server):** the archive is scanned (no path traversal, absolute paths or symlinks; size and file-count limits), its manifest is validated with the module or theme validator, and its package name must match the product. The version must be higher than every earlier release.
- **On approval:** the owner confirms the review; the archive is hashed again and the release metadata (product, package, type, version, platform constraint, SHA-256, size, publication date, key id) is signed with the marketplace's Ed25519 key.
- **On install (store):** the store refuses the package unless
    1. the metadata signature verifies against a trusted public key;
    2. the signed product, package name and type match the license;
    3. the platform constraint is satisfied;
    4. the downloaded size and SHA-256 match the signed values;
    5. the archive passes the same scan and validation, and its manifest name and version match.

    Only then are the current files backed up and the new ones swapped in.

A checksum detects corruption; only a verified signature establishes who published the package. **Neither proves that the code is safe.** That is why releases are reviewed before signing, and why modules still require the merchant's explicit trust confirmation before they are enabled (ADR-029).

## Third-party sellers

Sellers apply from `/vendor` (marketplace server). The application asks for:

- the seller name and the **vendor name** for packages;
- the legal name, country and website;
- the payout and support emails;
- a description of what they will sell;
- acceptance of the seller terms.

The owner reviews each application under Admin → Marketplace → Sellers and sets the platform commission when approving it.

Once approved, a seller works in the seller portal:

- **Products:** creates products whose package names must start with its vendor name (`acme/…`). Reserved names such as `ecomodular` cannot be claimed.
- **Releases:** uploads packages, which are validated, scanned (Phase 8.2) and wait for the owner's review. The owner reviews and signs them like official releases, and decides when a product is listed. Sellers can move their products back to draft or retire them.
- **Statement:** sees sales, commission, balance and payouts.

Accounting uses an append-only ledger, one entry per sale, refund and payout:

| Entry  | Gross  | Commission         | Net (owed to the seller) |
| ------ | ------ | ------------------ | ------------------------ |
| Sale   | price  | price × commission | gross − commission       |
| Refund | − sale | − sale commission  | reverses the sale        |
| Payout | 0      | 0                  | − amount paid            |

The balance is the sum of the net amounts per currency. Payouts are made outside the application (bank transfer, PayPal…) and recorded by the owner with a reference; a payout cannot exceed the balance. Platform products have no ledger entries.

Suspending a seller hides its products, refuses new purchases and pauses its uploads. Licenses already sold keep working, and the balance stays owed. Sellers can never see each other's products, releases, sales or payouts.

## Reviews and support

Customers with an **active** license can review a product once (rating from 1 to 5 and a text). Reviews appear on the product page after the owner approves them under Admin → Marketplace → Reviews. Product pages also show the publisher and its support contacts (the product's support URL and email, or the seller's support email).

## Security advisories

When a released version has a security problem, the owner publishes an advisory on the product. It gives a severity, a title, details, the affected versions (a Composer constraint such as `<1.2.3`) and the fixed version. Advisories travel inside the **signed** license token, so stores receive them on their next license check, whatever the license status: even refunded or expired licenses keep receiving security information. A store running an affected version sees:

- the advisory on Admin → Modrix Cloud;
- a warning from the health check (`Extension advisories`).

Nothing is disabled automatically. The merchant updates, or disables the extension. A tampered advisory fails signature verification and is ignored.

## Configuration

Store (client):

| Variable                              | Default                                 | Purpose                                      |
| ------------------------------------- | --------------------------------------- | -------------------------------------------- |
| `ECOMODULAR_MARKETPLACE_URL`          | `https://cloud-apps.modrixcommerce.com` | Marketplace server                           |
| `ECOMODULAR_MARKETPLACE_TRUSTED_KEYS` | (empty)                                 | Comma-separated Ed25519 public keys (base64) |
| `ECOMODULAR_MARKETPLACE_GRACE_DAYS`   | `14`                                    | Days after token expiry before the warning   |
| `ECOMODULAR_MARKETPLACE_TIMEOUT`      | `10`                                    | HTTP timeout in seconds                      |

Marketplace server only:

| Variable                                  | Default | Purpose                                                                              |
| ----------------------------------------- | ------- | ------------------------------------------------------------------------------------ |
| `ECOMODULAR_MARKETPLACE_SERVER`           | `false` | Enables the server routes and admin                                                  |
| `ECOMODULAR_MARKETPLACE_SIGNING_KEY`      |         | Ed25519 secret key from `php artisan ecomodular:marketplace:keygen`; never commit it |
| `ECOMODULAR_MARKETPLACE_TOKEN_DAYS`       | `30`    | License token validity                                                               |
| `ECOMODULAR_MARKETPLACE_DOWNLOAD_MINUTES` | `10`    | Download link validity (single use)                                                  |
| `ECOMODULAR_MARKETPLACE_MAX_PACKAGE_MB`   | `50`    | Largest accepted release archive                                                     |
| `MARKETPLACE_STRIPE_SECRET`               |         | The owner's Stripe secret key for marketplace sales                                  |
| `MARKETPLACE_STRIPE_WEBHOOK_SECRET`       |         | Signing secret of the `/webhooks/marketplace/stripe` endpoint                        |

The marketplace Stripe account is separate from any merchant's Stripe settings. Its webhook endpoint must send `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `refund.created` and `refund.updated`.

### Rotating the signing key

Generate a new key pair, add the new public key to `ECOMODULAR_MARKETPLACE_TRUSTED_KEYS` in a core release (stores then trust both keys), switch the server to the new secret key, and remove the old public key in a later release. Each signature names its key id, so old and new signatures verify during the overlap.

## License API (v1)

Stateless JSON endpoints under `/api/marketplace/v1`, rate limited (60 per minute per IP):

| Method and path            | Body                                                          | Returns                                                        |
| -------------------------- | ------------------------------------------------------------- | -------------------------------------------------------------- |
| `GET catalog`              |                                                               | Published products and latest versions                         |
| `POST licenses/activate`   | `key`, `instance_id`, `domain`, `platform_version`            | Signed token                                                   |
| `POST licenses/check`      | `key`, `instance_id`                                          | Signed token                                                   |
| `POST licenses/deactivate` | `key`, `instance_id`                                          | Confirmation                                                   |
| `POST updates`             | `key`, `instance_id`, `installed_version`, `platform_version` | Signed token and the entitled release with its signed metadata |
| `POST downloads`           | `key`, `instance_id`, `release_id`                            | Single-use URL valid for 10 minutes                            |

Errors carry a stable `error` code (`invalid_key`, `license_inactive`, `activation_limit`, `not_activated`, `not_entitled`, …). `GET /marketplace/download/{token}` streams the archive with an `X-Package-Sha256` header and invalidates the token.
