<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EcommerceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_can_fetch_catalog_products(): void
    {
        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => ['id', 'name', 'slug', 'base_price', 'variants'],
                    ],
                ],
            ]);
    }

    public function test_can_add_item_to_cart_and_calculate_total(): void
    {
        $variant = ProductVariant::first();

        $response = $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.summary.total_items', 2);
    }

    public function test_checkout_reserves_stock_and_creates_order(): void
    {
        $variant = ProductVariant::first();
        $initialInventory = Inventory::where('product_variant_id', $variant->id)->first();

        // 1. Create Cart with item
        $cart = Cart::create(['status' => 'active']);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 3,
            'unit_price' => $variant->price,
        ]);

        // 2. Perform Checkout
        $response = $this->postJson('/api/v1/checkout', [
            'cart_id' => $cart->id,
            'shipping_address' => ['street' => '123 Tech Lane', 'city' => 'San Francisco', 'country' => 'US'],
            'billing_address' => ['street' => '123 Tech Lane', 'city' => 'San Francisco', 'country' => 'US'],
            'payment_method' => 'mock',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.order.status', 'pending')
            ->assertJsonPath('data.payment.provider', 'mock');

        // 3. Verify Reserved Stock Incremented in DB
        $updatedInventory = Inventory::where('product_variant_id', $variant->id)->first();
        $this->assertEquals($initialInventory->quantity_reserved + 3, $updatedInventory->quantity_reserved);
    }

    public function test_webhook_handles_payment_succeeded_idempotently(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-TEST1234',
            'status' => 'pending',
            'total_amount' => 100.00,
            'payment_status' => 'pending',
        ]);

        $payload = [
            'id' => 'evt_test_webhook_001',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_123',
                    'metadata' => [
                        'order_id' => $order->id,
                    ],
                ],
            ],
        ];

        // First webhook execution
        $response1 = $this->postJson('/api/v1/payments/webhook', $payload);
        $response1->assertStatus(200)->assertJsonPath('data.status', 'processed');

        // Second webhook execution (Idempotency test)
        $response2 = $this->postJson('/api/v1/payments/webhook', $payload);
        $response2->assertStatus(200)->assertJsonPath('data.status', 'already_processed');
    }

    public function test_admin_can_view_inventory_audit(): void
    {
        $response = $this->getJson('/api/v1/admin/inventory');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'data' => [
                    'inventories' => [
                        '*' => ['id', 'sku', 'product_name', 'quantity_on_hand', 'quantity_reserved', 'available_stock'],
                    ],
                ],
            ]);
    }
}
