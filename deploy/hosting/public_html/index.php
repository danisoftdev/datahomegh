<?php

/**
 * Hostinger document root entrypoint.
 *
 * Expected layout (replace "datahomegh" if your home folder name differs):
 *   ~/datahomegh/          → Laravel root (artisan, app, vendor, .env, …)
 *   ~/public_html/         → This file + .htaccess + built assets (public/build)
 *
 * Copy this file and .htaccess from deploy/hosting/public_html/ into public_html/
 * after deploying the Laravel app one level above the web root.
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$laravelRoot = dirname(__DIR__).'/datahomegh';

if (! is_dir($laravelRoot)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Laravel application not found. Expected directory: '.$laravelRoot;
    exit(1);
}

if (file_exists($maintenance = $laravelRoot.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $laravelRoot.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $laravelRoot.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
