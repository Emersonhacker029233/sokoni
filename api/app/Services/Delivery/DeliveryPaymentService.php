<?php

namespace App\Services\Delivery;

use App\Models\DeliveryJob;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\CollectionRequest;
use App\Services\Payment\GatewayCapabilities;
use App\Services\Payment\PaymentGateway;
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
        if (! $this->onlinePaymentAvailable()) {
            throw DeliveryActionException::make('payments_unavailable', 503);
        }

        $existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $this->sameAttempt($existing, $job, $buyer);
        }

        try {
            $payment = DB::transaction(function () use ($job, $buyer, $channel, $msisdn, $idempotencyKey) {
                /** @var DeliveryJob $locked */
                $locked = DeliveryJob::query()->lockForUpdate()->findOrFail($job->id);
                if ($locked->status !== 'pending_payment' || $locked->payment_status !== 'unpaid') {
                    throw DeliveryActionException::make('not_payable', 409);
                }

                $pending = $locked->payments()->where('status', 'pending')->latest('id')->first();
                if ($pending !== null) {
                    if (! $this->timedOut($pending)) {
                        throw DeliveryActionException::make('payment_in_progress', 409);
                    }
                    $this->fail($pending, self::TIMEOUT, notify: false);
                }

                return Payment::query()->create([
                    'order_id' => $locked->order_id,
                    'delivery_job_id' => $locked->id,
                    'payer_user_id' => $buyer->id,
                    'purpose' => 'delivery_fee',
                    'provider' => $this->gateway->name(),
                    'channel' => $channel,
                    'msisdn' => $msisdn,
                    'amount' => $locked->fee,
                    'currency' => 'TZS',
                    'status' => 'pending',
                    'idempotency_key' => $idempotencyKey,
                ]);
            });
        } catch (QueryException $e) {
            // Two identical requests raced past the lookup; the unique key let one through.
            $existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing === null) {
                throw $e;
            }

            return $this->sameAttempt($existing, $job, $buyer);
        }

        $job->loadMissing('order');
        try {
            $result = $this->gateway->initiateCollection(new CollectionRequest(
                reference: $idempotencyKey,
                amount: (int) $payment->amount,
                currency: 'TZS',
                msisdn: $msisdn,
                channel: $channel,
                description: "Sokoni delivery {$job->order->code}",
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
        Payment::query()->where('status', 'pending')->whereNotNull('delivery_job_id')->orderBy('id')
            ->each(function (Payment $payment) use (&$changed) {
                if ($this->refresh($payment)->status !== 'pending') {
                    $changed++;
                }
            });

        // A crash between "payment succeeded" and "job marked paid" must not strand a paid job.
        Payment::query()->where('status', 'succeeded')->where('purpose', 'delivery_fee')
            ->whereHas('deliveryJob', fn ($q) => $q->where('payment_status', 'unpaid'))
            ->each(fn (Payment $payment) => $this->jobs->markFeePaid($payment->deliveryJob, $payment->provider_reference));

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

        if ($succeeded) {
            $this->jobs->markFeePaid($payment->deliveryJob()->firstOrFail(), $payment->provider_reference);
        } elseif ($failedReason !== null) {
            $this->fail($payment, $failedReason);
        }

        return $payment->fresh();
    }

    private function sameAttempt(Payment $existing, DeliveryJob $job, User $buyer): Payment
    {
        if ($existing->delivery_job_id !== $job->id || $existing->payer_user_id !== $buyer->id) {
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

        $job = $payment->deliveryJob()->first();
        if ($notify && $job !== null && $job->status === 'pending_payment') {
            $this->messages->paymentFailed($job);
        }
    }
}
