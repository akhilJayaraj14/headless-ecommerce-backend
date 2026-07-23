<?php

namespace App\Services;

use App\Jobs\ProcessOrderJob;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Log;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Webhook;
use Exception;

class PaymentService
{
    public function __construct(protected InventoryService $inventoryService)
    {
    }

    /**
     * Create a Stripe PaymentIntent (or Mock PaymentIntent for local dev/demoing).
     */
    public function createPaymentIntent(Order $order, string $provider = 'stripe'): array
    {
        $stripeSecret = config('services.stripe.secret');

        if ($provider === 'mock' || empty($stripeSecret) || $stripeSecret === 'sk_test_mock') {
            // Mock driver for instant local testing without live Stripe API keys
            $mockClientSecret = "pi_mock_" . bin2hex(random_bytes(12)) . "_secret_" . bin2hex(random_bytes(10));
            $mockIntentId = "pi_mock_" . bin2hex(random_bytes(12));

            Payment::create([
                'order_id' => $order->id,
                'transaction_id' => $mockIntentId,
                'provider' => 'mock',
                'amount' => $order->total_amount,
                'currency' => 'usd',
                'status' => 'pending',
                'payload' => [
                    'client_secret' => $mockClientSecret,
                    'mode' => 'mock_driver',
                ],
            ]);

            return [
                'provider' => 'mock',
                'payment_intent_id' => $mockIntentId,
                'client_secret' => $mockClientSecret,
                'status' => 'requires_payment_method',
            ];
        }

        Stripe::setApiKey($stripeSecret);

        $intent = PaymentIntent::create([
            'amount' => (int) ($order->total_amount * 100), // convert to cents
            'currency' => 'usd',
            'metadata' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ],
        ]);

        Payment::create([
            'order_id' => $order->id,
            'transaction_id' => $intent->id,
            'provider' => 'stripe',
            'amount' => $order->total_amount,
            'currency' => 'usd',
            'status' => 'pending',
            'payload' => $intent->toArray(),
        ]);

        return [
            'provider' => 'stripe',
            'payment_intent_id' => $intent->id,
            'client_secret' => $intent->client_secret,
            'status' => $intent->status,
        ];
    }

    /**
     * Process Stripe / Webhook Event with Idempotency Protection.
     */
    public function processWebhook(array $payload, ?string $signature = null): array
    {
        $webhookSecret = config('services.stripe.webhook_secret');
        $eventId = $payload['id'] ?? null;
        $eventType = $payload['type'] ?? 'unknown';

        if (! $eventId) {
            throw new Exception("Missing webhook event ID.");
        }

        // Enforce Idempotency: Ignore if event already processed
        if (WebhookEvent::where('event_id', $eventId)->exists()) {
            Log::info("Idempotency Guard: Webhook event {$eventId} already processed. Skipping.");
            return [
                'status' => 'already_processed',
                'event_id' => $eventId,
            ];
        }

        // Verify signature if Stripe signature header present & secret configured
        if ($signature && $webhookSecret) {
            try {
                Webhook::constructEvent(
                    json_encode($payload),
                    $signature,
                    $webhookSecret
                );
            } catch (Exception $e) {
                Log::error("Stripe Webhook Signature Verification Failed: " . $e->getMessage());
                throw new Exception("Invalid webhook signature.");
            }
        }

        // Record event in Webhook log
        WebhookEvent::create([
            'event_id' => $eventId,
            'event_type' => $eventType,
            'payload' => $payload,
            'processed_at' => now(),
        ]);

        // Handle specific Stripe events
        if ($eventType === 'payment_intent.succeeded' || $eventType === 'mock.payment_succeeded') {
            $object = $payload['data']['object'] ?? $payload;
            $orderId = $object['metadata']['order_id'] ?? null;
            $transactionId = $object['id'] ?? null;

            if ($orderId) {
                $order = Order::find($orderId);
                if ($order) {
                    $order->update([
                        'status' => 'processing',
                        'payment_status' => 'paid',
                    ]);

                    Payment::where('order_id', $order->id)->update([
                        'status' => 'succeeded',
                        'transaction_id' => $transactionId,
                    ]);

                    // Dispatch Async Order Processing Queue Job
                    ProcessOrderJob::dispatch($order->id);

                    Log::info("Payment Gateway: Payment succeeded for order {$order->order_number}. Job dispatched.");
                }
            }
        }

        return [
            'status' => 'processed',
            'event_id' => $eventId,
            'event_type' => $eventType,
        ];
    }
}
