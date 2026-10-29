<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One delivery job per dispatch order. Pickup/drop-off and every money
 * value are SNAPSHOTS frozen when the seller accepts, so later edits to
 * the shop, the settings or the order never change what a job pays.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('driver_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            // See App\Models\DeliveryJob::STATUSES.
            $table->string('status', 30);

            $table->decimal('pickup_lat', 10, 7);
            $table->decimal('pickup_lng', 10, 7);
            $table->string('pickup_address')->nullable();
            $table->decimal('dropoff_lat', 10, 7);
            $table->decimal('dropoff_lng', 10, 7);
            $table->string('dropoff_address')->nullable();

            $table->decimal('distance_km', 6, 2);
            $table->string('distance_source', 20); // road | straight_line
            $table->unsignedInteger('base_fee');
            $table->unsignedInteger('per_km_fee');
            $table->unsignedInteger('fee');
            $table->decimal('commission_pct', 5, 2);
            $table->unsignedInteger('commission');
            $table->unsignedInteger('driver_earnings');

            // unpaid | paid | cash (fee to be collected in cash by the driver)
            $table->string('payment_status', 20)->default('unpaid');
            $table->timestamp('paid_at')->nullable();

            $table->text('delivery_pin');                 // encrypted cast
            $table->unsignedTinyInteger('pin_attempts')->default(0);
            $table->timestamp('pin_locked_at')->nullable();
            $table->string('proof_photo')->nullable();    // private disk path

            $table->unsignedTinyInteger('dispatch_wave')->default(0);
            $table->timestamp('wave_started_at')->nullable();
            $table->unsignedTinyInteger('call_attempts')->default(0);

            // buyer | seller | driver | admin | system
            $table->string('cancelled_by', 20)->nullable();
            $table->string('cancel_reason')->nullable();
            $table->string('failure_reason', 40)->nullable();
            $table->text('failure_note')->nullable();

            // Outcome money, set when the job reaches a terminal state.
            $table->unsignedInteger('earned_amount')->nullable();     // what the driver is owed
            $table->unsignedInteger('refund_amount')->nullable();     // what the buyer gets back
            $table->string('refund_status', 20)->nullable();          // pending | refunded
            $table->foreignId('payout_id')->nullable()->constrained('driver_payouts')->nullOnDelete();

            $table->timestamp('pending_driver_at')->nullable();
            $table->timestamp('driver_assigned_at')->nullable();
            $table->timestamp('arrived_at_shop_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('on_the_way_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('no_driver_found_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'wave_started_at']);
            $table->index(['driver_profile_id', 'status']);
        });

        Schema::create('delivery_job_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 20); // buyer | seller | driver | admin | system
            $table->string('type', 40);       // status | offer_wave | call_attempt | pin_failed | ...
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->string('note')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['delivery_job_id', 'id']);
        });

        Schema::create('delivery_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('wave');
            $table->decimal('distance_km', 6, 2);
            // offered | declined | expired | won | lost | withdrawn
            $table->string('status', 20)->default('offered');
            $table->timestamp('offered_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['delivery_job_id', 'driver_profile_id']);
            $table->index(['driver_profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_offers');
        Schema::dropIfExists('delivery_job_events');
        Schema::dropIfExists('delivery_jobs');
    }
};
