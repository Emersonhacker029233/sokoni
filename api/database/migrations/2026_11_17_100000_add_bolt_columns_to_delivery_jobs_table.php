<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bolt Ride Booker on delivery jobs (docs/delivery/BOLT.md). Additive only:
 * every column is nullable or has a default, so older rows (own-driver
 * jobs on staging) keep reading exactly as before, with provider = null.
 *
 * The own-driver columns (driver_profile_id, delivery_pin, dispatch_wave,
 * commission ...) stay. Bolt jobs write 0 or '' into the NOT NULL ones and
 * never read them. Nothing is dropped or renamed.
 *
 * Safe to run again: each column and index is added only if missing. If
 * it fails part-way, down() removes whatever it added, so a retry starts
 * clean (same pattern as the moderation release).
 */
return new class extends Migration
{
    private const INDEXES = [
        'delivery_jobs_provider_status_index' => ['provider', 'status'],
        'delivery_jobs_provider_ref_index' => ['provider_ref'],
    ];

    public function up(): void
    {
        try {
            $this->addColumns([
                // null = an own-driver job from before Bolt; 'bolt' for every new one.
                'provider' => fn (Blueprint $t) => $t->string('provider', 20)->nullable()->after('status'),
                'provider_ref' => fn (Blueprint $t) => $t->string('provider_ref', 64)->nullable()->after('provider'),        // Bolt ride_id
                'provider_state' => fn (Blueprint $t) => $t->string('provider_state', 40)->nullable()->after('provider_ref'), // Bolt's ride state, as received
                // name, phone, plate, model, photo_url, tracking_url, eta_seconds
                'provider_driver' => fn (Blueprint $t) => $t->json('provider_driver')->nullable()->after('provider_state'),
                'provider_category' => fn (Blueprint $t) => $t->string('provider_category', 40)->nullable()->after('provider_driver'),
                // Bolt's estimate when the shop accepted; the customer pays ceil(estimate_max) as `fee`.
                'estimate_min' => fn (Blueprint $t) => $t->decimal('estimate_min', 12, 2)->nullable()->after('fee'),
                'estimate_max' => fn (Blueprint $t) => $t->decimal('estimate_max', 12, 2)->nullable()->after('estimate_min'),
                'currency' => fn (Blueprint $t) => $t->string('currency', 3)->nullable()->after('estimate_max'),
                // From Bolt's receipt after COMPLETED; difference = fee - final_cost (negative = Sokoni paid more).
                'final_cost' => fn (Blueprint $t) => $t->decimal('final_cost', 12, 2)->nullable()->after('currency'),
                'cost_difference' => fn (Blueprint $t) => $t->decimal('cost_difference', 12, 2)->nullable()->after('final_cost'),
                'receipt' => fn (Blueprint $t) => $t->json('receipt')->nullable()->after('cost_difference'),
                'booked_at' => fn (Blueprint $t) => $t->timestamp('booked_at')->nullable(),
                'last_polled_at' => fn (Blueprint $t) => $t->timestamp('last_polled_at')->nullable(),
                'receipt_checked_at' => fn (Blueprint $t) => $t->timestamp('receipt_checked_at')->nullable(),
                'booking_attempts' => fn (Blueprint $t) => $t->unsignedTinyInteger('booking_attempts')->default(0),
                'next_booking_at' => fn (Blueprint $t) => $t->timestamp('next_booking_at')->nullable(),
                'pending_payment_at' => fn (Blueprint $t) => $t->timestamp('pending_payment_at')->nullable(),
                'awaiting_ready_at' => fn (Blueprint $t) => $t->timestamp('awaiting_ready_at')->nullable(),
            ]);

            foreach (self::INDEXES as $name => $columns) {
                if (! Schema::hasIndex('delivery_jobs', $name)) {
                    Schema::table('delivery_jobs', fn (Blueprint $t) => $t->index($columns, $name));
                }
            }
        } catch (Throwable $e) {
            $this->down();
            throw $e;
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::INDEXES) as $name) {
            if (Schema::hasIndex('delivery_jobs', $name)) {
                Schema::table('delivery_jobs', fn (Blueprint $t) => $t->dropIndex($name));
            }
        }

        $present = array_values(array_filter($this->columnNames(), fn ($c) => Schema::hasColumn('delivery_jobs', $c)));
        if ($present !== []) {
            Schema::table('delivery_jobs', fn (Blueprint $t) => $t->dropColumn($present));
        }
    }

    /** @param array<string, Closure(Blueprint): mixed> $columns */
    private function addColumns(array $columns): void
    {
        $missing = array_filter($columns, fn ($add, $name) => ! Schema::hasColumn('delivery_jobs', $name), ARRAY_FILTER_USE_BOTH);
        if ($missing === []) {
            return;
        }

        Schema::table('delivery_jobs', function (Blueprint $table) use ($missing) {
            foreach ($missing as $add) {
                $add($table);
            }
        });
    }

    /** @return list<string> */
    private function columnNames(): array
    {
        return [
            'provider', 'provider_ref', 'provider_state', 'provider_driver', 'provider_category',
            'estimate_min', 'estimate_max', 'currency', 'final_cost', 'cost_difference', 'receipt',
            'booked_at', 'last_polled_at', 'receipt_checked_at', 'booking_attempts', 'next_booking_at',
            'pending_payment_at', 'awaiting_ready_at',
        ];
    }
};
