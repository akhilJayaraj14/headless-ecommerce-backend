<?php

use App\Http\Controllers\ApiDocsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/docs');
});

Route::get('/docs', [ApiDocsController::class, 'docs']);
