<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Return requests and their photos (docs/delivery/RETURNS.md). New tables
 * only. The Bolt ride that brings an item back lives on the request itself
 * (delivery_jobs allows one job per order, and that one is the delivery).
 *
 * If it fails part-way, down() removes whatever it created, so a retry
 * starts clean. Safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            if (! Schema::hasTable('return_requests')) {
                Schema::create('return_requests', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
                    $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
                    $table->foreignId('shop_id')->constrained('seller_profiles')->cascadeOnDelete();
                    // changed_mind | wrong_item | damaged | not_as_described
                    $table->string('reason', 30);
                    $table->text('comment')->nullable();
                    // requested | accepted | rejected | disputed | return_in_transit | received | refunded | closed
                    $table->string('status', 20)->default('requested');
                    // Moderators can hide the buyer's comment and photos (Report > Hide content).
                    $table->boolean('is_hidden')->default(false);

                    // Who pays the Bolt ride back: buyer (changed mind) or seller (anything the shop got wrong).
                    $table->string('payer', 10);
                    $table->unsignedInteger('fee')->nullable();          // what the payer owes for the ride: Bolt's upper bound
                    $table->string('fee_status', 20)->nullable();        // unpaid | paid | owed_by_seller | waived
                    $table->string('currency', 3)->nullable();
                    $table->timestamp('paid_at')->nullable();

                    $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
                    $table->string('decided_role', 10)->nullable();      // seller | admin
                    $table->timestamp('decided_at')->nullable();
                    $table->text('decision_note')->nullable();           // the shop's reason (required to reject)
                    $table->timestamp('escalated_at')->nullable();
                    $table->string('escalation_reason', 30)->nullable(); // seller_no_response | seller_blocked_buyer
                    $table->timestamp('disputed_at')->nullable();
                    $table->text('dispute_comment')->nullable();
                    $table->text('resolution_note')->nullable();         // Sokoni support's decision
                    $table->timestamp('received_at')->nullable();
                    $table->timestamp('refunded_at')->nullable();
                    $table->timestamp('closed_at')->nullable();
                    $table->string('closed_reason', 30)->nullable();     // withdrawn | admin

                    // The Bolt ride back (customer -> shop).
                    $table->string('ride_status', 30)->nullable();       // pending_quote | quoted | unavailable | pending_driver | driver_assigned | at_pickup | on_the_way | delivered | no_driver_found | failed | cancelled
                    $table->string('ride_failure_reason', 40)->nullable();
                    $table->string('provider', 20)->nullable();
                    $table->string('provider_ref', 64)->nullable()->index();
                    $table->string('provider_state', 40)->nullable();
                    $table->json('provider_driver')->nullable();
                    $table->string('provider_category', 40)->nullable();
                    $table->decimal('estimate_min', 12, 2)->nullable();
                    $table->decimal('estimate_max', 12, 2)->nullable();
                    $table->decimal('final_cost', 12, 2)->nullable();
                    $table->decimal('cost_difference', 12, 2)->nullable();
                    $table->json('receipt')->nullable();
                    $table->timestamp('booked_at')->nullable();
                    $table->timestamp('picked_up_at')->nullable();
                    $table->timestamp('last_polled_at')->nullable();
                    $table->timestamp('receipt_checked_at')->nullable();
                    $table->timestamps();

                    $table->index(['status', 'created_at']);
                    $table->index(['shop_id', 'status']);
                });
            }

            if (! Schema::hasTable('return_request_photos')) {
                Schema::create('return_request_photos', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('return_request_id')->constrained()->cascadeOnDelete();
                    $table->string('path');                              // private `local` disk
                    $table->unsignedTinyInteger('sort')->default(0);
                    $table->timestamps();
                });
            }
        } catch (Throwable $e) {
            $this->down();
            throw $e;
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('return_request_photos');
        Schema::dropIfExists('return_requests');
    }
};
