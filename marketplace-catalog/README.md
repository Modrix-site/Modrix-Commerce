# Modrix Marketplace

An independent marketplace for themes and extensions, with its own SQLite database, customer accounts, and admin login. It does not use the merchant store's admin credentials or database.

This directory is its own Laravel application. It has its own `.env` and its own SQLite database at `storage/catalog.sqlite`, so it can run on the host that serves modrixcommerce.com or on another server. Merchant stores do not share this database. A store connects by setting `ECOMODULAR_MARKETPLACE_URL` to this app's public URL and `ECOMODULAR_MARKETPLACE_TRUSTED_KEYS` to the public key shown under **Admin → General settings → Store licenses**.

```powershell
cd marketplace-catalog
copy .env.example .env
composer install
php artisan key:generate
php artisan serve --host=127.0.0.1 --port=8090
```

Point the web server's document root at `public/` when you deploy it. Back up `.env` and the whole `storage/` directory. `CATALOG_ROOT` is the directory scanned for themes and extensions; on a separate server set it to the package source you publish. `DB_DATABASE` is the marketplace database file.

A developer registers here, generates a free license or buys a paid package, then enters that key on the store's Admin → Marketplace page. Install pulls the signed package from this server into the store.


Open `/admin`. A new installation offers `/admin/setup` to create the first administrator; existing installations use `/admin/login`.

## Workspace

The public catalog uses a solid-color interface with separate theme/extension
collections, category browsing, search, free/paid and live-demo filters, featured
ordering, price sorting, recently updated sorting and pagination. Detail pages
include a large preview, package specifications, optional feature lists and
changelog, license actions and related packages.

**Demo URLs:** publishers can provide an HTTPS live-demo link when submitting a
package or an update. Admins can add, replace or clear it in Repository → Edit
package. Demo links are reviewed with the submission, included in the catalog
API, and displayed on cards and detail pages only when provided. Demos open in a
separate tab; the marketplace does not fetch or embed their content. URLs with
scripts, non-HTTPS schemes or embedded credentials are rejected.

**Categories:** Admin → Categories creates, renames, orders or hides categories.
Each can be for themes, extensions or both. Select categories in the package
editor or submission form. Hiding keeps assignments for later restoration.
Initial categories include Electronics, Fashion & lifestyle, Home & living,
Payments, Marketing & sales and Store operations. Local packages without saved
assignments receive suggestions based on their metadata; admin selections take
precedence. Category slugs remain fixed after creation to preserve links.

**Currency:** new catalogs default to USD. A previously saved currency choice is
kept; change it in General settings when appropriate. Existing orders retain
their recorded currency. Changing the catalog currency does not convert amounts.
Admins can also mark featured listings and add up to eight publisher-supplied
key features per package.

- **Overview:** package counts, paid listings, and the review queue.
- **Repository:** search themes/extensions; edit metadata, thumbnails, prices, visibility, source URLs, and downloadable ZIPs. Packages are discovered from `themes/*/*/theme.json` and `modules/*/*/module.json`, then copied into `storage/library`. Deleting a theme in a store's Appearance screen, or removing an extension from that store, does not remove this marketplace copy. Marketplace edits are metadata overrides; they do not modify a store's installed files.
- **Pricing:** manage free and paid prices in the configured catalog currency.
- **Submissions & approvals:** review publisher information, repository links and ZIPs, then approve or reject with a reason. Decisions record the administrator and time. Approval publishes the package and its price together. Duplicate package identifiers and conflicting listing URLs are rejected.
- **General settings:** marketplace name, tagline, currency, support address, logo, Stripe keys/webhook secret, and SMTP connection/from details plus a test email form. Saved secrets are not sent back in HTML; blank fields retain them.

Publishers register/sign in, submit a new package at `/submit`, and submit a newer version of a listed package at `/submit/update`. Decisions are tracked at `/submissions`. Administrators can publish an update directly at `/admin/updates`. Approving an update stores that version's ZIP and signed release, so a store installs it the next time it checks for updates. Rejected packages can be corrected and submitted again. Pending/rejected submissions never appear in public listings or the catalog API. Drafts are also excluded.

## Customer accounts and licenses

The redesigned login/register pages preserve the requested destination, support password visibility, and require matching passwords on registration. Customers manage their collection at `/account`.

Use **Generate a license** to claim a key for a free package or retrieve a purchased package's key. Paid packages require a completed purchase. One stable key is issued per customer/package; repeated generation does not rotate it. Existing paid-order keys are preserved. Customers can copy keys, see connected stores, and release an activation to move a key to another store. Each license allows one active store instance. Other accounts cannot see or release its keys.

Published themes and extensions are stored as zip files under `storage/sources/{type}/{vendor}/{name}/{version}.zip`. `storage/sources/index.json` lists each package's type, extension, and version. A store's license check asks this server for a newer signed release and, when one exists, installs it on that hosted store.

The server implements `POST /api/marketplace/v1/licenses/activate`, `/licenses/check`, `/licenses/deactivate`, `/updates`, and `/downloads`. License responses use the platform's shared Ed25519 signer and canonical JSON format. The server generates and stores a persistent private signing key; back it up with the database. The matching public key appears under **Admin → General settings → Store licenses**.

Before a merchant store can activate keys, configure its `ECOMODULAR_MARKETPLACE_URL` and add this public key to `ECOMODULAR_MARKETPLACE_TRUSTED_KEYS` (preserving any existing trusted keys), then run `php artisan config:clear` in that store. Trust configuration is not changed automatically. Licenses have no entitlement expiry; signed status tokens last 30 days and are refreshed by the store's checks. Automatic update installation is not advertised: the API returns no signed update release. Available ZIPs remain downloadable through the marketplace.

## Files and downloads

Logos/thumbnails accept PNG, JPEG and WebP up to 3 MB. SVG uploads are rejected. ZIP packages accept up to 20 MB compressed / 100 MB unpacked, with `theme.json` or `module.json` at the archive root. The manifest's package identifier and version must match the listing. Unsafe archive paths are rejected. Configure PHP `upload_max_filesize` and `post_max_size` to accommodate these limits.

Uploads are stored in `storage/`, outside the public directory. Package archives are never extracted or executed by the marketplace. An administrator should review source code before approving it. Free published ZIPs are available at `/download/{slug}`. Paid ZIPs require a signed-in customer with a recorded paid purchase. Admins can download pending ZIPs for review.

The catalog API at `/api/marketplace/v1/catalog` includes approved published packages, prices, thumbnail URLs, repository links, and download URLs. Repository URLs are public listing metadata; keep proprietary source in a private repository or supply only a ZIP.

Stripe uses hosted Checkout and signed webhook events at `/stripe/webhook` (`checkout.session.completed`). Configure real Stripe and SMTP credentials before using live checkout or sending email. The test suite does not make payments or send email.

## Storage and deployment

Back up the entire `storage/` directory. It contains accounts, settings (including service credentials), purchases, listings, submissions, and uploaded files. Keep it private, outside the web root, and restrict filesystem permissions to the application user. The SQLite tables are created additively on startup. Serve only `public/`; the PHP development server is for local use.

`CATALOG_STORAGE` overrides the storage location, and `CATALOG_ROOT` overrides the directory scanned for local themes/modules. These are server environment variables, not request parameters.

## Verification

```powershell
php marketplace-catalog/tests/workflow.php
```

The integration script starts an isolated local PHP server on an available port with a temporary database. It checks authentication, CSRF, settings, secret masking, image validation, ZIP validation, submission isolation, duplicate handling, approvals/rejections, catalog publication, visibility, pricing and download ownership. It leaves the temporary fixture directory for diagnosis and stops the test server on completion.
