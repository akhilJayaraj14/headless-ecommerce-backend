<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Register the Composer autoloader...
require __DIR__ . '/../vendor/autoload.php';

// Prepare SQLite database in /tmp for serverless execution
if (!file_exists('/tmp/database.sqlite') && file_exists(__DIR__ . '/../database/database.sqlite')) {
    @copy(__DIR__ . '/../database/database.sqlite', '/tmp/database.sqlite');
}

// Bootstrap Laravel and handle request...
/** @var Application $app */
$app = require_once __DIR__ . '/../bootstrap/app.php';

$app->handleRequest(Request::capture());
