<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Two-way ratings after a delivered job: buyer rates driver, driver rates buyer. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rater_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ratee_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('direction', 20); // buyer_to_driver | driver_to_buyer
            $table->unsignedTinyInteger('rating');
            $table->string('comment', 500)->nullable();
            $table->timestamps();

            $table->unique(['delivery_job_id', 'direction']);
            $table->index('ratee_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_ratings');
    }
};
