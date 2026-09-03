<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Managed banner inventory — the website's monetisation surface (Part 3
 * design pass). `position` is a fixed slot name (home_hero, home_mid,
 * category_top, sidebar), not a free-text placement — the template at
 * each slot only ever asks "give me the active banner(s) for this exact
 * position," so an enum keeps that query a plain WHERE rather than needing
 * a join to some other placement-registry concept that doesn't otherwise
 * exist in this schema. `starts_at`/`ends_at` are both nullable — an
 * always-on banner (while `is_active`) doesn't need a schedule, only a
 * timed campaign does. `impressions`/`clicks` are plain incrementing
 * counters (server-side impression count on render, click count via the
 * redirect route) — good enough for "how did this banner perform," not a
 * full analytics pipeline this app has no other infrastructure for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('image_path');
            $table->string('link_url');
            $table->enum('position', ['home_hero', 'home_mid', 'category_top', 'sidebar']);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('impressions_count')->default(0);
            $table->unsignedBigInteger('clicks_count')->default(0);
            $table->timestamps();

            $table->index(['position', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
