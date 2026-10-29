<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery (docs/delivery/PLAN.md). Additive only: a driver is an ordinary
 * `users` row plus this profile. Status and similar columns are plain
 * strings validated in the model, not DB enums, so later states never need
 * an ALTER on the live MySQL database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            // pending | approved | rejected | suspended
            $table->string('status', 20)->default('pending');
            $table->timestamp('submitted_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->string('suspended_reason')->nullable();
            $table->timestamp('flagged_at')->nullable();
            $table->string('flag_reason')->nullable();

            // Disk-relative paths on the PRIVATE `local` disk, never public URLs.
            $table->string('photo')->nullable();
            $table->text('national_id_number')->nullable(); // encrypted cast
            $table->string('national_id_image')->nullable();
            $table->string('licence_number', 40)->nullable();
            $table->string('licence_image')->nullable();
            $table->date('licence_expires_on')->nullable();

            $table->boolean('is_online')->default(false);
            $table->decimal('last_lat', 10, 7)->nullable();
            $table->decimal('last_lng', 10, 7)->nullable();
            $table->timestamp('last_located_at')->nullable();

            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->unsignedInteger('jobs_completed')->default(0);
            $table->unsignedInteger('cancellations_count')->default(0);

            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_version', 20)->nullable();
            $table->timestamps();

            $table->index(['status', 'is_online', 'last_located_at']);
            $table->index(['last_lat', 'last_lng']);
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_profile_id')->constrained()->cascadeOnDelete();
            // boda_boda | bajaj | car | bicycle
            $table->string('type', 20);
            // Normalised upper-case, no spaces. Null only for bicycles.
            $table->string('plate_number', 16)->nullable()->unique();
            $table->string('make', 60)->nullable();
            $table->string('colour', 30)->nullable();
            $table->string('photo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('driver_profiles');
    }
};
