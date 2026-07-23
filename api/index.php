<?php

// Vercel Serverless Entrypoint for Laravel REST API
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

// Ensure /tmp directory structure for serverless execution
if (!file_exists('/tmp/database.sqlite') && file_exists(__DIR__ . '/../database/database.sqlite')) {
    copy(__DIR__ . '/../database/database.sqlite', '/tmp/database.sqlite');
}

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

$response->send();

$kernel->terminate($request, $response);
