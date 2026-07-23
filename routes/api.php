<?php

use App\Http\Controllers\Api\V1\AdminInventoryController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentWebhookController;
use App\Http\Controllers\ApiDocsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Enterprise Headless eCommerce Engine
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // OpenAPI Docs Endpoint
    Route::get('/openapi.json', [ApiDocsController::class, 'spec']);

    // Auth Routes
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Catalog Routes (Public)
    Route::get('/products', [CatalogController::class, 'products']);
    Route::get('/products/{slug}', [CatalogController::class, 'product']);
    Route::get('/categories', [CatalogController::class, 'categories']);
    Route::get('/brands', [CatalogController::class, 'brands']);

    // Cart Routes (Public / Guest / Session / User)
    Route::get('/cart', [CartController::class, 'show']);
    Route::post('/cart/items', [CartController::class, 'addItem']);
    Route::delete('/cart/items/{id}', [CartController::class, 'removeItem']);

    // Checkout & Webhooks
    Route::post('/checkout', [CheckoutController::class, 'process']);
    Route::post('/payments/webhook', [PaymentWebhookController::class, 'handle']);

    // Authenticated Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);
    });

    // Admin & Inventory Operations
    Route::prefix('admin')->group(function () {
        Route::get('/inventory', [AdminInventoryController::class, 'index']);
        Route::put('/inventory/{id}', [AdminInventoryController::class, 'updateStock']);
    });
});
