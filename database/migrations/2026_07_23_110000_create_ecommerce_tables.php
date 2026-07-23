<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Categories
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Brands
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_url')->nullable();
            $table->timestamps();
        });

        // 3. Products
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('base_price', 10, 2);
            $table->string('status')->default('active'); // active, draft, archived
            $table->string('featured_image')->nullable();
            $table->timestamps();
        });

        // 4. Product Variants
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->string('sku')->unique();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->json('attributes')->nullable(); // e.g. {"color": "Blue", "size": "L"}
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 5. Inventories (Multi-Warehouse / Location Stock Engine)
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->onDelete('cascade');
            $table->string('location_name')->default('Main Warehouse');
            $table->integer('quantity_on_hand')->default(0);
            $table->integer('quantity_reserved')->default(0);
            $table->timestamps();

            $table->unique(['product_variant_id', 'location_name']);
        });

        // 6. Carts
        Schema::create('carts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('session_id')->nullable()->index();
            $table->string('status')->default('active'); // active, abandoned, converted
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // 7. Cart Items
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('cart_id');
            $table->foreign('cart_id')->references('id')->on('carts')->onDelete('cascade');
            $table->foreignId('product_variant_id')->constrained('product_variants')->onDelete('cascade');
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->timestamps();

            $table->unique(['cart_id', 'product_variant_id']);
        });

        // 8. Stock Reservations (Locks stock during checkout state)
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->uuid('cart_id');
            $table->foreignId('product_variant_id')->constrained('product_variants')->onDelete('cascade');
            $table->integer('quantity');
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        // 9. Orders State Machine Table
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('order_number')->unique();
            $table->string('status')->default('pending'); // pending, paid, processing, shipped, completed, cancelled, refunded
            $table->decimal('total_amount', 10, 2);
            $table->json('shipping_address')->nullable();
            $table->json('billing_address')->nullable();
            $table->string('payment_status')->default('pending'); // pending, paid, failed, refunded
            $table->string('payment_method')->default('stripe'); // stripe, mock
            $table->timestamps();
        });

        // 10. Order Items
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('order_id');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreignId('product_variant_id')->constrained('product_variants')->nullOnDelete();
            $table->string('sku');
            $table->string('product_name');
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->timestamps();
        });

        // 11. Payments & Transactions
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('order_id');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->string('transaction_id')->nullable()->index();
            $table->string('provider')->default('stripe'); // stripe, mock
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('USD');
            $table->string('status')->default('pending'); // pending, succeeded, failed, refunded
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        // 12. Idempotent Webhook Log
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->string('event_id')->primary(); // Stripe event ID e.g. evt_123
            $table->string('event_type');
            $table->json('payload');
            $table->timestamp('processed_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('inventories');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};
