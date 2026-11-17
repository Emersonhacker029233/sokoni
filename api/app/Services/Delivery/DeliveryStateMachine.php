<?php

namespace App\Services\Delivery;

use App\Models\DeliveryJob;
use App\Models\DeliveryJobEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The single way a delivery job changes status. Each transition:
 *  1. re-reads the job under a row lock and checks the edge is legal,
 *  2. writes the status, its timestamp and any extra columns,
 *  3. appends a delivery_job_events row (the timeline both sides see),
 *  4. after commit: sends the pushes and inbox entries.
 *
 * Actor roles on the timeline: buyer, seller, admin, system, and
 * `provider` for changes that came from Bolt.
 */
class DeliveryStateMachine
{
    private const TIMESTAMP_COLUMNS = [
        'pending_payment', 'awaiting_ready', 'pending_driver', 'driver_assigned', 'arrived_at_shop', 'picked_up',
        'on_the_way', 'arrived', 'delivered', 'cancelled', 'failed', 'no_driver_found',
    ];

    public function __construct(private readonly DeliveryMessages $messages) {}

    /**
     * @param  array<string, mixed>  $attributes  extra columns to write in the same update
     * @param  (callable(DeliveryJob): void)|null  $inTransaction  extra work that must commit atomically with the change
     * @param  list<string>  $onlyFrom  when set, the locked CURRENT status must also be one of these
     *
     * @throws DeliveryActionException when the edge is not legal from the job's CURRENT status
     */
    public function transition(
        DeliveryJob $job,
        string $to,
        ?User $actor,
        string $actorRole,
        array $attributes = [],
        ?string $note = null,
        array $meta = [],
        ?callable $inTransaction = null,
        array $onlyFrom = [],
    ): DeliveryJob {
        [$fresh, $from] = DB::transaction(function () use ($job, $to, $actor, $actorRole, $attributes, $note, $meta, $inTransaction, $onlyFrom) {
            /** @var DeliveryJob $fresh */
            $fresh = DeliveryJob::query()->lockForUpdate()->findOrFail($job->id);
            $from = $fresh->status;

            if (! $fresh->canTransitionTo($to) || ($onlyFrom !== [] && ! in_array($from, $onlyFrom, true))) {
                throw DeliveryActionException::make('invalid_transition', 409, ['from' => $from, 'to' => $to]);
            }

            $fresh->forceFill([
                'status' => $to,
                ...(in_array($to, self::TIMESTAMP_COLUMNS, true) ? ["{$to}_at" => now()] : []),
                ...$attributes,
            ])->save();

            $this->record($fresh, $actor, $actorRole, 'status', $from, $to, $note, $meta);

            if ($inTransaction !== null) {
                $inTransaction($fresh);
            }

            return [$fresh, $from];
        });

        $this->messages->statusChanged($fresh, $from, $to);

        return $fresh;
    }

    public function record(
        DeliveryJob $job,
        ?User $actor,
        string $actorRole,
        string $type,
        ?string $from = null,
        ?string $to = null,
        ?string $note = null,
        array $meta = [],
    ): DeliveryJobEvent {
        return DeliveryJobEvent::query()->create([
            'delivery_job_id' => $job->id,
            'actor_user_id' => $actor?->id,
            'actor_role' => $actorRole,
            'type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note === null ? null : mb_substr($note, 0, 255),
            'meta' => $meta === [] ? null : $meta,
        ]);
    }
}
