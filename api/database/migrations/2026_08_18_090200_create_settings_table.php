<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Editable platform values... read from config with database overrides"
 * (CLAUDE.md admin rebuild, Section 6). A plain key/value table — only a
 * handful of settings exist, and every one is a scalar, so this doesn't
 * need a typed schema per setting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
