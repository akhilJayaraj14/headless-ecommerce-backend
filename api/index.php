<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

define('LARAVEL_START', microtime(true));

// Ensure writable storage directory for logs and sqlite on Vercel AWS Lambda
if (!file_exists('/tmp/logs')) {
    @mkdir('/tmp/logs', 0777, true);
}
if (!file_exists('/tmp/framework/views')) {
    @mkdir('/tmp/framework/views', 0777, true);
}

putenv('LOG_CHANNEL=stderr');
putenv('LOG_PATH=php://stderr');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=/tmp/database.sqlite');
putenv('CACHE_STORE=array');

$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = '/tmp/database.sqlite';
$_SERVER['DB_CONNECTION'] = 'sqlite';
$_SERVER['DB_DATABASE'] = '/tmp/database.sqlite';

// Register the Composer autoloader...
require __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel...
/** @var Application $app */
$app = require_once __DIR__ . '/../bootstrap/app.php';

// Rebind storage path to writable /tmp for serverless execution
$app->useStoragePath('/tmp');

// Auto-seed SQLite database in /tmp
try {
    if (!file_exists('/tmp/database.sqlite')) {
        @touch('/tmp/database.sqlite');
    }
    static $bootstrapped = false;
    if (!$bootstrapped) {
        Artisan::call('migrate:fresh', ['--force' => true]);
        Artisan::call('db:seed', ['--force' => true]);
        $bootstrapped = true;
    }
} catch (\Throwable $e) {
    // Ignore seeding error if pdo extension not loaded
}

$app->handleRequest(Request::capture());
