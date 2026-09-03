<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Same reasoning as bio_sw on seller_profiles — see that migration's docblock. Product *titles* aren't split (brand/model names like "iPhone 12 Pro" or "Tecno Spark" don't translate — a Tanzanian buyer says them the same way in either language), only descriptions. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('description_sw')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('description_sw');
        });
    }
};
