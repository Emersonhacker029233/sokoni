<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 4 (client feedback): a dedicated "In Focus" advertising band,
 * noon.com-style — two to four poster slots in a row. `position` stays an
 * enum for the same reason every prior widening of this column gives:
 * every template only ever asks "give me the active banner(s) for this
 * exact position," a plain WHERE.
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
                'in_focus',
            ])->change();
        });
    }

    public function down(): void
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
};
