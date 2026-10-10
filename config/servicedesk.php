<?php

return [

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
