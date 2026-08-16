<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Offer"/"Punguzo" (CLAUDE.md Part 3) — a time-limited deal on a Listing.
 * `product_id` is NOT NULL and FKs to `products` (cascade — an Offer can't
 * outlive the Listing it discounts); `seller_id` is denormalised from the
 * product's own seller for cheap feed/"Offers row" queries without a join,
 * same reasoning as `products.seller_id` itself already being denormalised
 * rather than derived through category. Both FKs are non-nullable, so —
 * same schema guarantee as Update — there is no way for an Offer to exist
 * without referencing a real Listing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->enum('discount_type', ['percent', 'fixed_price']);
            // percent: 1-99 (whole percentage points). fixed_price: the new
            // sale price in TZS outright, not an amount subtracted.
            $table->decimal('discount_value', 12, 2);
            // The product's price at the moment the offer was created —
            // strikethrough display needs this to stay stable even if the
            // seller edits the product's price while the offer is live.
            $table->decimal('price_snapshot', 12, 2);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamps();

            $table->index(['seller_id', 'ends_at']);
            $table->index(['product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
