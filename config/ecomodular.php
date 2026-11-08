<?php

return [

    /*
    | Filesystem disk for product media. Use "public" for local storage
    | (requires `php artisan storage:link`) or an S3-compatible disk.
    */
    'media_disk' => env('ECOMODULAR_MEDIA_DISK', 'public'),

    /*
    | Prefix for human-readable order numbers, e.g. EM-000123.
    */
    'order_number_prefix' => env('ECOMODULAR_ORDER_PREFIX', 'EM-'),

    /*
    | Guest carts older than this many days are pruned by the scheduler.
    */
    'cart_lifetime_days' => (int) env('ECOMODULAR_CART_LIFETIME_DAYS', 30),

    /*
    | Maximum rows accepted by a single product CSV import.
    */
    'csv_import_max_rows' => (int) env('ECOMODULAR_CSV_IMPORT_MAX_ROWS', 5000),

    /*
    | Modules (docs/extensions/module-development.md). Packages live in
    | `path/<vendor>/<name>/module.json`, outside the public web root.
    | Safe mode boots without loading any module: use it to recover when a
    | module breaks every request (docs/extensions/recovery.md).
    */
    'modules' => [
        'path' => env('ECOMODULAR_MODULES_PATH', base_path('modules')),
        'safe_mode' => (bool) env('ECOMODULAR_SAFE_MODE', false),
    ],

    /*
    | Themes (docs/extensions/theme-development.md): data-only packages in
    | `path/<vendor>/<name>/theme.json`. The default theme is used until the
    | merchant activates one, and whenever the active theme is unusable.
    */
    /*
    | Official marketplace (docs/marketplace/licensing.md, ADR-033).
    |
    | Client (every merchant store): where the marketplace lives and which
    | publisher keys are trusted to sign releases (comma-separated
    | base64 Ed25519 public keys). Packages signed by any other key are refused.
    |
    | Server (only the platform owner's marketplace instance): the signing
    | key (base64 Ed25519 secret key, keep it out of version control),
    | marketplace Stripe credentials (separate from any store's own) and
    | licensing timings.
    */
    'installer' => [
        // false: install only with `php artisan ecomodular:install` (the /install pages return 404).
        'web' => (bool) env('ECOMODULAR_WEB_INSTALLER', true),
        // Hours a web installer setup token stays valid (0: no expiry).
        'token_hours' => (int) env('ECOMODULAR_INSTALLER_TOKEN_HOURS', 24),
    ],

    'health' => [
        // Bearer token that unlocks per-check details on /health (empty: overall status only).
        'token' => env('ECOMODULAR_HEALTH_TOKEN', ''),
        'disk_warn_percent' => (float) env('ECOMODULAR_HEALTH_DISK_WARN_PERCENT', 10),
        'disk_fail_percent' => (float) env('ECOMODULAR_HEALTH_DISK_FAIL_PERCENT', 5),
        'backup_max_age_hours' => (int) env('ECOMODULAR_HEALTH_BACKUP_MAX_AGE_HOURS', 26),
    ],

    'backup' => [
        'path' => env('ECOMODULAR_BACKUP_PATH', storage_path('app/private/backups')),
        // Root of storage/app, modules/ and themes/ (tests point this at a scratch tree).
        'files_root' => env('ECOMODULAR_BACKUP_FILES_ROOT', base_path()),
        // AES-256 encrypts every archive entry when set. Store it outside this server too.
        'password' => env('ECOMODULAR_BACKUP_PASSWORD', ''),
        'keep' => (int) env('ECOMODULAR_BACKUP_KEEP', 7),
        // Daily time (server timezone) for the scheduled backup; empty disables it.
        'schedule' => env('ECOMODULAR_BACKUP_SCHEDULE', '02:30'),
        'mysqldump' => env('ECOMODULAR_MYSQLDUMP', 'mysqldump'),
        'mysql' => env('ECOMODULAR_MYSQL', 'mysql'),
        'pg_dump' => env('ECOMODULAR_PG_DUMP', 'pg_dump'),
        'psql' => env('ECOMODULAR_PSQL', 'psql'),
        'timeout' => (int) env('ECOMODULAR_BACKUP_TIMEOUT', 3600),
    ],

    'security' => [
        // Content-Security-Policy: enforce, report-only or off.
        'csp' => env('ECOMODULAR_CSP', 'enforce'),
        // Strict-Transport-Security on HTTPS requests.
        'hsts' => (bool) env('ECOMODULAR_HSTS', true),
        // Extra image origins (comma-separated), e.g. a CDN not configured as the media disk URL.
        'img_sources' => array_values(array_filter(array_map('trim', explode(',', (string) env('ECOMODULAR_CSP_IMG_SOURCES', ''))))),
    ],

    'marketplace' => [
        'url' => rtrim((string) env('ECOMODULAR_MARKETPLACE_URL', 'https://marketplace.ecomodular.dev'), '/'),
        'trusted_keys' => array_values(array_filter(array_map('trim', explode(',', (string) env('ECOMODULAR_MARKETPLACE_TRUSTED_KEYS', ''))))),
        'grace_days' => (int) env('ECOMODULAR_MARKETPLACE_GRACE_DAYS', 14),
        'timeout' => (int) env('ECOMODULAR_MARKETPLACE_TIMEOUT', 10),

        'server' => [
            'enabled' => (bool) env('ECOMODULAR_MARKETPLACE_SERVER', false),
            'signing_key' => env('ECOMODULAR_MARKETPLACE_SIGNING_KEY'),
            'token_days' => (int) env('ECOMODULAR_MARKETPLACE_TOKEN_DAYS', 30),
            'download_minutes' => (int) env('ECOMODULAR_MARKETPLACE_DOWNLOAD_MINUTES', 10),
            'max_package_mb' => (int) env('ECOMODULAR_MARKETPLACE_MAX_PACKAGE_MB', 50),
            'stripe_secret' => env('MARKETPLACE_STRIPE_SECRET'),
            'stripe_webhook_secret' => env('MARKETPLACE_STRIPE_WEBHOOK_SECRET'),
        ],
    ],

    // Core platform updates: `paths` are the only paths an update replaces.
    'updates' => [
        'root' => env('ECOMODULAR_UPDATES_ROOT', base_path()),
        // PHP CLI for the install process; empty = this PHP, or the CLI found next to PHP-FPM.
        'php' => env('ECOMODULAR_UPDATES_PHP'),
        'paths' => [
            'app', 'bootstrap/app.php', 'bootstrap/providers.php', 'config',
            // Never `database` as a whole: it holds the live SQLite file.
            'database/migrations', 'database/seeders', 'database/factories', 'lang',
            'public/build', 'public/index.php', 'resources', 'routes', 'vendor', 'artisan',
            'composer.json', 'composer.lock', 'package.json',
        ],
        'max_mb' => 300,
    ],

    'themes' => [
        'path' => env('ECOMODULAR_THEMES_PATH', base_path('themes')),
        'default' => env('ECOMODULAR_DEFAULT_THEME', 'ecomodular/essence'),
    ],

];
