<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PaymentWebhookLog;
use App\Services\Payments\QrisWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Unauthenticated QRIS webhook receiver. There is no user/tenant session here —
 * trust comes entirely from the provider signature, verified inside
 * QrisWebhookService. Every payload is logged before any payment mutation.
 *
 *   - processed / ignored (duplicate or unknown reference) → 200 so the provider
 *     stops retrying;
 *   - invalid signature                                    → 403 (payment untouched);
 *   - unknown/disabled provider or parse failure           → 422.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(private readonly QrisWebhookService $webhooks) {}

    public function store(Request $request, string $provider): JsonResponse
    {
        $log = $this->webhooks->process(
            provider: $provider,
            headers: $request->headers->all(),
            payload: $request->json()->all(),
            rawBody: $request->getContent(),
        );

        $status = match (true) {
            in_array($log->processing_status, [
                PaymentWebhookLog::STATUS_PROCESSED,
                PaymentWebhookLog::STATUS_IGNORED,
            ], true) => 200,
            $log->signature_valid === false => 403,
            default => 422,
        };

        return response()->json([
            'data' => [
                'processing_status' => $log->processing_status,
                'signature_valid' => $log->signature_valid,
            ],
            'meta' => [
                'foundation' => 'POS_ANDROID_SAAS_FOUNDATION',
            ],
        ], $status);
    }
}
