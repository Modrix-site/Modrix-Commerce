# Module Development

Modules add features to EcoModular without editing core files. A module is a directory with a data-only manifest (`module.json`), PHP code under its own namespace, and optionally migrations, routes, settings, permissions and React components. The three bundled modules in `modules/ecomodular/` are working examples:

| Module                       | Shows how to                                                                                                                               |
| ---------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| `ecomodular/product-reviews` | migrations (and a schema change in an update), settings, a permission, admin pages and navigation, storefront routes and a storefront slot |
| `ecomodular/sales-widget`    | a dashboard widget built on core reports, a setting                                                                                        |
| `ecomodular/order-alerts`    | a queued, idempotent event listener, a module table, settings                                                                              |

## Security model: read this first

**A module's PHP code runs with the same privileges as the store.** It can read and change every table, file and secret the application can. Manifests, capabilities and the guarded loader exist to prevent mistakes and to make failures recoverable. They are not a sandbox.

- Only install modules whose authors you trust. A license key or signature proves where a package came from, not that it is safe.
- Enabling a module requires an administrator to confirm that they trust its code, and a recent password confirmation.
- Nothing is installed or enabled automatically, not even after files are copied into `modules/`.
- Public third-party submissions will not be accepted until review, signing and isolation controls exist (Phase 8).

## Package layout

```text
modules/<vendor>/<name>/
  module.json                    manifest (required)
  src/                           PHP, PSR-4 autoloaded from the manifest
  database/migrations/           migrations (optional)
  routes/admin.php               admin routes (optional)
  routes/storefront.php          storefront routes (optional)
  resources/js/pages/            Inertia pages (optional)
  resources/js/slots/            storefront slot and dashboard widget components (optional)
```

The directory must be exactly two levels deep, and its path must equal the manifest `name`. Packages live outside the public web root.

## Manifest reference

The authoritative schema is [`module.schema.json`](module.schema.json). Add `"$schema": "../../../docs/extensions/module.schema.json"` to get editor validation. The platform validates manifests with its own validator and rejects any package that fails, showing the errors in **Admin → Extensions**.

| Key                 | Required  | Notes                                                                                                                                                                                                                   |
| ------------------- | --------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `name`              | yes       | `vendor/package`: lowercase letters, digits and dashes. Must match the directory.                                                                                                                                       |
| `display_name`      | yes       | Up to 120 characters.                                                                                                                                                                                                   |
| `description`       |           | Up to 500 characters.                                                                                                                                                                                                   |
| `version`           | yes       | Semantic version (`1.2.0`). Downgrades are refused.                                                                                                                                                                     |
| `type`              | yes       | `"module"`.                                                                                                                                                                                                             |
| `platform_version`  | yes       | Composer-style constraint against the platform version (currently `1.0.0-rc.1`), e.g. `"^1.0"`.                                                                                                                         |
| `author`, `license` |           | Shown in the admin.                                                                                                                                                                                                     |
| `autoload`          | with code | Exactly one `{"psr-4": {"Vendor\\Package\\": "src/"}}`. At least two namespace segments. `App\`, `Illuminate\`, `Database\` and other framework namespaces are reserved. Namespaces must not overlap between modules.   |
| `provider`          |           | A `ServiceProvider` class inside the autoload namespace.                                                                                                                                                                |
| `requires`          |           | `{"vendor/other": "^1.0"}`. Dependencies must be installed before this module is installed, and enabled before it is enabled. Cycles are rejected.                                                                      |
| `permissions`       |           | `[{"name": "reviews.moderate", "label": "...", "group": "..."}]`. Names must not collide with core or other modules. Requires the `permissions` capability.                                                             |
| `capabilities`      |           | From the list below. Declarative sections require their capability.                                                                                                                                                     |
| `migrations`        |           | Directory of migration files named like `2026_01_31_000000_create_example_table.php`. Names must be unique across the platform. Requires `database.migrations`.                                                         |
| `routes`            |           | `{"admin": "routes/admin.php", "storefront": "routes/storefront.php"}`. Requires `admin.routes` / `storefront.routes`.                                                                                                  |
| `assets`            |           | `{"pages": "resources/js/pages", "slots": "resources/js/slots"}`.                                                                                                                                                       |
| `settings`          |           | Settings schema (below). Requires `settings`.                                                                                                                                                                           |
| `admin_navigation`  |           | `[{"key", "title", "route", "icon", "group", "permission", "order"}]`. `route` is one of the module's own admin route names without the prefix. `permission` is required. Requires `admin.navigation` and admin routes. |

Every path must be relative and stay inside the package. Absolute paths, `..` segments and symlinks pointing outside the package are rejected. Unknown keys are rejected.

### Capabilities

| Capability            | Meaning                               |
| --------------------- | ------------------------------------- |
| `admin.routes`        | Adds admin pages                      |
| `admin.navigation`    | Adds admin menu items                 |
| `storefront.routes`   | Adds storefront pages or endpoints    |
| `storefront.slots`    | Shows content inside storefront pages |
| `dashboard.widgets`   | Adds dashboard widgets                |
| `database.migrations` | Creates database tables               |
| `settings`            | Has merchant settings                 |
| `permissions`         | Defines staff permissions             |
| `events.listen`       | Reacts to store events                |
| `scheduled.tasks`     | Runs scheduled tasks                  |
| `console.commands`    | Adds console commands                 |
| `notifications`       | Sends notifications                   |
| `checkout.validators` | Can block checkout                    |
| `pricing.steps`       | Changes cart pricing                  |
| `catalog.sale_prices` | Sets temporary sale prices            |
| `catalog.image_urls`  | Replaces product image files          |
| `payment.gateway`     | Adds a payment method                 |
| `shipping.rates`      | Adds shipping rates                   |
| `tax.provider`        | Replaces tax calculation              |
| `search.provider`     | Replaces storefront search            |

Administrators see these before enabling a module. Declare every capability your code uses: the PHP-level ones are honesty requirements, since PHP code cannot be restricted (see the security model).

### Settings schema

```json
"settings": [
    { "key": "auto_approve", "type": "boolean", "label": "Publish without moderation", "default": false },
    { "key": "min_length", "type": "integer", "label": "Minimum length", "default": 10, "min": 0, "max": 500 },
    { "key": "mode", "type": "select", "label": "Mode", "options": [{ "value": "a", "label": "A" }] },
    { "key": "recipient", "type": "email", "label": "Send to", "help": "Leave empty to pause." },
    { "key": "a_model", "type": "string", "label": "Model", "suggestions": ["model-1", "model-2"], "visible_when": { "mode": "a" } }
]
```

- **Types:** `boolean`, `string` (`max_length` ≤ 500), `text` (`max_length` ≤ 10000), `integer` (`min`, `max`), `select` (`options`), `email`. Optional keys: `help`, `default`, `required`.
- **Suggestions:** a `string` setting may list up to 50 `suggestions`. The form shows them as a dropdown with a **Custom…** choice for any other value. Suggestions are not an allowlist.
- **Conditional fields:** `visible_when` maps other setting keys to a value or list of values, e.g. `{ "provider": ["openai", "azure"] }`. The field is shown only when every condition holds. A hidden field is not required, and leaving it empty keeps its stored value.
- **Where values go:** they are validated by the platform from the type and stored under `extension.<vendor>.<package>.<key>`.
- **Admin form:** the form in **Admin → Extensions → (module)** is generated from the schema.
- **No code:** values are data only. Never evaluate them as code, templates, SQL or validation rules.
- **Reading values:** `app(\App\Core\Extensions\ModuleSettingsSchema::class)->of('vendor/package', 'key', $default)`.

## Lifecycle

```text
discovered ── install ──▶ installed ── enable ──▶ enabled ◀─ disable / enable ─▶ disabled
     │                        │                       │
  invalid               uninstall ◀───────────────────┘ (after disable)
                                            newer files on disk ─▶ update_pending ── update ──▶ previous state
                            any failure ─▶ failed (not loaded; see recovery)
```

| Operation | What happens                                                                                                                                                                                                                                                                                           | Admin permission       |
| --------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ---------------------- |
| Install   | Checks dependencies (installed, versions satisfied), runs the module's migrations, registers its permissions and grants them to the Administrator role. A failed migration is rolled back and the module is marked `failed`.                                                                           | `extensions.install`   |
| Enable    | Requires trust confirmation. Dependencies must be enabled. The provider is started as a trial; if it throws, the module is not enabled.                                                                                                                                                                | `extensions.enable`    |
| Configure | Validates and stores settings.                                                                                                                                                                                                                                                                         | `extensions.configure` |
| Disable   | Stops loading the module. **All data, settings and role assignments are kept.** Refused while an enabled module depends on it.                                                                                                                                                                         | `extensions.disable`   |
| Update    | When the files on disk carry a newer version, the module becomes `update_pending` and **is not loaded until updated**, so new code never runs against the old schema. Update runs the new migrations, syncs permissions (removing dropped ones), then restores the previous enabled or disabled state. | `extensions.update`    |
| Uninstall | Requires typing the module name. Rolls back the module's migrations (**its tables and data are deleted**), removes its settings and permissions. Refused while another installed module depends on it, and while it is enabled.                                                                        | `extensions.install`   |

Install, enable, update and uninstall also require a recent password confirmation. Every operation is serialised by a lock, written to the module's lifecycle log (`extension_events`) and to the audit log, including refusals and failures. Route and event caches are cleared afterwards; re-run `php artisan optimize` in production.

The same operations are available from the command line, and work in safe mode:

```bash
php artisan ecomodular:modules list
```

```bash
php artisan ecomodular:modules install vendor/package
```

```bash
php artisan ecomodular:modules enable vendor/package --trust
```

```bash
php artisan ecomodular:modules uninstall vendor/package --confirm=vendor/package
```

## How a module is loaded

- **Autoloading:** enabled modules get PSR-4 autoloading from a dedicated class loader.
- **Declarations:** permissions and admin navigation are registered from the manifest.
- **Provider:** the provider is registered and booted through a guard. If `register()` or `boot()` throws, the module is quarantined (`failed`, logged, audited) and the rest of the store keeps working. Module providers register after the platform's providers and before any provider boots.
- **Routes:** loaded by the platform, not by the module:

| File                | URL prefix                    | Route name prefix           | Middleware                                                           |
| ------------------- | ----------------------------- | --------------------------- | -------------------------------------------------------------------- |
| `routes.admin`      | `/admin/m/<vendor>/<package>` | `admin.<vendor>.<package>.` | `web`, `installed`, `auth`, `verified`, `staff`, `module.authorized` |
| `routes.storefront` | `/m/<vendor>/<package>`       | `<vendor>.<package>.`       | `web`, `installed`                                                   |

`module.authorized` refuses any module admin route that does not declare a `can:` permission middleware, so every admin page must name the permission it requires. Storefront routes must add their own `auth`, throttling and authorization.

## Pages and components

Module React code is compiled with the application, so **adding or updating a module with UI requires an asset rebuild** (`npm run build`).

- **Pages:** `Inertia::render('module/<vendor>/<package>/<page>')` resolves to `modules/<vendor>/<package>/resources/js/pages/<page>.tsx`. Pages under `storefront/` use the storefront layout; others use the admin layout.
- **Slot and widget components:** `resources/js/slots/<Component>.tsx`, default export, receiving the props built on the server. They render inside an error boundary, so a crashing component disappears instead of breaking the page.
- **Imports:** core UI components can be imported with `@/components/...`.
- **No generated route helpers:** do not import `@/routes` or `@/actions`. They are generated from the routes present at build time and would break the build when the module is disabled. Pass URLs from the server in props, as the reviews module does.

## Extension points

Typed contracts for critical operations; events for reactions. **Stable** means covered by the compatibility policy below.

| Extension point                     | API                                                                                                                                                                                                                                                 | Status            |
| ----------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------- |
| Payment gateway                     | implement `App\Domains\Payments\Contracts\PaymentGateway`, `PaymentMethods::register()`                                                                                                                                                             | Stable            |
| Manual payment method               | implement `App\Domains\Payments\Contracts\PaymentMethod`, `PaymentMethods::register()`                                                                                                                                                              | Stable            |
| Shipping rates                      | implement `App\Domains\Shipping\Contracts\ShippingRateProvider`, `ShippingRates::register()`                                                                                                                                                        | Stable            |
| Tax                                 | bind `App\Domains\Tax\Contracts\TaxRateProvider`                                                                                                                                                                                                    | Stable            |
| Pricing                             | implement `App\Domains\Checkout\Pricing\PricingStep`, `CartPricer::addStep()`                                                                                                                                                                       | Stable            |
| Sale prices                         | implement `App\Domains\Catalog\Pricing\SalePriceProvider`, `SalePrices::addProvider()`; lowest offer wins, cart charges it                                                                                                                          | Stable            |
| Product image URLs                  | `App\Core\Media\ProductImageUrls::register($module, fn (ProductImage $i): ?string => ...)`; first non-null URL replaces the image on the storefront, `forget($module)` removes it                                                                                                                           | Stable            |
| Checkout validation                 | implement `App\Domains\Checkout\Contracts\CheckoutValidator`, `CheckoutValidators::register()`. Fails closed.                                                                                                                                       | Stable            |
| Search                              | bind `App\Domains\Catalog\Contracts\SearchProvider`; may only narrow the visible-product query                                                                                                                                                      | Stable            |
| Storefront slots                    | `App\Core\Extensions\StorefrontSlots::register()`: `home.after_featured`, `catalog.after_products`, `product.after_details`, `cart.after_totals`, `checkout.payment_method`, `checkout.after_summary`, `order.confirmation`, `storefront.footer`, `storefront.overlay` | Stable            |
| Admin product panel                 | `StorefrontSlots::register('admin.product.sidebar', ...)`: a panel in the product editor sidebar (see flash-sales)                                                                                                                                  | Stable            |
| Dashboard widgets                   | `App\Core\Extensions\DashboardWidgets::register()`                                                                                                                                                                                                  | Stable            |
| Personal data export                | `App\Domains\Customers\PersonalDataExport::contribute('<key>', fn (User $user) => [...])`: add the data your module stores about a customer to their export (see the newsletter module)                                                             | Stable            |
| Admin navigation                    | manifest `admin_navigation` (or `AdminNavigation::add()`)                                                                                                                                                                                           | Stable            |
| Permissions                         | manifest `permissions`                                                                                                                                                                                                                              | Stable            |
| Settings                            | manifest `settings`, `ModuleSettingsSchema::of()`                                                                                                                                                                                                   | Stable            |
| Events                              | `ProductCreated`, `OrderPlaced`, `OrderPaid`, `OrderShipped`, `OrderDelivered`, `OrderCancelled`, `OrderRefunded`, `InventoryLow`, `CustomerRegistered`. Dispatched after commit; may be delivered more than once, so listeners must be idempotent. | Stable            |
| Scheduled tasks                     | `$this->callAfterResolving(Schedule::class, fn ($s) => $s->command(...))` in the provider                                                                                                                                                           | Laravel           |
| Console commands                    | `$this->commands([...])` in the provider                                                                                                                                                                                                            | Laravel           |
| Notification channels               | Laravel notifications                                                                                                                                                                                                                               | Laravel           |
| Product types / attribute providers | not yet available                                                                                                                                                                                                                                   | Planned (Phase 8) |

Domain models (`Product`, `Order`, …) may be read by modules. Change commerce state only through the documented services (`OrderWorkflow`, `InventoryService`, `OrderRefunds`): writing order, payment or stock columns directly bypasses locking, history and audit.

## Compatibility policy

- Stable extension points follow semantic versioning of the platform version. Within a major version (`1.x`), they are only extended: new optional parameters, new methods with default implementations, and new events.
- A breaking change is announced in the changelog at least one minor version before removal, with the old API marked `@deprecated`.
- From 1.0 the extension API follows Semantic Versioning: extension points are only added within a major version. Declare `"^1.0"` (release candidates such as `1.0.0-rc.1` satisfy it). Packages written for `0.x` must add `^1.0` after testing, because `^0.4` alone is refused by 1.x.
- Internal classes not listed above may change at any time.

## Writing migrations

- Prefix table names with your package (`product_reviews`, `order_alert_deliveries`) and name migration files uniquely.
- Every migration needs a working `down()`: uninstall runs them.
- Never alter core tables. Add your own table with a foreign key instead.
- MySQL cannot roll back schema changes inside a transaction. Keep each migration small, so a failed install or update leaves at most one step to repair (see [recovery](recovery.md)).

## Testing a module

The platform's own tests show the patterns:

- `tests/Support/ModuleFixtures.php` writes packages into a temporary modules directory.
- `tests/Feature/Extensions/SampleModulesTest.php` drives the bundled modules through their lifecycle.
- `tests/Feature/Extensions/ModuleBootTest.php` boots separate processes, which is the only way to catch problems that appear on a fresh boot.
