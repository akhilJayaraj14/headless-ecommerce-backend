<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with demo eCommerce data.
     */
    public function run(): void
    {
        // 1. Customer & Admin Users
        $demoUser = User::firstOrCreate(
            ['email' => 'customer@example.com'],
            [
                'name' => 'Demo Customer',
                'password' => Hash::make('password123'),
            ]
        );

        $adminUser = User::firstOrCreate(
            ['email' => 'admin@ecommerce.com'],
            [
                'name' => 'Store Administrator',
                'password' => Hash::make('admin123'),
            ]
        );

        // 2. Categories
        $electronics = Category::create(['name' => 'Electronics', 'slug' => 'electronics']);
        $audio = Category::create(['name' => 'Audio & Headphones', 'slug' => 'audio-headphones', 'parent_id' => $electronics->id]);
        $laptops = Category::create(['name' => 'Laptops & Computers', 'slug' => 'laptops-computers', 'parent_id' => $electronics->id]);

        // 3. Brands
        $apple = Brand::create(['name' => 'Apple', 'slug' => 'apple', 'logo_url' => 'https://assets.example.com/brands/apple.png']);
        $sony = Brand::create(['name' => 'Sony', 'slug' => 'sony', 'logo_url' => 'https://assets.example.com/brands/sony.png']);

        // 4. Products & Variants
        // Product 1: MacBook Pro
        $macbook = Product::create([
            'category_id' => $laptops->id,
            'brand_id' => $apple->id,
            'name' => 'MacBook Pro 16-inch M3 Max',
            'slug' => 'macbook-pro-16-m3-max',
            'description' => 'Supercharged for pros with M3 Max 16-core CPU and 40-core GPU.',
            'base_price' => 3499.00,
            'status' => 'active',
            'featured_image' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8',
        ]);

        $mbVariant1 = ProductVariant::create([
            'product_id' => $macbook->id,
            'sku' => 'MBP16-M3-36GB-1TB',
            'name' => 'Space Black / 36GB RAM / 1TB SSD',
            'price' => 3499.00,
            'attributes' => ['color' => 'Space Black', 'ram' => '36GB', 'storage' => '1TB'],
        ]);

        $mbVariant2 = ProductVariant::create([
            'product_id' => $macbook->id,
            'sku' => 'MBP16-M3-48GB-2TB',
            'name' => 'Silver / 48GB RAM / 2TB SSD',
            'price' => 3999.00,
            'attributes' => ['color' => 'Silver', 'ram' => '48GB', 'storage' => '2TB'],
        ]);

        // Product 2: Sony WH-1000XM5
        $sonyHeadphones = Product::create([
            'category_id' => $audio->id,
            'brand_id' => $sony->id,
            'name' => 'Sony WH-1000XM5 Wireless Headphones',
            'slug' => 'sony-wh-1000xm5-headphones',
            'description' => 'Industry-leading noise cancelling with Auto NC Optimizer.',
            'base_price' => 399.99,
            'status' => 'active',
            'featured_image' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e',
        ]);

        $sonyVariant1 = ProductVariant::create([
            'product_id' => $sonyHeadphones->id,
            'sku' => 'SONY-XM5-BLK',
            'name' => 'Black',
            'price' => 399.99,
            'attributes' => ['color' => 'Black'],
        ]);

        $sonyVariant2 = ProductVariant::create([
            'product_id' => $sonyHeadphones->id,
            'sku' => 'SONY-XM5-SIL',
            'name' => 'Silver',
            'price' => 399.99,
            'attributes' => ['color' => 'Silver'],
        ]);

        // 5. Multi-Warehouse Inventories
        Inventory::create([
            'product_variant_id' => $mbVariant1->id,
            'location_name' => 'Main Warehouse (US West)',
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
        ]);

        Inventory::create([
            'product_variant_id' => $mbVariant1->id,
            'location_name' => 'East Coast Hub (US East)',
            'quantity_on_hand' => 25,
            'quantity_reserved' => 0,
        ]);

        Inventory::create([
            'product_variant_id' => $mbVariant2->id,
            'location_name' => 'Main Warehouse (US West)',
            'quantity_on_hand' => 15,
            'quantity_reserved' => 0,
        ]);

        Inventory::create([
            'product_variant_id' => $sonyVariant1->id,
            'location_name' => 'Main Warehouse (US West)',
            'quantity_on_hand' => 120,
            'quantity_reserved' => 0,
        ]);

        Inventory::create([
            'product_variant_id' => $sonyVariant2->id,
            'location_name' => 'Main Warehouse (US West)',
            'quantity_on_hand' => 80,
            'quantity_reserved' => 0,
        ]);
    }
}
