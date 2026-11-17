<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A shop's return policy (docs/delivery/RETURNS.md). Defaults to 'none',
 * so every existing shop is unchanged until its owner picks 24h, 3d or 7d.
 * Additive and safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            if (! Schema::hasColumn('seller_profiles', 'return_policy')) {
                Schema::table('seller_profiles', fn (Blueprint $t) => $t->string('return_policy', 10)->default('none'));
            }
            if (! Schema::hasColumn('seller_profiles', 'return_notes')) {
                Schema::table('seller_profiles', fn (Blueprint $t) => $t->text('return_notes')->nullable());
            }
        } catch (Throwable $e) {
            $this->down();
            throw $e;
        }
    }

    public function down(): void
    {
        $present = array_values(array_filter(['return_policy', 'return_notes'], fn ($c) => Schema::hasColumn('seller_profiles', $c)));
        if ($present !== []) {
            Schema::table('seller_profiles', fn (Blueprint $t) => $t->dropColumn($present));
        }
    }
};
