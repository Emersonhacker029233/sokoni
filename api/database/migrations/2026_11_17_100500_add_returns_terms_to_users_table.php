<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agreement to the Return Policy section of the Terms (Terms 1.2), kept
 * apart from users.terms_version on purpose: iOS 1.0 (9) asks the user to
 * agree again whenever terms_version differs from the "1.1" it ships with,
 * and it has no Return Policy text to show. Writing 1.2 there would loop
 * it. Only the new app records this, and only for users who see returns
 * (docs/delivery/RETURNS.md, "Terms"). Nullable; safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            if (! Schema::hasColumn('users', 'returns_terms_version')) {
                Schema::table('users', fn (Blueprint $t) => $t->string('returns_terms_version', 20)->nullable());
            }
            if (! Schema::hasColumn('users', 'returns_terms_accepted_at')) {
                Schema::table('users', fn (Blueprint $t) => $t->timestamp('returns_terms_accepted_at')->nullable());
            }
        } catch (Throwable $e) {
            $this->down();
            throw $e;
        }
    }

    public function down(): void
    {
        $present = array_values(array_filter(['returns_terms_version', 'returns_terms_accepted_at'], fn ($c) => Schema::hasColumn('users', $c)));
        if ($present !== []) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn($present));
        }
    }
};
