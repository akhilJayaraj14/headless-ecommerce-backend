<?php

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Writable storage directories for AWS Lambda / Vercel
$storageDirs = [
    '/tmp/storage/app/public',
    '/tmp/storage/framework/cache/data',
    '/tmp/storage/framework/sessions',
    '/tmp/storage/framework/testing',
    '/tmp/storage/framework/views',
    '/tmp/storage/logs',
];

foreach ($storageDirs as $dir) {
    if (!file_exists($dir)) {
        @mkdir($dir, 0777, true);
    }
}

putenv('APP_DEBUG=true');
putenv('LOG_CHANNEL=stderr');
putenv('LOG_PATH=php://stderr');
putenv('CACHE_STORE=array');
putenv('SESSION_DRIVER=array');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=/tmp/database.sqlite');

$_ENV['APP_DEBUG'] = 'true';
$_ENV['LOG_CHANNEL'] = 'stderr';
$_ENV['LOG_PATH'] = 'php://stderr';
$_ENV['CACHE_STORE'] = 'array';
$_ENV['SESSION_DRIVER'] = 'array';
$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = '/tmp/database.sqlite';

require __DIR__ . '/../vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__ . '/../bootstrap/app.php';

// Initialize Console Kernel to run SQLite migration & seed in /tmp if empty
try {
    if (!file_exists('/tmp/database.sqlite') || filesize('/tmp/database.sqlite') === 0) {
        @touch('/tmp/database.sqlite');
        $console = $app->make(ConsoleKernel::class);
        $console->call('migrate', ['--force' => true]);
        $console->call('db:seed', ['--force' => true]);
    }
} catch (\Throwable $e) {
    // Ignore seeding error in serverless environment
}

$app->handleRequest(Request::capture());
