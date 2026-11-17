<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The shop's return policy as the buyer saw it at checkout, copied onto
 * Bolt delivery orders only (docs/delivery/RETURNS.md). A shop changing
 * its policy later never changes an order already placed. Null on every
 * other order. Additive and safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'return_policy')) {
            Schema::table('orders', fn (Blueprint $t) => $t->string('return_policy', 10)->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'return_policy')) {
            Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('return_policy'));
        }
    }
};
