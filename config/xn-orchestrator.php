<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Catalog Path
    |--------------------------------------------------------------------------
    |
    | Directory where the package catalog YAML files are stored. When null,
    | the package's own resources/catalog directory is used. Publish the
    | catalog with `php artisan vendor:publish --tag=package-catalog` to
    | override the entries from your own application.
    |
    */

    'catalog_path' => null,

    /*
    |--------------------------------------------------------------------------
    | Compatibility
    |--------------------------------------------------------------------------
    |
    | hide_incompatible: when true, packages that do not support the current
    | Laravel or PHP version are hidden from the menu. When false (default),
    | they are still shown but tagged with a "⚠ incompatible" marker and the
    | installation is guarded by an explicit confirmation.
    |
    */

    'compatibility' => [
        'hide_incompatible' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Dedicated log channel for package orchestrator installations.
    | Logs are written to `storage/logs/package-orchestrator.log`.
    |
    */

    'logging' => [
        'channel' => 'package-orchestrator',
    ],
];