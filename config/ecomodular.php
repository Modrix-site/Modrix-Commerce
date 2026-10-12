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

    'themes' => [
        'path' => env('ECOMODULAR_THEMES_PATH', base_path('themes')),
        'default' => env('ECOMODULAR_DEFAULT_THEME', 'ecomodular/essence'),
    ],

];
