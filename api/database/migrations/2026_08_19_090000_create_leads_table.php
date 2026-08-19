<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Call (reveals the number on click, and logs the reveal as a lead)"
 * (CLAUDE.md website Section 3) — a real, queryable record of buyer
 * interest a seller/admin could later report on, not just a counter.
 * `user_id` is nullable: revealing a phone number, unlike messaging, is
 * one of the actions this site keeps open to anonymous browsing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['call'])->default('call');
            $table->timestamps();

            $table->index(['seller_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
