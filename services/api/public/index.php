<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$basePath = is_file(__DIR__.'/artisan') ? __DIR__ : dirname(__DIR__);
$environmentFile = $basePath.'/.env';
$environment = is_file($environmentFile) ? (string) file_get_contents($environmentFile) : '';
$installationLock = $basePath.'/storage/app/install-bootstrap.complete';
if (! preg_match('/^APP_KEY=(?!\s*$).+/m', $environment) || ! is_file($installationLock)) {
    require __DIR__.'/install.php';
    exit;
}

if (isset($_GET['setup'])) {
    $_SERVER['REQUEST_URI'] = '/setup';
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $basePath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $basePath.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $basePath.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
