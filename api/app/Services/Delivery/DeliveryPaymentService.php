<?php

namespace App\Services\Delivery;

use App\Models\DeliveryJob;
use App\Models\Payment;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\Payment\CollectionRequest;
use App\Services\Payment\CollectionStatus;
use App\Services\Payment\GatewayCapabilities;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\PaymentGatewayFactory;
use App\Services\Returns\ReturnMessages;
use App\Services\Returns\ReturnService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Collecting a Bolt delivery's fee, and a return's ride-back fee (Sokoni
 * 1.1 "Pay now").
 *
 * What is charged is decided here and only here: the fee frozen on the job
 * when the shop accepted (Bolt's estimate, upper bound). Item money is not
 * collected online: the buyer pays the shop directly, as before.
 *
 * The app can start an attempt but can never finish one. A payment becomes
 * `succeeded` only when the gateway, asked by this server, says so; then
 * the job is marked paid through DeliveryJobService::markFeePaid, and Bolt
 * is booked once the shop has marked the order ready. Status queries happen
 * when the buyer's order screen polls (throttled), on every minute cron
 * tick (always), and when a verified provider webhook says something
 * changed (DalipayWebhookHandler): the webhook only triggers the query.
 *
 * Money the provider confirms is never dropped: a success that arrives
 * after the attempt timed out here is still applied, and one that arrives
 * when the fee is already settled (an earlier attempt, or an admin's
 * "Record fee paid") is kept as `refund_pending`, owed back to the buyer.
 */
class DeliveryPaymentService
{
    public const TIMEOUT = 'timeout';

    /** Failure reasons after which the provider may still complete the payment. */
    private const MAY_STILL_SUCCEED = [self::TIMEOUT, 'gateway_error'];

    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly DeliveryJobService $jobs,
        private readonly DeliveryMessages $messages,
        private readonly ?PaymentGatewayFactory $gateways = null,
    ) {}

    public function capabilities(): GatewayCapabilities
    {
        return $this->gateway->capabilities();
    }

    /** Whether "Pay now" can work at all on this server. */
    public function onlinePaymentAvailable(): bool
    {
        return $this->gateway->capabilities()->collections;
    }

    /**
     * Sends a payment prompt to the buyer's phone. The same idempotency key
     * sent again returns the same attempt instead of a second prompt.
     */
    public function start(DeliveryJob $job, User $buyer, string $channel, string $msisdn, string $idempotencyKey): Payment
    {
        $job->loadMissing('order');

        return $this->open(
            $buyer, $channel, $msisdn, $idempotencyKey,
            owns: fn (Payment $p) => $p->delivery_job_id === $job->id,
            create: function () use ($job, $buyer, $channel, $msisdn, $idempotencyKey) {
                /** @var DeliveryJob $locked */
                $locked = DeliveryJob::query()->lockForUpdate()->findOrFail($job->id);
                if ($locked->status !== 'pending_payment' || $locked->payment_status !== 'unpaid') {
                    throw DeliveryActionException::make('not_payable', 409);
                }
                $this->expireOpenAttempt($locked->payments()->where('status', 'pending')->latest('id')->first());

                return $this->newAttempt($buyer, $channel, $msisdn, $idempotencyKey, [
                    'order_id' => $locked->order_id,
                    'delivery_job_id' => $locked->id,
                    'purpose' => 'delivery_fee',
                    'amount' => $locked->fee,
                ]);
            },
            description: "Sokoni delivery {$job->order->code}",
        );
    }

    /**
     * "Changed my mind" returns: the buyer pays the Bolt ride back the same
     * way (docs/delivery/RETURNS.md). The amount is the one frozen on the
     * return when the shop accepted.
     */
    public function startForReturn(ReturnRequest $return, User $buyer, string $channel, string $msisdn, string $idempotencyKey): Payment
    {
        $return->loadMissing('order');

        return $this->open(
            $buyer, $channel, $msisdn, $idempotencyKey,
            owns: fn (Payment $p) => $p->return_request_id === $return->id,
            create: function () use ($return, $buyer, $channel, $msisdn, $idempotencyKey) {
                /** @var ReturnRequest $locked */
                $locked = ReturnRequest::query()->lockForUpdate()->findOrFail($return->id);
                if ($locked->status !== 'accepted' || $locked->fee_status !== 'unpaid' || (int) $locked->fee <= 0) {
                    throw DeliveryActionException::make('not_payable', 409);
                }
                $this->expireOpenAttempt($locked->payments()->where('status', 'pending')->latest('id')->first());

                return $this->newAttempt($buyer, $channel, $msisdn, $idempotencyKey, [
                    'order_id' => $locked->order_id,
                    'return_request_id' => $locked->id,
                    'purpose' => 'return_fee',
                    'amount' => $locked->fee,
                ]);
            },
            description: "Sokoni return {$return->order->code}",
        );
    }

    public function refreshForReturn(ReturnRequest $return): void
    {
        $pending = $return->payments()->where('status', 'pending')->latest('id')->first();
        if ($pending !== null && Cache::add("payments:query:{$pending->id}", true, max(1, (int) config('payments.status_query_interval_seconds')))) {
            $this->refresh($pending);
        }
    }

    /**
     * One payment attempt: the same idempotency key returns the same
     * attempt, a second prompt waits until the first expired, and the
     * prompt is sent to the phone after the row is safely stored.
     *
     * @param  callable(Payment): bool  $owns  whether an attempt found by key belongs to this job/return
     * @param  callable(): Payment  $create  runs in a transaction; locks the payable and creates the row
     */
    private function open(User $buyer, string $channel, string $msisdn, string $idempotencyKey, callable $owns, callable $create, string $description): Payment
    {
        if (! $this->onlinePaymentAvailable()) {
            throw DeliveryActionException::make('payments_unavailable', 503);
        }

        $existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $this->sameAttempt($existing, $owns, $buyer);
        }

        try {
            $payment = DB::transaction($create);
        } catch (QueryException $e) {
            // Two identical requests raced past the lookup; the unique key let one through.
            $existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing === null) {
                throw $e;
            }

            return $this->sameAttempt($existing, $owns, $buyer);
        }

        try {
            $result = $this->gateway->initiateCollection(new CollectionRequest(
                reference: $idempotencyKey,
                amount: (int) $payment->amount,
                currency: 'TZS',
                msisdn: $msisdn,
                channel: $channel,
                description: $description,
            ));
        } catch (Throwable $e) {
            Log::error("[payments] initiate failed for payment #{$payment->id}: ".$e->getMessage());
            $this->fail($payment, 'gateway_error');

            return $payment->fresh();
        }

        $this->remember($payment, $result->meta);

        if ($result->unknown()) {
            // The prompt may be on the buyer's phone: keep the attempt open until it times out.
            Log::warning("[payments] payment #{$payment->id}: the provider didn't confirm the prompt; waiting");

            return $payment->fresh();
        }

        if (! $result->accepted()) {
            if ($result->providerReference !== null) {
                $payment->forceFill(['provider_reference' => $result->providerReference])->save();
            }
            $this->fail($payment, $result->failureReason ?? 'declined');

            return $payment->fresh();
        }

        $payment->forceFill(['provider_reference' => $result->providerReference])->save();

        return $payment->fresh();
    }

    private function expireOpenAttempt(?Payment $pending): void
    {
        if ($pending === null) {
            return;
        }
        if (! $this->timedOut($pending)) {
            throw DeliveryActionException::make('payment_in_progress', 409);
        }
        $this->fail($pending, self::TIMEOUT, notify: false);
    }

    private function newAttempt(User $buyer, string $channel, string $msisdn, string $idempotencyKey, array $attributes): Payment
    {
        return Payment::query()->create([
            ...$attributes,
            'payer_user_id' => $buyer->id,
            'provider' => $this->gateway->name(),
            'channel' => $channel,
            'msisdn' => $msisdn,
            'currency' => 'TZS',
            'status' => 'pending',
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    /**
     * From the buyer's polling: ask the gateway about this job's open
     * attempt, at most once per status_query_interval_seconds.
     */
    public function refreshForJob(DeliveryJob $job): void
    {
        $pending = $job->payments()->where('status', 'pending')->latest('id')->first();
        if ($pending === null) {
            return;
        }
        $interval = max(1, (int) config('payments.status_query_interval_seconds'));
        if (! Cache::add("payments:query:{$pending->id}", true, $interval)) {
            return;
        }
        $this->refresh($pending);
    }

    /**
     * From the minute cron: every open attempt is asked about, and any
     * confirmed payment whose job was somehow not marked paid is finished.
     *
     * @return int how many payments changed state
     */
    public function refreshAllPending(): int
    {
        $changed = 0;
        Payment::query()->where('status', 'pending')
            ->where(fn ($q) => $q->whereNotNull('delivery_job_id')->orWhereNotNull('return_request_id'))
            ->orderBy('id')
            ->each(function (Payment $payment) use (&$changed) {
                if ($this->refresh($payment)->status !== 'pending') {
                    $changed++;
                }
            });

        // A crash between "payment succeeded" and "marked paid" must not strand a paid delivery or return.
        Payment::query()->where('status', 'succeeded')->where('purpose', 'delivery_fee')
            ->whereHas('deliveryJob', fn ($q) => $q->where('payment_status', 'unpaid'))
            ->each(fn (Payment $payment) => $this->jobs->markFeePaid($payment->deliveryJob, $payment->provider_reference));
        Payment::query()->where('status', 'succeeded')->where('purpose', 'return_fee')
            ->whereHas('returnRequest', fn ($q) => $q->where('fee_status', 'unpaid'))
            ->each(fn (Payment $payment) => app(ReturnService::class)->markFeePaid($payment->returnRequest, $payment->provider_reference));

        return $changed;
    }

    /**
     * From the minute cron: Dalipay attempts that timed out here (or got no
     * answer when sent) are asked about again for `late_check_minutes`, so
     * an approval that came late, with its webhook lost, is still recorded.
     *
     * @return int how many late payments were found
     */
    public function recheckRecentFailures(): int
    {
        $found = 0;
        Payment::query()->where('status', 'failed')->where('provider', 'dalipay')
            ->whereNotNull('provider_reference')
            ->whereIn('failure_reason', self::MAY_STILL_SUCCEED)
            ->where('created_at', '>=', now()->subMinutes(max(1, (int) config('payments.dalipay.late_check_minutes', 60))))
            ->where(fn ($q) => $q->whereNotNull('delivery_job_id')->orWhereNotNull('return_request_id'))
            ->orderBy('id')
            ->each(function (Payment $payment) use (&$found) {
                if (($payment->meta['provider_final'] ?? null) !== null) {
                    return;
                }
                if (in_array($this->confirm($payment)->status, ['succeeded', 'refund_pending'], true)) {
                    $found++;
                }
            });

        return $found;
    }

    /**
     * A verified webhook, or the late check: ask the provider about this
     * payment and apply the answer, whatever its state here. Only ever
     * moves a payment towards "paid"; a confirmed payment never goes back.
     */
    public function confirm(Payment $payment): Payment
    {
        $payment = $payment->fresh();
        if ($payment->status === 'pending') {
            return $this->refresh($payment);
        }
        if ($payment->status !== 'failed' || $payment->provider_reference === null) {
            return $payment;
        }

        try {
            $status = $this->gatewayFor($payment)->queryCollection($payment->provider_reference);
        } catch (Throwable $e) {
            Log::warning("[payments] status query failed for payment #{$payment->id}: ".$e->getMessage());

            return $payment;
        }
        $this->remember($payment, $status->meta);

        if ($status->status === 'succeeded') {
            Log::notice("[payments] payment #{$payment->id} succeeded after it had failed here ({$payment->failure_reason})");
            $this->settle($payment, $status);
        } elseif ($status->status === 'failed') {
            $this->remember($payment, ['provider_final' => 'failed']);
        }

        return $payment->fresh();
    }

    /** Asks the gateway once and applies the answer. Never trusts anything the app sent. */
    public function refresh(Payment $payment): Payment
    {
        if ($payment->status !== 'pending') {
            return $payment;
        }
        if ($payment->provider_reference === null) {
            // The prompt was never confirmed by the gateway; nothing to ask about.
            if ($this->timedOut($payment)) {
                $this->fail($payment, self::TIMEOUT);
            }

            return $payment->fresh();
        }

        try {
            $status = $this->gatewayFor($payment)->queryCollection($payment->provider_reference);
        } catch (Throwable $e) {
            Log::warning("[payments] status query failed for payment #{$payment->id}: ".$e->getMessage());
            if ($this->timedOut($payment)) {
                // No answer and no time left: the buyer may try again; a late success is still caught.
                $this->fail($payment, self::TIMEOUT);
            }

            return $payment->fresh();
        }
        $this->remember($payment, $status->meta);

        if ($status->status === 'succeeded') {
            $this->settle($payment, $status);
        } elseif ($status->status === 'failed') {
            $this->fail($payment, $status->failureReason ?? 'declined');
        } elseif ($this->timedOut($payment)) {
            $this->fail($payment, self::TIMEOUT);
        }

        return $payment->fresh();
    }

    /**
     * The provider confirmed this payment. It pays the delivery fee or the
     * return ride fee, unless that is already settled, in which case the
     * money is owed back to the buyer (`refund_pending`). The payable is
     * locked before the payment, the same order as start(), so two
     * confirmations can't both pay one fee.
     */
    private function settle(Payment $payment, CollectionStatus $status): void
    {
        if ($status->amount !== null && $status->amount !== (int) $payment->amount) {
            Log::critical("[payments] payment #{$payment->id}: the provider confirmed {$status->amount} {$payment->currency} for a {$payment->amount} attempt; not applied, an admin must check it");
            $this->remember($payment, ['amount_mismatch' => $status->amount]);

            return;
        }

        $outcome = DB::transaction(function () use ($payment, $status) {
            $alreadySettled = false;
            if ($payment->delivery_job_id !== null) {
                $job = DeliveryJob::query()->lockForUpdate()->find($payment->delivery_job_id);
                $alreadySettled = $job !== null && $job->payment_status !== 'unpaid';
            } elseif ($payment->return_request_id !== null) {
                $return = ReturnRequest::query()->lockForUpdate()->find($payment->return_request_id);
                $alreadySettled = $return !== null && $return->fee_status !== 'unpaid';
            }

            /** @var Payment $locked */
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if (! in_array($locked->status, ['pending', 'failed'], true)) {
                return null; // confirmed already
            }

            $locked->forceFill([
                'status' => $alreadySettled ? 'refund_pending' : 'succeeded',
                'paid_at' => $status->paidAt ?? now(),
                'failure_reason' => null,
                'meta' => array_filter([
                    ...(array) $locked->meta,
                    'late_after' => $locked->status === 'failed' ? $locked->failure_reason : null,
                    'refund_reason' => $alreadySettled ? 'fee_already_settled' : null,
                ], fn ($v) => $v !== null),
            ])->save();

            return $alreadySettled ? 'refund' : 'apply';
        });

        if ($outcome === 'refund') {
            Log::warning("[payments] payment #{$payment->id} arrived when its fee was already settled; {$payment->amount} {$payment->currency} is owed back to the buyer");
        } elseif ($outcome === 'apply' && $payment->return_request_id !== null) {
            // ReturnService depends on Bolt, not on payments; resolved here to keep the constructor graph acyclic.
            app(ReturnService::class)->markFeePaid($payment->returnRequest()->firstOrFail(), $payment->provider_reference);
        } elseif ($outcome === 'apply') {
            $this->jobs->markFeePaid($payment->deliveryJob()->firstOrFail(), $payment->provider_reference);
        }
    }

    /** @param array<string, scalar|null> $meta provider details kept on the payment for admins */
    private function remember(Payment $payment, array $meta): void
    {
        $meta = array_filter($meta, fn ($v) => $v !== null && $v !== '');
        if ($meta === []) {
            return;
        }
        DB::transaction(function () use ($payment, $meta) {
            $locked = Payment::query()->lockForUpdate()->find($payment->id);
            $locked?->forceFill(['meta' => [...(array) $locked->meta, ...$meta]])->save();
        });
    }

    /** @param callable(Payment): bool $owns */
    private function sameAttempt(Payment $existing, callable $owns, User $buyer): Payment
    {
        if (! $owns($existing) || $existing->payer_user_id !== $buyer->id) {
            throw DeliveryActionException::make('idempotency_key_reused', 409);
        }

        return $this->refresh($existing);
    }

    private function timedOut(Payment $payment): bool
    {
        return $payment->created_at->lte(now()->subSeconds(max(10, (int) config('payments.attempt_timeout_seconds'))));
    }

    /** Marks an open attempt failed (once) and tells the buyer, unless the job has moved on. */
    private function fail(Payment $payment, string $reason, bool $notify = true): void
    {
        $updated = Payment::query()->whereKey($payment->id)->where('status', 'pending')
            ->update(['status' => 'failed', 'failure_reason' => $reason, 'updated_at' => now()]);
        if ($updated === 0) {
            return;
        }

        if (! $notify) {
            return;
        }
        $job = $payment->deliveryJob()->first();
        if ($job !== null && $job->status === 'pending_payment') {
            $this->messages->paymentFailed($job);
        }
        $return = $payment->returnRequest()->first();
        if ($return !== null && $return->fee_status === 'unpaid') {
            app(ReturnMessages::class)->toBuyer($return, 'payment_failed');
        }
    }

    /** The gateway this payment was made with, which may not be today's PAYMENT_PROVIDER. */
    private function gatewayFor(Payment $payment): PaymentGateway
    {
        if ($payment->provider === $this->gateway->name()) {
            return $this->gateway;
        }

        return ($this->gateways ?? app(PaymentGatewayFactory::class))->forProvider((string) $payment->provider);
    }
}
