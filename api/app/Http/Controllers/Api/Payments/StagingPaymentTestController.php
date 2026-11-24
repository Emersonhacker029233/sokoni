<?php

namespace App\Http\Controllers\Api\Payments;

use App\Models\DeliveryJob;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\Delivery\DeliveryJobService;
use App\Services\Delivery\DeliveryMessages;
use App\Services\Delivery\DeliveryPaymentService;
use App\Services\Payment\Dalipay\DalipayConfig;
use App\Services\Payment\Dalipay\DalipayWebhookHandler;
use App\Services\Payment\DalipayGateway;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Staging only (EnsureStagingPaymentsTest): tests Dalipay's TEST mode from
 * PowerShell (tools/staging-dalipay-test.ps1) while Bolt is switched off,
 * through the real payment service, gateway, webhook handler and cron.
 *
 * Each test makes a delivery fee to pay: a test order from a test shop to a
 * test buyer (both marked "Staging payments test", never real accounts)
 * with a delivery job waiting for payment. Nothing is booked on Bolt: the
 * order never becomes ready. Refused unless PAYMENT_PROVIDER=dalipay with
 * valid keys.
 *
 * Test keys (DALIPAY_ENV=test): any test number, TSh 100 to 10,000.
 *
 * Live keys (DALIPAY_ENV=live, real money) only with ALL of: APP_ENV=staging,
 * the token, STAGING_PAYMENTS_ALLOW_LIVE=true, `live: true` in the request
 * (staging-dalipay-test.ps1 -Live, after you type YES PAY), a normal payment,
 * at most TSh 2,000, and the number exactly STAGING_PAYMENTS_LIVE_MSISDN.
 * Anything else is refused, and every live collection is logged as a
 * warning. Production answers 404 whatever the configuration
 * (EnsureStagingPaymentsTest).
 *
 *  POST /api/staging/payments                      {msisdn, channel?, amount? (1000), mode: normal|no_answer, live?}
 *  GET  /api/staging/payments/{id}                 asks Dalipay, as the app's polling does
 *  POST /api/staging/payments/{id}/replay-webhook  the same signed webhook twice, signed here
 *
 * `no_answer` (test keys only) sends the push to an unreachable address
 * (192.0.2.1), so the attempt gets no answer and must expire after
 * PAYMENT_ATTEMPT_TIMEOUT_SECONDS.
 */
class StagingPaymentTestController
{
    private const UNREACHABLE = 'https://192.0.2.1';

    private const DEFAULT_AMOUNT = 1000;

    public function store(Request $request, PaymentGatewayFactory $gateways): JsonResponse
    {
        $gateway = config('payments.provider') === 'dalipay' ? $gateways->dalipay() : null;
        if ($gateway === null) {
            return response()->json([
                'code' => 'not_dalipay',
                'message' => 'Runs only with PAYMENT_PROVIDER=dalipay and valid Dalipay keys (see storage/logs for "[payments]").',
            ], 409);
        }
        $live = ! $gateway->capabilities()->sandbox;

        $data = $request->validate([
            'msisdn' => ['required', 'string', 'regex:/^\+255[67]\d{8}$/'],
            // Display only, like the app's choice: Dalipay takes the network from the number.
            'channel' => ['nullable', 'string', Rule::in($gateway->capabilities()->channels)],
            'amount' => ['nullable', 'integer', 'min:100', 'max:10000'],
            'mode' => ['nullable', Rule::in(['normal', 'no_answer'])],
            'live' => ['nullable', 'boolean'],
        ]);
        $mode = $data['mode'] ?? 'normal';
        $amount = (int) ($data['amount'] ?? self::DEFAULT_AMOUNT);
        $wantsLive = (bool) ($data['live'] ?? false);

        if ($wantsLive && ! $live) {
            return response()->json(['code' => 'not_live', 'message' => 'Dalipay is in test mode here (DALIPAY_ENV=test): a live test would pay nothing real. Run without -Live.'], 409);
        }
        if ($live && ($refusal = $this->liveRefusal($wantsLive, $mode, $amount, (string) $data['msisdn'])) !== null) {
            return response()->json(['code' => 'live_not_allowed', 'message' => $refusal], 409);
        }

        [$buyer, $job] = DB::transaction(fn () => $this->fixtures($amount));

        $service = new DeliveryPaymentService(
            $mode === 'no_answer'
                ? new DalipayGateway(DalipayConfig::fromArray([...(array) config('payments.dalipay'), 'base_url' => self::UNREACHABLE, 'timeout_seconds' => 5, 'create_timeout_seconds' => 15], false))
                : $gateway,
            app(DeliveryJobService::class),
            app(DeliveryMessages::class),
        );

        $payment = $service->start($job, $buyer, $data['channel'] ?? null, (string) $data['msisdn'], 'stg'.Str::random(21));
        $payment->forceFill(['meta' => [...(array) $payment->fresh()->meta, 'staging_test' => true, 'staging_mode' => $mode, 'staging_live' => $live]])->save();
        $payment = $payment->fresh();

        if ($live) {
            Log::warning(sprintf(
                '[payments] STAGING LIVE COLLECTION (real money): TSh %d to the staging live number (ending %s), payment #%d, Dalipay %s, status %s. Back to test mode: docs/delivery/STAGING-dalipay.md',
                $amount, substr((string) $data['msisdn'], -3), $payment->id, $payment->provider_reference ?? 'no uuid yet', $payment->status,
            ));
        }

        return response()->json(['data' => $this->view($payment)], 201);
    }

    /** Why a live collection from staging is refused, or null when every condition holds. */
    private function liveRefusal(bool $wantsLive, string $mode, int $amount, string $msisdn): ?string
    {
        $allowed = (string) config('payments.staging_live_msisdn');
        $max = (int) config('payments.staging_live_max_amount', 2000);

        return match (true) {
            ! app()->environment('staging') => 'Live collections from the staging test need APP_ENV=staging.',
            config('payments.staging_allow_live') !== true => 'Dalipay has live keys here. Set STAGING_PAYMENTS_ALLOW_LIVE=true to allow a live test collection.',
            ! $wantsLive => 'Dalipay has live keys here: a real payment needs live: true (staging-dalipay-test.ps1 -Live).',
            $mode !== 'normal' => 'Only a normal payment can be live; no_answer is for test mode.',
            $amount > $max => 'A live test collection is at most TSh '.number_format($max).'.',
            preg_match('/^\+255[67]\d{8}$/', $allowed) !== 1 => 'Set STAGING_PAYMENTS_LIVE_MSISDN (E.164, +255...) to the one number live tests may charge.',
            ! hash_equals($allowed, $msisdn) => 'A live test collection can only go to STAGING_PAYMENTS_LIVE_MSISDN.',
            default => null,
        };
    }

    public function show(int $payment, DeliveryPaymentService $payments): JsonResponse
    {
        return response()->json(['data' => $this->view($payments->confirm($this->testPayment($payment)))]);
    }

    public function replayWebhook(int $payment, DalipayWebhookHandler $webhooks): JsonResponse
    {
        $p = $this->testPayment($payment);
        $secret = (string) config('payments.dalipay.webhook_secret');
        if ($secret === '' || $p->provider_reference === null || ! in_array($p->status, ['succeeded', 'failed', 'refund_pending'], true)) {
            return response()->json(['code' => 'not_replayable', 'message' => 'Needs DALIPAY_WEBHOOK_SECRET and a payment Dalipay has finished (succeeded or failed).'], 409);
        }

        $failed = $p->status === 'failed';
        $body = json_encode([
            'event' => $failed ? 'collection.failed' : 'collection.success',
            'data' => [
                'reference' => $p->meta['dalipay_reference'] ?? null,
                'uuid' => $p->provider_reference,
                'status' => $failed ? 'failed' : 'success',
                'amount' => (int) $p->amount,
                'currency' => $p->currency,
                'external_id' => $p->meta['external_id'] ?? null,
            ],
        ]);
        $signature = hash_hmac('sha256', $body, $secret);

        [$firstStatus, $first] = $webhooks->handle($body, $signature);
        [$secondStatus, $second] = $webhooks->handle($body, $signature);

        return response()->json(['data' => [
            'first' => ['http' => $firstStatus, ...$first],
            'second' => ['http' => $secondStatus, ...$second],
            'payment' => $this->view($p->fresh()),
        ]]);
    }

    /** @return array{0: User, 1: DeliveryJob} */
    private function fixtures(int $amount): array
    {
        $owner = User::query()->firstOrCreate(['email' => 'staging-payments-shop@sokoni.invalid'], ['name' => 'Staging payments test shop']);
        $shop = SellerProfile::query()->where('user_id', $owner->id)->first();
        if ($shop === null) {
            $shop = new SellerProfile;
            $shop->forceFill([
                'user_id' => $owner->id,
                'shop_name' => 'Staging payments test',
                'handle' => 'stgpaytest'.random_int(10, 99),
                'status' => 'verified',
                'lat' => -6.8161,
                'lng' => 39.2803,
                'address' => 'Staging payments test',
            ])->save();
        }
        $buyer = User::query()->firstOrCreate(['email' => 'staging-payments-buyer@sokoni.invalid'], ['name' => 'Staging payments test buyer']);

        $order = new Order;
        $order->forceFill([
            'buyer_id' => $buyer->id,
            'seller_id' => $shop->id,
            'status' => 'accepted',
            'accepted_at' => now(),
            'subtotal' => 0,
            'delivery_fee' => 0,
            'total' => 0,
            'delivery_method' => 'delivery',
            'fulfilment' => 'dispatch',
            'address' => 'STAGING PAYMENT TEST',
            'delivery_lat' => -6.7924,
            'delivery_lng' => 39.2083,
            'payment_method' => 'cash_on_delivery',
            'notes' => 'STAGING PAYMENT TEST (tools/staging-dalipay-test.ps1). Not a real order.',
        ])->save();

        $job = new DeliveryJob;
        $job->forceFill([
            'order_id' => $order->id,
            'status' => 'pending_payment',
            'provider' => DeliveryJob::PROVIDER_BOLT,
            'pickup_lat' => $shop->lat,
            'pickup_lng' => $shop->lng,
            'pickup_address' => $shop->address,
            'dropoff_lat' => $order->delivery_lat,
            'dropoff_lng' => $order->delivery_lng,
            'dropoff_address' => $order->address,
            'distance_km' => 0,
            'distance_source' => 'straight_line',
            'base_fee' => 0,
            'per_km_fee' => 0,
            'fee' => $amount,
            'commission_pct' => 0,
            'commission' => 0,
            'driver_earnings' => 0,
            'delivery_pin' => '',
            'payment_status' => 'unpaid',
            'currency' => 'TZS',
            'pending_payment_at' => now(),
        ])->save();

        return [$buyer, $job];
    }

    private function testPayment(int $id): Payment
    {
        $payment = Payment::query()->find($id);
        abort_unless($payment !== null && ($payment->meta['staging_test'] ?? false) === true, 404);

        return $payment;
    }

    /** @return array<string, mixed> */
    private function view(Payment $payment): array
    {
        $uuid = $payment->provider_reference;

        return [
            'payment_id' => $payment->id,
            'status' => $payment->status,
            'failure_reason' => $payment->failure_reason,
            'amount' => (int) $payment->amount,
            'channel' => $payment->channel,
            'mode' => $payment->meta['staging_mode'] ?? null,
            'live' => (bool) ($payment->meta['staging_live'] ?? false),
            'dalipay_uuid' => $uuid,
            'dalipay_reference' => $payment->meta['dalipay_reference'] ?? null,
            'dalipay_status' => $payment->meta['dalipay_status'] ?? null,
            'dalipay_provider' => $payment->meta['dalipay_provider'] ?? null,
            'dalipay_create_seconds' => $payment->meta['dalipay_create_seconds'] ?? null,
            'external_id' => $payment->meta['external_id'] ?? null,
            'late_after' => $payment->meta['late_after'] ?? null,
            'fee_paid' => $payment->deliveryJob()->value('payment_status') === 'paid',
            'order_code' => $payment->order()->value('code'),
            'created_at' => $payment->created_at,
            'webhooks' => $uuid === null ? [] : PaymentWebhookEvent::query()->where('provider', 'dalipay')
                ->where('event_id', 'like', $uuid.':%')->orderBy('id')->get()
                ->map(fn (PaymentWebhookEvent $e) => ['event_id' => $e->event_id, 'result' => $e->result, 'received_at' => $e->created_at])
                ->all(),
        ];
    }
}
