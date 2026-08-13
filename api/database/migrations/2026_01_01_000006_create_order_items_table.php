<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // Nullable + nullOnDelete: a product can be deleted later without
            // orphaning historical order line items, which keep their own
            // title/price snapshot regardless.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title_snapshot');
            $table->unsignedBigInteger('price_snapshot');
            $table->unsignedInteger('qty');
            $table->timestamps();

            $table->index(['order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
