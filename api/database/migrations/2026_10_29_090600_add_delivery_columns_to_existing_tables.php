<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ONLY changes this feature makes to pre-existing tables: two new
 * nullable columns. Every existing row stays null, which means "exactly
 * the behaviour Sokoni 1.0 already has".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // null = legacy flow (flat fee, seller handles delivery).
            // 'dispatch' = Sokoni Driver delivery (docs/delivery/PLAN.md).
            $table->string('fulfilment', 20)->nullable()->after('delivery_method');
        });

        Schema::table('devices', function (Blueprint $table) {
            // null = the Sokoni customer app (every existing row).
            // 'driver' = the Sokoni Driver app. Lets delivery pushes reach
            // the right app when one phone has both installed.
            $table->string('app', 20)->nullable()->after('platform');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('app');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('fulfilment');
        });
    }
};
