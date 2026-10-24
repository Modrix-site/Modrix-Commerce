<?php

return [
    // Catalog prices are shown in this currency unless changed in admin settings.
    'currency' => env('MARKETPLACE_CURRENCY', 'USD'),

    // Private files: package ZIPs, release archives, uploads. Never public.
    'storage' => env('MARKETPLACE_STORAGE', storage_path('app/private')),

    // Written when setup finishes (web installer or marketplace:install); /install is closed while it exists.
    'installed_file' => storage_path('app/installed'),

    // Source tree (modules/ and themes/) that `marketplace:publish` packages from.
    'source_root' => env('CATALOG_ROOT') ?: dirname(base_path()),

    // Signed license tokens stay valid this long; stores refresh them on checks.
    'token_days' => 30,

    // One-time download links expire after this many minutes.
    'download_minutes' => 10,
];
