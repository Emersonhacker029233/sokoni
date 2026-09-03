<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sms_blasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users');
            $table->string('audience'); // verified_sellers|buyers|all
            $table->boolean('consent_only')->default(false);
            $table->text('message');
            $table->unsignedInteger('total_count');
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->string('status')->default('pending'); // pending|processing|completed
            $table->timestamps();
        });

        Schema::create('sms_blast_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sms_blast_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('phone');
            $table->string('status')->default('pending'); // pending|sent|failed
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['sms_blast_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_blast_recipients');
        Schema::dropIfExists('sms_blasts');
    }
};
