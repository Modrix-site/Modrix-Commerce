# Module Development

> Status: **design contract (Phase 4 not implemented yet).** The extension points that already exist are listed under "Available now". Everything else describes the planned, stable contract and may still be refined before Phase 4 ships.

## Available now (Phase 1)

| Extension point  | API                                                                                             | Notes                                                                                                                                      |
| ---------------- | ----------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| Permissions      | `App\Core\Authorization\PermissionRegistry::register($name, $label, $group, $source)`           | `$source` must be the module name. Core permissions cannot be overridden. Run `php artisan ecomodular:sync-permissions` after registering. |
| Admin navigation | `App\Core\Admin\AdminNavigation::add($key, $title, $route, $icon, $group, $permission, $order)` | Items appear only for users holding `$permission` and only if the route exists. The route itself must still enforce the permission.        |
| Settings         | `App\Core\Settings\SettingsRepository`                                                          | Use the `extension.<vendor>.<name>.*` key namespace.                                                                                       |
| Audit            | `App\Core\Audit\AuditLogger::record($action, $subject, $metadata)`                              | Prefix actions with the module name.                                                                                                       |

## Planned manifest (`module.json`)

```json
{
    "name": "acme/product-reviews",
    "display_name": "Product Reviews",
    "version": "1.2.0",
    "type": "module",
    "platform_version": "^1.0",
    "provider": "Acme\\ProductReviews\\ReviewsServiceProvider",
    "requires": { "acme/notifications": "^2.0" },
    "permissions": [
        {
            "name": "reviews.moderate",
            "label": "Moderate reviews",
            "group": "Reviews"
        }
    ],
    "capabilities": [
        "admin.navigation",
        "storefront.components",
        "events.listen"
    ],
    "migrations": "database/migrations",
    "routes": { "admin": "routes/admin.php" },
    "assets": { "admin": "dist/admin.js" },
    "settings_schema": "settings.schema.json"
}
```

The manifest is **data**: it is JSON-schema validated and never executed. Packages are rejected for invalid manifests, unknown capabilities, name conflicts, incompatible `platform_version`, dependency cycles, or paths that escape the package directory.

## Planned lifecycle

`discovered → validated → installed → enabled ⇄ disabled → update pending → failed`

- Installing runs migrations inside a recorded operation. Enabling registers the service provider. Neither happens automatically after an upload.
- Disabling keeps the module's data. A module cannot be disabled while an enabled module depends on it.
- Uninstalling warns about data loss and requires explicit confirmation.
- Every lifecycle step is written to `extension_events` and the audit log.
- Recovery: if enabling fails, the module is marked `failed` and its provider is not loaded on the next boot. `php artisan ecomodular:extensions:disable <name>` works even when the admin UI is unavailable.

## Security model

PHP modules run with the **same privileges as the application**. A license key or signature proves origin, not safety. Only install modules you trust. Public third-party submissions will not be accepted until review, signing and isolation controls exist (Phase 8).
