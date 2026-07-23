<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

define('LARAVEL_START', microtime(true));

// Register the Composer autoloader...
require __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel...
/** @var Application $app */
$app = require_once __DIR__ . '/../bootstrap/app.php';

// Auto-seed in-memory SQLite database for serverless demo if empty
try {
    if (config('database.default') === 'sqlite' && config('database.connections.sqlite.database') === ':memory:') {
        static $bootstrapped = false;
        if (!$bootstrapped) {
            Artisan::call('migrate:fresh', ['--force' => true]);
            Artisan::call('db:seed', ['--force' => true]);
            $bootstrapped = true;
        }
    }
} catch (\Throwable $e) {
    // Ignore if already seeded
}

$app->handleRequest(Request::capture());
