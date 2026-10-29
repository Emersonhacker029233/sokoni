<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Server-side breadcrumbs, sampled at most every ~30 s while a driver is on
 * a job, kept only for dispute handling. Purged after
 * `delivery_location_retention_hours` (72 h) by delivery:purge-locations.
 * Live tracking itself never touches this table (Firebase RTDB).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_job_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->unsignedSmallInteger('heading')->nullable();
            $table->decimal('speed', 6, 2)->nullable();    // m/s
            $table->decimal('accuracy', 7, 2)->nullable(); // metres
            $table->timestamp('recorded_at');

            $table->index('recorded_at');
            $table->index(['delivery_job_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_locations');
    }
};
