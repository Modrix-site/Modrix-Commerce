# Database Schema

Target: MySQL 8 (InnoDB, `utf8mb4_unicode_ci`). SQLite is supported for evaluation and the fast test suite. Migrations must stay portable (ADR-008).

Conventions:

- Money: `DECIMAL(19,4)` plus an explicit `CHAR(3)` ISO-4217 currency column. Never a float (ADR-007).
- Financial and audit history is never cascade-deleted. Foreign keys to `users` from history tables use `nullOnDelete`.
- JSON only for flexible extension settings, audit metadata and structured attributes. Never for whole orders, products or customers.
- Every lookup, status/date filter, SKU, slug, order number and external provider reference is indexed.

## Phase 1 tables (implemented)

### `users` (Laravel + Fortify, extended)

| Column                                       | Type                    | Notes                                 |
| -------------------------------------------- | ----------------------- | ------------------------------------- |
| id                                           | bigint PK               |                                       |
| name, email                                  | string                  | `email` unique, stored lowercase      |
| email_verified_at                            | timestamp null          |                                       |
| password                                     | string                  | bcrypt/argon hash                     |
| two_factor_secret, two_factor_recovery_codes | text null               | encrypted by Fortify                  |
| two_factor_confirmed_at                      | timestamp null          |                                       |
| remember_token                               | string null             | cleared when an account is disabled   |
| **disabled_at**                              | timestamp null, indexed | disabled accounts cannot authenticate |

Staff are users with at least one role. Customers are users without roles (ADR-005).

### `roles`

| Column       | Type              | Notes                                         |
| ------------ | ----------------- | --------------------------------------------- |
| id           | bigint PK         |                                               |
| name         | string(64) unique | stable identifier (slug); `owner` is reserved |
| display_name | string(120)       |                                               |
| description  | string(500) null  |                                               |
| is_system    | bool              | system roles cannot be deleted or renamed     |

### `permissions`

| Column              | Type                | Notes                                                                         |
| ------------------- | ------------------- | ----------------------------------------------------------------------------- |
| name                | string(120) unique  | e.g. `products.view`. Must also exist in `PermissionRegistry` to be effective |
| display_name, group | string              | `group` indexed, used by the role editor                                      |
| source              | string(120) indexed | `core` or a module name, for cleanup on uninstall                             |

### `permission_role`, `role_user`

Composite primary keys. Both foreign keys cascade on delete. `role_user.created_at` records when the role was granted.

### `settings`

| Column | Type               | Notes                                                                           |
| ------ | ------------------ | ------------------------------------------------------------------------------- |
| key    | string(191) unique | namespaced: `store.*`, `system.*`, later `extension.<name>.*`, `theme.<name>.*` |
| value  | json null          |                                                                                 |

`system.installed_at` marks completed installation.

### `audit_logs` (append-only)

| Column                   | Type                    | Notes                                                  |
| ------------------------ | ----------------------- | ------------------------------------------------------ |
| actor_id                 | FK users, nullOnDelete  |                                                        |
| actor_label              | string null             | actor email snapshot, kept after the user is deleted   |
| action                   | string(120) indexed     | e.g. `auth.login`, `user.roles_changed`                |
| subject_type, subject_id | nullable morph, indexed |                                                        |
| ip_address               | string(45)              |                                                        |
| user_agent               | string(512)             |                                                        |
| metadata                 | json null               | redacted (ADR-010)                                     |
| created_at               | timestamp indexed       | no `updated_at`; the model refuses updates and deletes |

Retention: audit logs are kept indefinitely by default. A configurable pruning command will arrive in Phase 7.

## Phase 2 tables (implemented)

All money columns are `DECIMAL(19,4)` and read through the `decimal:4` cast, never as floats (ADR-017).

### Catalog

| Table                      | Key columns                                                                                                                         | Notes                                                         |
| -------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------- |
| `categories`               | `parent_id` (restrict), `slug` unique, `position`, `is_active`, SEO fields                                                          | adjacency list; a category with children cannot be deleted    |
| `products`                 | `type` (simple/variable), `slug` unique, `status` (draft/published/archived), `published_at`, SEO fields, soft deletes              | indexed on `status, published_at`                             |
| `product_variants`         | `product_id`, `sku` unique (64), `is_default`, `is_active`, `weight`, `barcode`, `track_inventory`, `allow_backorder`, soft deletes | every product has at least one variant; SKUs are never reused |
| `product_prices`           | `variant_id`, `currency`, `amount`, `compare_at_amount`                                                                             | unique `variant_id, currency`                                 |
| `product_attributes`       | `name`, `slug` unique, `position`                                                                                                   | option axes, e.g. Size                                        |
| `product_attribute_values` | `attribute_id`, `value`, `slug`                                                                                                     | unique `attribute_id, slug`                                   |
| `product_variant_options`  | `variant_id`, `attribute_value_id` (restrict)                                                                                       | composite PK                                                  |
| `product_categories`       | `product_id`, `category_id`                                                                                                         | composite PK                                                  |
| `product_images`           | `product_id`, `variant_id` null, `disk`, `path`, `alt`, `position`, `width`, `height`                                               | uploads are re-encoded server-side                            |

### Inventory

| Table                 | Key columns                                                                                        | Notes                                                                   |
| --------------------- | -------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------- |
| `inventory_locations` | `code` unique, `is_default`                                                                        | one `default` location is seeded by the migration                       |
| `inventory_stocks`    | `variant_id`, `location_id`, `on_hand`                                                             | unique `variant_id, location_id`; locked `FOR UPDATE` during allocation |
| `inventory_movements` | `quantity_change`, `quantity_after`, `reason`, `reference` morph, `actor_id`, `note`, `created_at` | append-only ledger; the model refuses updates and deletes               |

### Customers and carts

| Table                | Key columns                                                                         | Notes                                                                       |
| -------------------- | ----------------------------------------------------------------------------------- | --------------------------------------------------------------------------- |
| `customers`          | `user_id` unique null (nullOnDelete), `email`, names, `phone`, `accepts_marketing`  | guests have no `user_id`                                                    |
| `customer_addresses` | address fields, `country_code` (ISO 3166-1 alpha-2), default shipping/billing flags |                                                                             |
| `carts`              | `token` uuid unique, `user_id` null, `currency`, `coupon_code`                      | guest carts are identified by the session token                             |
| `cart_items`         | `cart_id`, `variant_id`, `quantity`, `seen_unit_price`                              | unique `cart_id, variant_id`; `seen_unit_price` drives price-change notices |

### Discounts, shipping, tax

| Table                                  | Key columns                                                                                                                                     | Notes                                 |
| -------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------- |
| `coupons`                              | `code` unique, `type` (percentage/fixed), `value`, `currency`, `min_subtotal`, `starts_at`, `ends_at`, usage limits, `times_used`, `applies_to` | soft deletes                          |
| `coupon_products`, `coupon_categories` | composite PKs                                                                                                                                   | eligibility scope                     |
| `coupon_redemptions`                   | `coupon_id` (restrict), `order_id`, `customer_id`, `email`, `amount`, `currency`                                                                | unique `coupon_id, order_id`          |
| `shipping_methods`                     | `code` unique, `rate`, `currency`, `free_over`, `countries` json, `is_active`, `position`                                                       |                                       |
| `tax_rates`                            | `country_code`, `region` null, `rate` (percent, `DECIMAL(8,4)`), `is_active`                                                                    | the most specific match (region) wins |

### Orders

| Table             | Key columns                                                                                                                                                                                                       | Notes                                                                       |
| ----------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------- |
| `orders`          | `number` unique (EM-000001), `customer_id`/`user_id` (nullOnDelete), `status`, `payment_status`, `fulfillment_status`, all totals, method code and name snapshots, `idempotency_key` unique, lifecycle timestamps | the three state fields change only through `OrderWorkflow`                  |
| `order_items`     | `product_id`/`variant_id` (nullOnDelete), `sku`, name snapshots, `unit_price`, `subtotal`, `discount_total`, `tax_rate`, `tax_total`, `total`, `restocked_quantity`                                               | snapshots survive product deletion                                          |
| `order_addresses` | `type` (shipping/billing) and an address snapshot                                                                                                                                                                 | unique `order_id, type`                                                     |
| `order_events`    | `type`, `message`, `is_customer_visible`, `actor_id`, `metadata`, `created_at`                                                                                                                                    | order history, notes and sent-email records                                 |
| `payments`        | `order_id` (restrict), `method_code`, `provider`, `provider_reference`, `amount`, `currency`, `status`, `recorded_by`, `paid_at`                                                                                  | unique `provider, provider_reference`; Phase 2 records manual payments only |
| `refunds`         | `order_id` (restrict), `payment_id`, `amount`, `currency`, `reason`, `status`, `provider_reference`, `actor_id`                                                                                                   |                                                                             |
| `refund_items`    | `refund_id`, `order_item_id` (restrict), `quantity`, `restocked`                                                                                                                                                  |                                                                             |
| `shipments`       | `order_id` (restrict), `carrier`, `tracking_number`, `tracking_url`, `shipped_at`, `delivered_at`                                                                                                                 |                                                                             |

Payments, refunds and shipments use `restrictOnDelete` on `order_id`, so an order with financial history cannot be hard-deleted.

## Phase 3 tables and columns (implemented)

| Table / column                 | Notes                                                                                                                                                                                         |
| ------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `payments.provider_reference`  | for gateways, the hosted session id (unique with `provider`)                                                                                                                                  |
| `payments.provider_payment_id` | the provider's charge/payment intent id, used to match refunds; indexed                                                                                                                       |
| `payments.checkout_url`        | the hosted page, reused while the session is valid                                                                                                                                            |
| `payments.expires_at`          | when the hosted session expires                                                                                                                                                               |
| `payments.failure_reason`      | provider failure or mismatch explanation                                                                                                                                                      |
| `payments.status`              | `pending`, `processing`, `paid`, `failed`, `expired`, `mismatch`; indexed with `created_at` for the reconcile job                                                                             |
| `refunds.status`               | `pending`, `completed`, `failed`; `refunds.failure_reason`; `provider_reference` indexed                                                                                                      |
| `payment_webhook_events`       | `provider`, `event_id` (unique together), `type`, `payload` json, `status` (received/processed/ignored/failed), `attempts`, `error`, `order_id` (nullOnDelete), `received_at`, `processed_at` |

Webhook payloads contain no card data (Stripe never sends full card numbers). They may contain customer emails and are kept for audit; retention and pruning arrive with Phase 7.

## Phase 4 tables (implemented)

### `extensions`

| Column                              | Type               | Notes                                                                     |
| ----------------------------------- | ------------------ | ------------------------------------------------------------------------- |
| name                                | string(100) unique | `vendor/package`                                                          |
| type                                | string(16)         | `module` (themes will reuse this table in Phase 5)                        |
| display_name, description           | string             | from the manifest                                                         |
| state                               | string(20) indexed | discovered, invalid, installed, enabled, disabled, update_pending, failed |
| version                             | string null        | installed version; null when not installed                                |
| available_version                   | string null        | version of the files on disk                                              |
| path                                | string             | relative to the modules directory                                         |
| manifest                            | json null          | last validated manifest                                                   |
| errors                              | json null          | validation errors                                                         |
| enabled_before_update               | bool               | restores the enabled state after an update or recovery                    |
| failure                             | text null          | why the module is failed                                                  |
| installed_at, enabled_at, failed_at | timestamp null     |                                                                           |

### `extension_events` (append-only)

`extension`, `action` (discovered, installed, enabled, configured, disabled, updated, uninstalled, quarantined, state_changed, files_missing), `from_state`, `to_state`, `version`, `success`, `message`, `actor_id` (nullOnDelete), `created_at`. The model refuses updates and deletes.

Module settings use the `settings` table under `extension.<vendor>.<package>.*`; module permissions use `permissions.source = <vendor>/<package>`. Bundled sample modules add `product_reviews` and `order_alert_deliveries` when installed.

## Phase 5 columns (implemented)

| Table / column                     | Notes                                                                                    |
| ---------------------------------- | ---------------------------------------------------------------------------------------- |
| `extensions.type = theme`          | Themes reuse the extensions table; the unique key is now `(type, name)`.                 |
| `extensions.applied_manifest`      | The theme version the merchant applied (rendered by the storefront); `applied_at`.       |
| `settings: theme.<v>.<n>.design`   | Merchant design overrides (flat map of design settings).                                 |
| `settings: theme.<v>.<n>.sections` | Merchant home sections (`{"home": [...]}`).                                              |
| `settings: theme.uploads`          | Image library of merchant uploads (`media:themes/<random>.<ext>`), shared by all themes. |

Theme lifecycle events go to `extension_events` with `extension = "theme:<vendor>/<name>"`.

## Phase 6 tables (implemented)

Server role (used only with `ECOMODULAR_MARKETPLACE_SERVER=true`):

| Table                     | Notes                                                                                                                                                                                             |
| ------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `marketplace_publishers`  | Seeded with the platform owner (`is_platform`); room for vendors later.                                                                                                                           |
| `marketplace_products`    | Unique `slug` and `(type, extension_name)`; price in minor units, currency, status, `updates_months`, `activation_limit`; soft deletes.                                                           |
| `marketplace_releases`    | Unique `(product_id, version)`; status `pending_review`, `published`, `rejected` or `withdrawn`; private `archive_path`, `sha256`, size, manifest, `signed_metadata`, `signature`, review fields. |
| `marketplace_orders`      | Separate from merchant `orders`: number, buyer, status, total, Stripe session and payment intent.                                                                                                 |
| `marketplace_order_items` | Product snapshot per order.                                                                                                                                                                       |
| `licenses`                | `key_hash` (unique, SHA-256), `key_encrypted`, `key_hint`, status, `activation_limit`, `updates_until`, `expires_at`, `status_reason`.                                                            |
| `license_activations`     | Per `instance_id`, with domain, platform version, `last_seen_at`, `deactivated_at`.                                                                                                               |
| `license_events`          | Append-only license history.                                                                                                                                                                      |
| `download_tokens`         | `token_hash` (unique), license, release, instance, `expires_at`, `used_at` (single use).                                                                                                          |

Client role (every store):

| Table                        | Notes                                                                                                                                                                     |
| ---------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `marketplace_licenses`       | One per product (`product_slug` unique): encrypted key, status, `updates_until`, signed token, `token_valid_until`, `available_release`, `last_checked_at`, `last_error`. |
| `marketplace_update_history` | Append-only install, update and rollback log: versions, success, message, `backup_path`, `sha256`, actor.                                                                 |

The store's instance id is the setting `marketplace.instance_id`.

## Phase 8 tables (implemented)

Marketplace server:

| Table / column                         | Notes                                                                                                                                               |
| -------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| `marketplace_releases.scan`            | Static scan summary and findings shown to the reviewer.                                                                                             |
| `marketplace_advisories`               | Security advisories: severity, title, body, `affected_versions` (Composer constraint), `fixed_version`, `published_at`. Signed into license tokens. |
| `marketplace_publishers` (new columns) | `status`, `commission_percent`, `legal_name`, `country`, `website`, `payout_email`, `support_email`, `verified_at`.                                 |
| `marketplace_products` (new columns)   | `support_email`, `support_url`.                                                                                                                     |
| `marketplace_publisher_members`        | Account ↔ publisher (`user_id` unique), role.                                                                                                       |
| `marketplace_seller_applications`      | Applications with vendor slug, legal and payout details, review status and notes.                                                                   |
| `marketplace_payouts`                  | Payouts recorded by the owner: amount, currency, reference.                                                                                         |
| `marketplace_ledger_entries`           | Append-only: sale, refund and payout entries (gross, commission, net, signed). Unique `(order_item_id, type)`.                                      |
| `marketplace_reviews`                  | Reviews by license holders: rating, title, body, moderation status. Unique `(product_id, user_id)`.                                                 |

Module `ecomodular/newsletter`: `newsletter_subscribers` (email, consent text and time, source, unsubscribe token, `unsubscribed_at`).

## Media and translations (implemented)

| Table                   | Notes                                                                                                                                                                                                           |
| ----------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `media`                 | Every stored image: `disk`, `path` (unique together), `collection` (library, products or themes), file name, MIME type, size, dimensions, alt text, uploader. Backfilled from product images and theme uploads. |
| `product_translations`  | Per product and locale: name, short description, description, SEO title and description. Unique `(product_id, locale)`.                                                                                         |
| `category_translations` | Per category and locale: name, description, SEO title and description. Unique `(category_id, locale)`.                                                                                                          |

Settings: `store.default_locale` and `store.locales` (additional storefront languages).

`interface_translations`: the merchant's own storefront wording per locale (`locale`, `key` = English text, `value`), overriding `lang/storefront/{locale}.json`.

## Order documents (implemented)

| Table               | Notes                                                                                                                                                                                                           |
| ------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `invoices`          | One per order (unique `order_id`). `number` (unique) and `sequence`, `issued_at`, `currency`, and JSON snapshots `seller`, `buyer`, `lines`, `totals`; `notes` (footer); `issued_by`. Never updated or deleted. |
| `document_counters` | `name` (primary, `invoice`) and `next`. Locked and advanced in the same transaction as the invoice, so numbers have no gaps.                                                                                    |

`orders.hold_previous_status`: the status an `on_hold` order returns to when released. `orders.status` gains `on_hold` and `fulfillment_status` gains `packed`.

Settings:

- `documents.invoice_prefix`
- `documents.invoice_padding`
- `documents.tax_id`
- `documents.seller_address`
- `documents.footer`
- `documents.logo_media_id`

## Detailed order statuses (implemented)

- `orders.status`:
    - `pending_payment`
    - `pending` (awaiting review)
    - `confirmed`
    - `on_hold`
    - `processing`
    - `completed`
    - `cancelled`
- `orders.payment_status` adds `failed`.
- `orders.fulfillment_status`:
    - `unfulfilled`
    - `picking`
    - `packing`
    - `packed`
    - `ready_for_dispatch`
    - `shipped`
    - `in_transit`
    - `out_for_delivery`
    - `delivered`
    - `delivery_failed`
    - `returned`
- `orders.display_status` (indexed): the headline status derived from the three tracks (ADR-044) and kept current on every save.
- Settings:
    - `orders.review_before_confirm`
    - `orders.notify.{confirmed,payment_failed,shipped,out_for_delivery,delivered,delivery_failed,returned}`

## Payments and bulk orders (implemented)

`orders.payment_status` adds `partially_paid`, and `orders.display_status` adds `partially_paid`.

Manual payments use `payments.method_code`:

- `cash`
- `bank_transfer`
- `card_terminal`
- `other`

They have `provider = manual`, and `paid_at` is the date received.

## Planned tables

The tables below follow §13 of the master specification and are designed when each phase starts:

- **Multi-store (ADR-039):** `stores`, `theme_assignments`, `store_id` on channel-specific tables
