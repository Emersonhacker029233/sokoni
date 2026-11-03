<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sokoni 1.1 dispatch checkout asks for the phone the rider should call at
 * the drop-off (it may not be the buyer's own). Nullable, so every existing
 * row and every Sokoni 1.0 order stays exactly as it is; when empty the
 * rider gets the buyer's account phone, as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_phone', 20)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('delivery_phone');
        });
    }
};
