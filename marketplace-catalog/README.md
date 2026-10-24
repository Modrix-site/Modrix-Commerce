# Modrix Marketplace

The marketplace for Modrix Commerce themes and extensions: a public catalog,
customer accounts with license keys, Stripe checkout, publisher submissions,
and the license and update API that merchant stores connect to.

It is a standalone Laravel 13 application with its own MySQL/MariaDB
database. It shares no database or login with any store, so it can
run on any PHP host. See [docs/DEPLOY.md](docs/DEPLOY.md) for shared hosting
(cPanel), VPS and Stripe setup.

## Local setup

Create an empty MySQL/MariaDB database, then:

```bash
cd marketplace-catalog
composer install
php artisan serve --port=8090
```

Open `http://127.0.0.1:8090`. The web installer at `/install` asks for the
MySQL connection, creates the tables, and then creates the first
administrator. After that it closes for good and `/admin` is the workspace.
With a terminal you can instead fill `DB_*` in `.env` and run
`php artisan key:generate`, `php artisan migrate --seed` and
`php artisan marketplace:install`.

Moving from the previous catalog app (`storage/catalog.sqlite`)? Run
`php artisan marketplace:import-legacy storage/catalog.sqlite` after
`migrate`. Accounts, packages, licenses, activations, orders, submissions and
release files are copied and the signing key is kept, so connected stores keep
working.

## What it does

**Catalog** (`/`, `/item/{slug}`): themes and extensions with categories,
search, free/paid and live-demo filters, sorting and pagination. Free packages
download from `/download/{slug}`; paid ones for customers who own them.

**Accounts** (`/account`): register, sign in, generate a license for a free
package or see the key of a bought one, and disconnect a store to move a key.
One key per customer and package; each key is active on one store at a time.

**Checkout** (`/buy/{slug}`): Stripe Checkout. The signed webhook at
`/stripe/webhook` marks the order paid, issues the license and emails the key.
Refunds revoke the license.

**Publishers** (`/submit`, `/submit/update`, `/submissions`): upload a ZIP with
`module.json` or `theme.json` at its root; name and version must match the
form. An admin approves or rejects each submission with a note.

**Admin** (`/admin`): overview, repository (edit listings, thumbnails, prices,
status, categories and releases), submissions, updates, categories, pricing,
licenses (search, disconnect, revoke), orders, and settings (name, currency,
logo, Stripe, SMTP with a test email, and the signing public key).

**Store API** (`/api/marketplace/v1/…`): `catalog`, `licenses/activate`,
`licenses/check`, `licenses/deactivate`, `updates`, `downloads` and one-time
`download/{token}` links. Responses are signed with Ed25519; stores trust the
public key shown under Admin → Settings → Licensing.

**Publishing from the platform repository**:
`php artisan marketplace:publish vendor/package` zips that module or theme from
`CATALOG_ROOT` and publishes it as a signed release.

## Files and security

- Thumbnails and logos: PNG, JPEG or WebP up to 3 MB (no SVG).
- Package ZIPs: up to 20 MB, 100 MB unpacked, no unsafe paths or symlinks. ZIPs
  are stored privately and never extracted or run by the marketplace; review
  the code before approving.
- Private files live in `storage/app/private` (`MARKETPLACE_STORAGE`), outside
  the web root. Serve only `public/`.
- Stripe and SMTP secrets and the signing key are stored encrypted with
  `APP_KEY`. Settings pages never show saved secrets; leaving a field blank keeps
  the saved value.
- Back up the database, `storage/app/private` and `.env`.

## Tests

```bash
php vendor/bin/phpunit
```

The tests run on an in-memory SQLite database. They make no payments and send
no email.
