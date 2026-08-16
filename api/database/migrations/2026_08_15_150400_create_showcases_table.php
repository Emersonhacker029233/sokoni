<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Showcase"/"Onyesho" (CLAUDE.md Part 3) — a vertical full-screen product
 * video, Reels/TikTok-style. `product_id` is NOT NULL (a Showcase is
 * definitionally a product video) and `seller_id` is denormalised from it
 * for the same feed-query reason as `offers.seller_id`. Both non-nullable,
 * same schema-level "no standalone content" guarantee as Update/Offer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('showcases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('video_path');
            $table->string('thumb_path');
            $table->text('caption')->nullable();
            $table->unsignedSmallInteger('duration');
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();

            $table->index(['seller_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('showcases');
    }
};
