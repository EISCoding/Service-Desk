<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Fresh deployment without dependencies: explain the one step that cannot happen in the browser.
if (! file_exists(__DIR__.'/../vendor/autoload.php')) {
    http_response_code(503);
    echo '<!DOCTYPE html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Einrichtung</title>'
        .'<body style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;background:#f5f5f7;color:#1d1d1f;display:grid;place-items:center;min-height:100vh;margin:0;padding:16px">'
        .'<main style="max-width:480px;background:#fff;border-radius:16px;padding:28px;box-shadow:0 12px 32px -8px rgba(0,0,0,.2)">'
        .'<h1 style="font-size:22px;margin:0 0 8px">Fast geschafft</h1>'
        .'<p style="color:#636366;line-height:1.5">Es fehlen noch die PHP-Abhängigkeiten. In Plesk: <b>Websites &amp; Domains → PHP Composer</b> (oder Laravel Toolkit), '
        .'die <code>composer.json</code> der Anwendung wählen, Umgebung <b>Produktion</b>, <b>Abhängigkeiten installieren</b>.</p>'
        .'<p style="color:#636366;line-height:1.5">Danach diese Seite neu laden – der Einrichtungsassistent startet automatisch.</p></main></body></html>';
    exit;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
