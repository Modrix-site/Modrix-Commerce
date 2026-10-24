# Standalone Modrix Marketplace — design

Date: 2026-10-24. Status: design approved in chat ("go ahead").

## Goal

Rebuild `marketplace-catalog/` as a proper, separately deployable Laravel
application: the marketplace where publishers list themes and extensions and
merchants buy or claim them, receive a license key, and connect it from their
store (Admin → Marketplace). It takes from the store platform only what a
marketplace needs — database tables as migrations, the store license API and
payment webhooks — and runs on ordinary hosting.

Decisions (user): rebuild the existing catalog app (keep its features and API);
MySQL/MariaDB + SQLite; Stripe payments; leave the store platform's built-in
marketplace server code untouched.

## Non-goals

- No change to the store platform (`app/Core/Marketplace/Client` stays the
  client; its server tables stay as they are).
- No new payment providers beyond Stripe; no seller payouts.
- No Node/npm build on the server.

## Compatibility contract (must not break)

Stores call `{MARKETPLACE_URL}/api/marketplace/v1/` with JSON:

| Endpoint | Request | Response |
|---|---|---|
| `GET catalog` | — | `{products: [...]}` |
| `POST licenses/activate` | `key, instance_id, domain?, platform_version` | `{token, signature}` |
| `POST licenses/check` | `key, instance_id` | `{token, signature}` |
| `POST licenses/deactivate` | `key, instance_id` | `{ok: true}` |
| `POST updates` | `key, instance_id, installed_version?, platform_version` | `{release?: {...}, signature?}` |
| `POST downloads` | `key, instance_id, release_id` | `{url, expires_at, release}` |

Errors: non-2xx with `{error, message}`; 5xx means "temporarily unavailable".
The token is the same shape as today (`schema: 1, license_id, product,
extension, type, status, activated, instance_id, updates_until, issued_at,
valid_until (+30 days), advisories, key_id`), signed with Ed25519 over the same
canonical JSON as the platform's `App\Core\Marketplace\Signing`. Download URLs
start with the marketplace base URL and the file size equals the signed size.

## Application

- Laravel 13, PHP ≥ 8.3, namespace `Marketplace\` (`app/`).
- Standard structure: `app/Models`, `app/Http/Controllers/{Api,Admin,Account,Catalog}`,
  `app/Services` (Signing, Licensing, Releases, StripeGateway, LegacyImporter),
  `database/migrations`, `routes/{web,api}.php`, Blade views in `resources/views`.
- Pages are server-rendered Blade with the current look (plain CSS file in
  `public/`), so deployment needs only PHP and Composer.
- Sessions, cache and queue use the `database` driver by default.

## Data model (migrations)

- `users` — name, email (unique), password, `is_admin`, timestamps. Customers
  and publishers are users; admins sign in at `/admin/login`.
- `settings` — key/value; secrets (Stripe keys, webhook secret, SMTP password)
  stored encrypted; the Ed25519 private key is stored encrypted here too.
- `categories` — slug (fixed), name, description, type (theme|module|all),
  position, enabled.
- `packages` — identifier (`vendor/name`, unique), type (theme|module), slug
  (unique), name, summary, description, publisher_id (users), price (decimal),
  currency, is_free, status (draft|published|hidden), featured, demo_url,
  repository_url, thumbnail_path, features (json), category ids (pivot
  `category_package`), timestamps.
- `package_releases` — package_id, version, path, sha256, size,
  platform_version, signed_metadata (json), signature, published_at; unique
  (package_id, version).
- `submissions` — user_id, package identifier, kind (new|update), data (json),
  zip path, status (pending|approved|rejected), review_note, reviewed_by,
  reviewed_at.
- `orders` — user_id, package_id, amount, currency, stripe_session (unique),
  status (pending|paid|expired|refunded), license_id, paid_at.
- `licenses` — user_id, package_id, key (unique), type (free|paid), status
  (active|revoked|refunded), unique (user_id, package_id).
- `license_activations` — license_id, instance_id, domain, active,
  activated_at, deactivated_at; one active activation per license.
- `download_tokens` — token_hash (primary), license_id, release_id,
  instance_id, expires_at (10 minutes), used_at.
- `webhook_events` — provider, event_id (unique per provider), type,
  processed_at.
- Laravel `sessions`, `cache`, `jobs`, `password_reset_tokens`.

## Store license API rules

- Activate: key must exist and be active; a different active instance means
  `409 {error: "license_in_use"}`; same instance is idempotent. Records domain.
- Check: returns the current status token for that instance (inactive if not
  activated there).
- Deactivate: frees the instance.
- Updates: newest published release above `installed_version` that satisfies
  `platform_version` (composer/semver), with its signed metadata; else none.
- Downloads: issue a single-use token for a release of the licensed package;
  `GET /api/marketplace/v1/download/{token}` streams the ZIP once.
- Rate limit: 60 requests/minute per IP on the API.

## Webhooks (Stripe)

- `POST /stripe/webhook`: verify `Stripe-Signature` (HMAC-SHA256 with the
  webhook secret, 300 s tolerance); unknown or invalid → 400.
- Each event id is recorded in `webhook_events`; repeats return 200 without
  side effects.
- `checkout.session.completed` (payment_status paid): order → paid, license
  issued (or existing returned), receipt email queued.
- `checkout.session.expired`: order → expired.
- `charge.refunded`: order → refunded, license → refunded (store checks fail).

## Checkout

Hosted Stripe Checkout created server-side from the package price; success
page shows the license key once the webhook has marked the order paid (polls
the order). Free packages: "Get license" issues a free key immediately.

## Legacy import

`php artisan marketplace:import-legacy {path=storage/catalog.sqlite}` copies
admins and customers (password hashes kept), settings, categories, listings,
offers/prices, submissions, orders, licenses, activations, releases and the
signing key. Idempotent (skips existing rows by natural keys). Issued keys and
connected stores keep working.

## Install and deployment

- `php artisan marketplace:install` — migrates, creates the signing key if
  missing and the first admin (prompted). `/admin/setup` remains for web setup
  when no admin exists.
- Shared hosting: document root `public/`, `.env` (APP_URL, DB_*, MAIL_*),
  `php artisan migrate --force`, optional cron `* * * * * php artisan
  schedule:run` (prunes expired download tokens).
- `docs/DEPLOY.md` covers cPanel/shared hosting and a VPS (Nginx + PHP-FPM).

## Admin

Overview, Repository (edit packages, prices, visibility, featured, demo,
thumbnails, ZIPs), Releases (publish update), Submissions (approve/reject),
Categories, Licenses (search, revoke, release activation), Orders, Settings
(name, currency, Stripe, SMTP + test email, public key display).

## Testing

PHPUnit feature tests on SQLite: API contract (token verifies with the
platform's Signing algorithm), one-instance rule, updates/downloads ownership
and single-use tokens, Stripe webhook signature/idempotency/refund, free
license, checkout session creation (Stripe HTTP faked), admin approval
publishes a release, legacy import. MySQL run documented.

## Phases

1. Foundation: skeleton, migrations, models, Signing, license API, Stripe
   webhook, legacy import, install command, admin sign-in.
2. Pages: public catalog, account (licenses, activations), checkout, admin
   screens. The old `src/` code is removed when phase 2 covers it.
