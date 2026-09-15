<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 3 (client feedback): noon.com-style category tiles need a real
 * photograph per category, admin-uploaded. Nullable — a category with no
 * image set falls back to the existing inline SVG icon on a brand-yellow
 * tile (CategoryForm/home.blade.php), so this is additive only; nothing
 * about the existing icon-based rendering breaks for a category the
 * client hasn't photographed yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('image')->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
};
