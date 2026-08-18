<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Every admin action recorded with who, what, when and why" (CLAUDE.md
 * admin rebuild, Section 6) — a plain morph-target log rather than a
 * generic package, so `causer`/`subject`/`action`/`reason` line up exactly
 * with that wording and every resource's moderation actions can write to
 * it directly via App\Support\ActivityLogger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('causer_id')->constrained('users')->cascadeOnDelete();
            $table->string('action');
            $table->nullableMorphs('subject');
            $table->text('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            // nullableMorphs() above already indexes (subject_type, subject_id).
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
