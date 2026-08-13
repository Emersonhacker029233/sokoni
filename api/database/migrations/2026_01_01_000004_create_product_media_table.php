<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['image', 'video']);
            $table->string('path');
            $table->string('thumb_path')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_media');
    }
};
