<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

define('LARAVEL_START', microtime(true));

putenv('LOG_CHANNEL=stderr');
putenv('LOG_PATH=php://stderr');

// Register the Composer autoloader...
require __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel...
/** @var Application $app */
$app = require_once __DIR__ . '/../bootstrap/app.php';

// Auto-seed database if enabled & driver loaded
try {
    if (config('database.default') === 'sqlite' && extension_loaded('pdo_sqlite')) {
        static $bootstrapped = false;
        if (!$bootstrapped) {
            Artisan::call('migrate:fresh', ['--force' => true]);
            Artisan::call('db:seed', ['--force' => true]);
            $bootstrapped = true;
        }
    }
} catch (\Throwable $e) {
    // Ignore seeding exceptions in serverless context
}

$app->handleRequest(Request::capture());
