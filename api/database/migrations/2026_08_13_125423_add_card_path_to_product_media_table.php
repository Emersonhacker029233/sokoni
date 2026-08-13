<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_media', function (Blueprint $table) {
            // Mid-size variant (CLAUDE.md feature 7: "Generate 3 image sizes
            // on upload (thumb 300, card 800, full 1600)"). `thumb_path` is
            // the 300px list/grid thumbnail, `path` is the 1600px full
            // detail-carousel image, and this is the 800px in-between size
            // for contexts that don't need either extreme. Null for video
            // rows, which only get a thumb-sized poster frame.
            $table->string('card_path')->nullable()->after('thumb_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_media', function (Blueprint $table) {
            $table->dropColumn('card_path');
        });
    }
};
