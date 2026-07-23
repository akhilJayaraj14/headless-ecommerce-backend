<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class ApiDocsController extends Controller
{
    public function docs(): Response
    {
        $specUrl = url('/api/v1/openapi.json');

        $html = <<<HTML
<!doctype html>
<html>
  <head>
    <title>Enterprise Headless eCommerce API Documentation</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
      body {
        margin: 0;
        padding: 0;
        background: #0f172a;
        color: #f8fafc;
        font-family: system-ui, -apple-system, sans-serif;
      }
    </style>
  </head>
  <body>
    <script
      id="api-reference"
      data-url="{$specUrl}"></script>
    <script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>
  </body>
</html>
HTML;

        return response($html, 200, ['Content-Type' => 'text/html']);
    }

    public function spec()
    {
        $spec = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'Enterprise Headless eCommerce REST API',
                'description' => 'High-performance Magento-inspired Headless eCommerce API with Redis stock locking, state machine checkout, Stripe idempotent webhooks, and multi-warehouse inventory.',
                'version' => '1.0.0',
            ],
            'servers' => [
                ['url' => url('/api/v1'), 'description' => 'Current API Endpoint'],
            ],
            'paths' => [
                '/auth/register' => [
                    'post' => [
                        'summary' => 'Register a new customer',
                        'tags' => ['Authentication'],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'name' => ['type' => 'string', 'example' => 'Akhil Jayaraj'],
                                            'email' => ['type' => 'string', 'example' => 'akhil@example.com'],
                                            'password' => ['type' => 'string', 'example' => 'secret123'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => ['description' => 'Customer registered with Sanctum API Token'],
                        ],
                    ],
                ],
                '/auth/login' => [
                    'post' => [
                        'summary' => 'Customer Login',
                        'tags' => ['Authentication'],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'email' => ['type' => 'string', 'example' => 'akhil@example.com'],
                                            'password' => ['type' => 'string', 'example' => 'secret123'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Authenticated successfully'],
                        ],
                    ],
                ],
                '/products' => [
                    'get' => [
                        'summary' => 'List products with Redis Caching',
                        'tags' => ['Catalog'],
                        'parameters' => [
                            ['name' => 'category', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'search', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer']],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Paginated product list'],
                        ],
                    ],
                ],
                '/products/{slug}' => [
                    'get' => [
                        'summary' => 'Get product details by slug',
                        'tags' => ['Catalog'],
                        'parameters' => [
                            ['name' => 'slug', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Detailed product record with variants & inventory'],
                        ],
                    ],
                ],
                '/cart' => [
                    'get' => [
                        'summary' => 'Get active cart',
                        'tags' => ['Cart'],
                        'parameters' => [
                            ['name' => 'X-Cart-ID', 'in' => 'header', 'schema' => ['type' => 'string']],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Cart details with subtotal and item count'],
                        ],
                    ],
                ],
                '/cart/items' => [
                    'post' => [
                        'summary' => 'Add item to cart',
                        'tags' => ['Cart'],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'product_variant_id' => ['type' => 'integer', 'example' => 1],
                                            'quantity' => ['type' => 'integer', 'example' => 2],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Item added to cart'],
                        ],
                    ],
                ],
                '/checkout' => [
                    'post' => [
                        'summary' => 'Initialize Checkout, Lock Stock & Create Stripe PaymentIntent',
                        'tags' => ['Checkout & Payment'],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'cart_id' => ['type' => 'string', 'format' => 'uuid'],
                                            'shipping_address' => ['type' => 'object'],
                                            'billing_address' => ['type' => 'object'],
                                            'payment_method' => ['type' => 'string', 'example' => 'mock'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => ['description' => 'Order created in pending state, stock reserved for 15m, payment intent created'],
                        ],
                    ],
                ],
                '/payments/webhook' => [
                    'post' => [
                        'summary' => 'Idempotent Webhook Listener (Stripe / Mock)',
                        'tags' => ['Checkout & Payment'],
                        'responses' => [
                            '200' => ['description' => 'Webhook received and processed idempotently'],
                        ],
                    ],
                ],
                '/admin/inventory' => [
                    'get' => [
                        'summary' => 'Admin Multi-Warehouse Inventory Audit',
                        'tags' => ['Admin & Inventory'],
                        'responses' => [
                            '200' => ['description' => 'Warehouse stock overview'],
                        ],
                    ],
                ],
            ],
        ];

        return response()->json($spec);
    }
}
