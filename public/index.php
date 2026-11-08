<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Installer runs BEFORE Laravel: the database and application key do not
// exist yet. This works on both Apache and Nginx front-controller routing.
$setupRoute = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($setupRoute === '/installer' || $setupRoute === '/installer/') {
    require __DIR__.'/installer/index.php';
    exit;
}

// An unconfigured package is never allowed to boot a partially configured
// Laravel application or expose framework exceptions.
$envFile = __DIR__.'/../.env';
if (!is_file($envFile) || !preg_match('/^APP_KEY=.+$/m', (string) file_get_contents($envFile))) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        header('Location: /installer/', true, 302);
        exit;
    }
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Installation is required.');
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
