<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C3 (tester feedback): generic key/value product attributes — Cars'
 * make/model today, built generally enough that Real Estate's own
 * attributes (bedrooms, size, etc.) can reuse this exact table later
 * rather than needing a second one-off mechanism. Deliberately NOT a
 * third category level (Cars stays a single subcategory under
 * "Vehicles & Parts") — make/model are attributes OF a car listing, not
 * a place in the category tree.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('value');
            $table->timestamps();

            $table->unique(['product_id', 'key']);
            $table->index('key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attributes');
    }
};
