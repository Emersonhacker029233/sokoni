<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives Kibonet's delivery-report POST (`KIBONET_DELIVERY_REPORT_URL` /
 * `deliveryReportUrl` in the send payload — see KibonetSmsGateway). Kibonet's
 * docs don't specify a payload shape for this callback, so everything it
 * sends is simply logged to the `sms` channel rather than parsed into named
 * fields that might not match — this is diagnostics, not a status update
 * any other part of the app currently reads.
 *
 * Deliberately outside CSRF protection (`bootstrap/app.php`'s
 * `validateCsrfTokens(except: ...)`) since Kibonet, not a browser with a
 * Sokoni session, is the caller.
 */
class SmsDeliveryCallbackController
{
    public function __invoke(Request $request): JsonResponse
    {
        Log::channel('sms')->info('Kibonet: delivery report received', [
            'ip' => $request->ip(),
            'payload' => $request->all(),
        ]);

        return response()->json(['received' => true]);
    }
}
