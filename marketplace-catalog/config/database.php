<?php

$path = (string) env('DB_DATABASE', 'storage/catalog.sqlite');

if (! preg_match('/^(?:[A-Za-z]:[\\\\\\/]|\/)/', $path)) {
    $path = base_path($path);
}

return [
    'default' => 'sqlite',
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => $path,
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
        ],
    ],
];
