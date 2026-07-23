# Enterprise Headless eCommerce REST API (Laravel + Redis + Docker + AWS)

[![CI/CD Pipeline](https://github.com/akhilJayaraj14/headless-ecommerce-backend/actions/workflows/ci-cd.yml/badge.svg)](https://github.com/akhilJayaraj14/headless-ecommerce-backend/actions)
[![PHP Version](https://img.shields.io/badge/PHP-8.4-777BB4.svg?logo=php)](https://php.net)
[![Laravel Framework](https://img.shields.io/badge/Laravel-11%2F12-FF2D20.svg?logo=laravel)](https://laravel.com)
[![Redis](https://img.shields.io/badge/Redis-7-DC382D.svg?logo=redis)](https://redis.io)
[![Docker](https://img.shields.io/badge/Docker-Ready-2496ED.svg?logo=docker)](https://www.docker.com/)

An enterprise-grade, **Magento-style Headless eCommerce Backend API** built with Laravel 11/12. Designed for high throughput, sub-millisecond catalog responses, atomic inventory protection, state-machine checkout flow, and resilient payment integrations.

---

## Technical Stack & Architecture

- **Core Framework**: Laravel 11/12 (PHP 8.4) API-First Architecture
- **Caching & Locks**: Redis 7 (Catalog response caching & Redis atomic locks for inventory race conditions)
- **Database Engine**: MySQL 8.0 (Relational E-Commerce Data Model) & SQLite Support
- **Queue Workers**: Laravel Redis Queue (Async Order Fulfillment, Low Stock Alerts, Invoicing)
- **Payment Processing**: Stripe PaymentIntents API + Server-Driven Webhook Handler with **Idempotency Event Logging** & Mock Driver
- **Inventory Engine**: Multi-Warehouse Stock Tracking, Atomic Stock Reservation, 15-Minute Reservation Auto-Release Timer
- **DevOps & Cloud Infrastructure**:
  - **Docker & Compose**: `docker-compose.yml` (App, Nginx, MySQL, Redis, Worker, Mailpit)
  - **AWS Ready**: Infrastructure-as-Code (Terraform scripts for ECS Fargate, Aurora MySQL, ElastiCache Redis, S3 bucket) in `/aws`
  - **Vercel Serverless**: Configured with `vercel.json` and serverless handler
  - **GitHub Actions**: `.github/workflows/ci-cd.yml` CI/CD Pipeline

---

## API Architecture Overview

```
                      +----------------------------------+
                      |   Client Application / Storefront|
                      +----------------+-----------------+
                                       | REST API (JSON)
                                       v
                      +----------------------------------+
                      |  Nginx / Vercel Serverless Gateway|
                      +----------------+-----------------+
                                       |
                                       v
                      +----------------------------------+
                      |    Laravel 11 REST Controllers   |
                      +----+-------------------+----+----+
                           |                   |    |
          +----------------+                   |    +----------------+
          | Redis Caching                      | SQL Queries         | Queue Jobs
          v                                    v                     v
  +---------------+                    +---------------+     +---------------+
  | Redis Cache & |                    | MySQL 8.0     |     | Redis Queue & |
  | Stock Lock    |                    | Relational DB |     | Worker Jobs   |
  +---------------+                    +---------------+     +---------------+
```

---

## Core System Modules

### 1. Inventory Reservation Engine (High-Concurrency Protection)
During checkout, the API prevents race conditions and overselling using **Redis Distributed Locks** combined with SQL `FOR UPDATE` transactions:
```php
$lockKey = "inventory:lock:variant:{$variantId}";
$lock = Cache::lock($lockKey, 5);

if ($lock->get()) {
    // Check available stock (quantity_on_hand - quantity_reserved)
    // Increment quantity_reserved atomically
    // Create 15-minute expiration timer
}
```

### 2. Idempotent Payment Webhook Handler
The Stripe Webhook listener tracks unique event IDs (`evt_...`) in a dedicated `webhook_events` database log. Duplicate webhooks sent by payment providers are safely acknowledged without executing duplicate order fulfillments.

### 3. Interactive OpenAPI Documentation
Interactive API docs powered by Scalar are served directly at `/docs`.

---

## Key API Endpoints

| Method | Endpoint | Description |
| :--- | :--- | :--- |
| **GET** | `/docs` | Interactive OpenAPI API Documentation |
| **POST** | `/api/v1/auth/register` | Customer Registration with Sanctum Token |
| **POST** | `/api/v1/auth/login` | Customer Authentication |
| **GET** | `/api/v1/products` | List Catalog Products (Redis Cached) |
| **GET** | `/api/v1/products/{slug}` | Detailed Product Record with Variants & Stock |
| **GET** | `/api/v1/cart` | Get Active Cart & Subtotal |
| **POST** | `/api/v1/cart/items` | Add Item to Cart |
| **POST** | `/api/v1/checkout` | Initialize Checkout, Reserve Stock & Create PaymentIntent |
| **POST** | `/api/v1/payments/webhook` | Stripe / Mock Idempotent Webhook Listener |
| **GET** | `/api/v1/admin/inventory` | Multi-Warehouse Stock Audit & Allocation |

---

## Quickstart Guide

### Option 1: Running with Docker Compose
```bash
# Clone the repository
git clone https://github.com/akhilJayaraj14/headless-ecommerce-backend.git
cd headless-ecommerce-backend

# Spin up containers
docker-compose up -d --build

# Run database migrations and seed demo data
docker-compose exec app php artisan migrate:fresh --seed
```
Visit `http://localhost:8000/docs` to test endpoints.

### Option 2: Running Locally
```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan test
php artisan serve
```

---

## Testing & Quality Assurance

Run the automated test suite covering catalog caching, cart calculations, atomic stock locks, webhook idempotency, and admin inventory audits:

```bash
php artisan test
```

```
PASS  Tests\Feature\EcommerceTest
✓ can fetch catalog products
✓ can add item to cart and calculate total
✓ checkout reserves stock and creates order
✓ webhook handles payment succeeded idempotently
✓ admin can view inventory audit

Tests:    7 passed (64 assertions)
Duration: 0.35s
```

---

## License

Designed and developed by [Akhil Jayaraj](https://github.com/akhilJayaraj14). Open-sourced under the MIT License.
