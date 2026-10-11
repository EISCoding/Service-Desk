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
    | Mail
    |--------------------------------------------------------------------------
    |
    | fetch_limit: mails per mailbox and minute (the queue worker runs <= 50 s).
    | reopen_days: a reply to a closed ticket within this many days reopens it,
    | later replies start a new ticket (adjustable in the admin in Phase 6).
    |
    */

    'mail' => [
        'fetch_limit' => (int) env('MAIL_FETCH_LIMIT', 25),
        'reopen_days' => (int) env('TICKET_REOPEN_DAYS', 14),
        // Laravel mailer used instead of the mailbox SMTP (e.g. "log" for local
        // development). Leave empty in production.
        'mailer' => env('MAILBOX_MAILER') ?: null,
    ],

    'queue' => [
        // Work off jobs queued by a web request right after its response
        // (mails leave within seconds instead of up to a minute).
        'after_response' => (bool) env('QUEUE_AFTER_RESPONSE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    |
    | Maximum size per file in kilobytes. PHP's upload_max_filesize and
    | post_max_size (Plesk → PHP settings) must be at least as large.
    |
    */

    'attachments' => [
        'max_kb' => (int) env('ATTACHMENT_MAX_KB', 20480),
        'max_files' => 10,
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
