<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Manual driver payouts (v1). Created before delivery_jobs, which point at it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('method', 30)->nullable();     // e.g. mpesa, airtel, bank, cash
            $table->string('reference', 100)->nullable(); // provider / transfer reference
            // pending | paid | void
            $table->string('status', 20)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['driver_profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_payouts');
    }
};
