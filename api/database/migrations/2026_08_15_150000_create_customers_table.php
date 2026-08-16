<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Customer"/"Mteja" (CLAUDE.md Part 3) — a buyer following a seller's
 * shop, replacing the generic "follower" term everywhere in code and UI.
 * A real Eloquent model (surrogate `id`, not a bare pivot like
 * `favorites`) specifically so create/delete fire model events —
 * CustomerObserver keeps `seller_profiles.customer_count` denormalised
 * off this table the same way ReviewObserver keeps rating_avg/rating_count
 * off `reviews`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['buyer_id', 'seller_id']);
            $table->index(['seller_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
