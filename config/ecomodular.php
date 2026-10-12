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

];
