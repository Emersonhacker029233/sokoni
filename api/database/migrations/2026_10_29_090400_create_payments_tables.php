<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Online collection of DELIVERY FEES only (v1: item money goes from buyer to
 * seller directly, never through Sokoni). Tables land now so the data model
 * is complete; the gateway itself is wired in phase 5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_job_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('purpose', 30)->default('delivery_fee');
            $table->string('provider', 30);              // fake | clickpesa | ...
            $table->string('channel', 30)->nullable();   // mpesa | airtel | mixx | halopesa | cash
            $table->text('msisdn')->nullable();          // encrypted cast
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('TZS');
            // pending | succeeded | failed | cancelled | refunded
            $table->string('status', 20)->default('pending');
            $table->string('idempotency_key', 100)->unique();
            $table->string('provider_reference', 120)->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_reference']);
            $table->index(['order_id', 'status']);
        });

        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->string('event_id', 150);
            $table->json('payload');
            $table->boolean('signature_valid')->default(false);
            $table->string('result', 40)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payments');
    }
};
