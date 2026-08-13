<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            // Unique: one review per completed order — this plus the
            // completed-order check in StoreReviewRequest is the entire
            // defence against fake reviews (see CLAUDE.md, feature 2).
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->text('reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();

            $table->index(['seller_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
