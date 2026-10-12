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

## Planned tables

The tables below follow §13 of the master specification and are designed when each phase starts:

- **Phase 2:** `customers`, `customer_addresses`, `products`, `product_translations`, `product_variants`, `product_attributes`, `product_attribute_values`, `categories` (nested set or adjacency + path), `product_categories`, `product_images`, `product_prices`, `carts`, `cart_items`, `orders`, `order_items`, `order_addresses`, `shipments`, `inventory_locations`, `inventory_stocks`, `inventory_movements`, `coupons`, `coupon_redemptions`, `refunds`
- **Phase 3:** `payments`, `payment_webhook_events` (unique `provider` + `event_id`)
- **Phase 4:** `extensions`, `extension_versions`, `extension_installations`, `extension_dependencies`, `extension_settings`, `extension_permissions`, `extension_events`
- **Phase 5:** `themes`, `theme_settings`, `theme_assignments`
- **Phase 6:** `marketplace_products`, `marketplace_releases`, `marketplace_orders`, `marketplace_order_items`, `licenses`, `license_activations`, `license_events`, `download_tokens`
- **Post-launch:** `vendors`, `vendor_payouts`, `marketplace_commissions`, `vendor_verifications`
