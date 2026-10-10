<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Customer numbers
    |--------------------------------------------------------------------------
    |
    | New customers get "<prefix><next number>" (e.g. K-10001) unless a number
    | is entered manually. The next number is the highest existing one + 1.
    |
    */

    'customer_number' => [
        'prefix' => 'K-',
        'start' => 10001,
    ],

    /*
    |--------------------------------------------------------------------------
    | Countries for addresses (ISO 3166-1 alpha-2 => German name)
    |--------------------------------------------------------------------------
    */

    'countries' => [
        'DE' => 'Deutschland',
        'AT' => 'Österreich',
        'CH' => 'Schweiz',
        'LI' => 'Liechtenstein',
        'LU' => 'Luxemburg',
        'NL' => 'Niederlande',
        'BE' => 'Belgien',
        'FR' => 'Frankreich',
        'IT' => 'Italien',
        'PL' => 'Polen',
        'CZ' => 'Tschechien',
        'DK' => 'Dänemark',
    ],

    /*
    |--------------------------------------------------------------------------
    | Local admin account
    |--------------------------------------------------------------------------
    |
    | Created by `php artisan db:seed` when APP_ENV=local. Production admins
    | are created with `php artisan servicedesk:create-admin` (Phase 6).
    |
    */

    'seed_admin' => [
        'name' => env('SEED_ADMIN_NAME', 'Admin'),
        'email' => env('SEED_ADMIN_EMAIL', 'admin@servicedesk.test'),
        'password' => env('SEED_ADMIN_PASSWORD', 'password'),
    ],

];
