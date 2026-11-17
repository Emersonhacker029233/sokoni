<?php

namespace App\Services\Delivery;

use App\Models\DeliveryJob;
use App\Models\Payment;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\Payment\CollectionRequest;
use App\Services\Payment\GatewayCapabilities;
use App\Services\Payment\PaymentGateway;
use App\Services\Returns\ReturnMessages;
use App\Services\Returns\ReturnService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Collecting a Bolt delivery's fee (Sokoni 1.1 "Pay now").
 *
 * What is charged is decided here and only here: the fee frozen on the job
 * when the shop accepted (Bolt's estimate, upper bound). Item money is not
 * collected online: the buyer pays the shop directly, as before.
 *
 * The app can start an attempt but can never finish one. A payment becomes
 * `succeeded` only when the gateway, asked by this server, says so; then
 * the job is marked paid through DeliveryJobService::markFeePaid, and Bolt
 * is booked once the shop has marked the order ready. Status queries happen
 * when the buyer's order screen polls (throttled) and on every minute cron
 * tick (always).
 */
class DeliveryPaymentService
{
    public const TIMEOUT = 'timeout';

    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly DeliveryJobService $jobs,
        private readonly DeliveryMessages $messages,
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

        if (! $result->accepted()) {
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

    /** Asks the gateway once and applies the answer. Never trusts anything the app sent. */
    public function refresh(Payment $payment): Payment
    {
        if ($payment->status !== 'pending') {
            return $payment;
        }
        if ($payment->provider_reference === null) {
            // The prompt was never accepted by the gateway; nothing to ask about.
            if ($this->timedOut($payment)) {
                $this->fail($payment, self::TIMEOUT);
            }

            return $payment->fresh();
        }

        try {
            $status = $this->gateway->queryCollection($payment->provider_reference);
        } catch (Throwable $e) {
            Log::warning("[payments] status query failed for payment #{$payment->id}: ".$e->getMessage());

            return $payment;
        }

        $succeeded = false;
        $failedReason = null;
        DB::transaction(function () use ($payment, $status, &$succeeded, &$failedReason) {
            /** @var Payment $locked */
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($locked->status !== 'pending') {
                return;
            }

            if ($status->status === 'succeeded') {
                $locked->forceFill(['status' => 'succeeded', 'paid_at' => $status->paidAt ?? now()])->save();
                $succeeded = true;
            } elseif ($status->status === 'failed') {
                $failedReason = $status->failureReason ?? 'declined';
            } elseif ($this->timedOut($locked)) {
                $failedReason = self::TIMEOUT;
            }
        });

        if ($succeeded && $payment->return_request_id !== null) {
            // ReturnService depends on Bolt, not on payments; resolved here to keep the constructor graph acyclic.
            app(ReturnService::class)->markFeePaid($payment->returnRequest()->firstOrFail(), $payment->provider_reference);
        } elseif ($succeeded) {
            $this->jobs->markFeePaid($payment->deliveryJob()->firstOrFail(), $payment->provider_reference);
        } elseif ($failedReason !== null) {
            $this->fail($payment, $failedReason);
        }

        return $payment->fresh();
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
}
