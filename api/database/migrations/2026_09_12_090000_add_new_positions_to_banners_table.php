<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part B (client feedback): noon.com-pattern ad inventory — three new
 * slots alongside the four the original banner system shipped with.
 * `position` stays an enum (not a free-text placement) for the same
 * reason the original migration gives: every template only ever asks
 * "give me the active banner(s) for this exact position," a plain WHERE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->enum('position', [
                'home_hero',
                'home_mid',
                'category_top',
                'sidebar',
                'search_background',
                'category_strip_side',
                'near_you_side',
            ])->change();
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->enum('position', ['home_hero', 'home_mid', 'category_top', 'sidebar'])->change();
        });
    }
};
