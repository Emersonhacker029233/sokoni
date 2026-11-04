<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an admin hide a reported shop, chat message, showcase or update the
 * same way products, comments and reviews can already be hidden (Apple
 * Guideline 1.2: "remove the content"). Same column, same meaning as the
 * existing `products.is_hidden` / `comments.is_hidden` / `reviews.is_hidden`.
 *
 * Additive only: a boolean defaulting to false, so every existing row
 * stays exactly as visible as it is today. No index (it is only ever read
 * alongside an already-indexed filter), so nothing counts against the
 * 1000-byte key limit.
 *
 * Rollback: `php artisan migrate:rollback` while this batch is the newest,
 * or by hand: `ALTER TABLE seller_profiles DROP COLUMN is_hidden;` and the
 * same for `messages`, `showcases` and `updates`. Anything hidden becomes
 * visible again.
 */
return new class extends Migration
{
    private const TABLES = ['seller_profiles', 'messages', 'showcases', 'updates'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            if (Schema::hasColumn($name, 'is_hidden')) {
                continue;
            }
            Schema::table($name, function (Blueprint $table) {
                $table->boolean('is_hidden')->default(false);
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            if (! Schema::hasColumn($name, 'is_hidden')) {
                continue;
            }
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('is_hidden');
            });
        }
    }
};
