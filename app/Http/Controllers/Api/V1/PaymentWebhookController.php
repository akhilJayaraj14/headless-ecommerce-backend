<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class PaymentWebhookController extends Controller
{
    use ApiResponse;

    public function __construct(protected PaymentService $paymentService)
    {
    }

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $signature = $request->header('Stripe-Signature');

        try {
            $result = $this->paymentService->processWebhook($payload, $signature);
            return $this->successResponse($result, 'Webhook handled');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }
}
