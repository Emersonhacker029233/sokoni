<?php

namespace App\Http\Controllers\Api\Payments;

use App\Services\Payment\Dalipay\DalipayWebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/payments/webhook/dalipay. Always reachable (not behind the
 * delivery flags), unauthenticated and throttled; DalipayWebhookHandler
 * verifies the signature and ignores anything it doesn't know.
 */
class DalipayWebhookController
{
    public function __invoke(Request $request, DalipayWebhookHandler $handler): JsonResponse
    {
        [$status, $body] = $handler->handle($request->getContent(), $request->header('X-Signature'));

        return response()->json($body, $status);
    }
}
