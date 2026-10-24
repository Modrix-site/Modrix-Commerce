<?php

/*
| The marketplace database: SQLite for small installs and tests, MySQL or
| MariaDB on ordinary hosting. It is independent of every merchant store.
| (The legacy catalog.sqlite under CATALOG_STORAGE is only read by
| `php artisan marketplace:import-legacy`.)
*/

$sqlite = (string) env('DB_DATABASE', 'database/marketplace.sqlite');

if ($sqlite !== ':memory:' && ! preg_match('/^(?:[A-Za-z]:[\\\\\\/]|\/)/', $sqlite)) {
    $sqlite = base_path($sqlite);
}

$mysql = fn (string $driver) => [
    'driver' => $driver,
    'url' => env('DB_URL'),
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'marketplace'),
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', ''),
    'unix_socket' => env('DB_SOCKET', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => env('DB_PREFIX', ''),
    'prefix_indexes' => true,
    'strict' => true,
    'engine' => 'InnoDB',
];

return [
    'default' => env('DB_CONNECTION', 'sqlite'),

    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => $sqlite,
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 5000,
            'journal_mode' => null,
            'synchronous' => null,
        ],
        'mysql' => $mysql('mysql'),
        'mariadb' => $mysql('mariadb'),
    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],
];
