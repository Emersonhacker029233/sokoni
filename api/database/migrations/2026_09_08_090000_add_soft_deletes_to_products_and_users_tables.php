<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C2 (tester feedback): admin product/user deletion must be recoverable,
 * not the permanent `DELETE` a bare `is_hidden`/`banned_at` flag already
 * covered — a real `deleted_at` column, via Eloquent's own SoftDeletes,
 * is what actually makes that possible (see Product/User models).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });

        // A deleted user's shop must be recoverable right alongside them —
        // restoring the user but leaving their shop permanently gone (or
        // vice versa) would be a broken half-state (see the cascading
        // delete in UsersTable).
        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
