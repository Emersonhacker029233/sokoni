<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Update"/"Taarifa" (CLAUDE.md Part 3) — a 24h shop notice, Instagram-story
 * style. `seller_id` is NOT NULL and FKs to `seller_profiles`, which only
 * exists for an actual registered shop — this is the schema guarantee (not
 * just request validation) that there is no way to post standalone personal
 * content: a row simply cannot exist without a seller behind it.
 * `product_id` is nullable — an Update may or may not be about one specific
 * Listing, but it always belongs to a shop either way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->enum('type', ['image', 'video']);
            $table->string('media_path');
            $table->string('thumb_path')->nullable();
            $table->text('caption')->nullable();
            $table->timestamps();
            // Set at creation (created_at + 24h) rather than computed on
            // read, so the scheduled cleanup command (updates:delete-expired)
            // can filter with a plain indexed comparison.
            $table->timestamp('expires_at');

            $table->index(['seller_id', 'expires_at']);
            $table->index(['expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('updates');
    }
};
