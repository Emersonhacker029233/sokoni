<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('seller_profiles')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price');
            $table->string('currency', 3)->default('TZS');
            $table->unsignedInteger('stock')->default(1);
            $table->enum('condition', ['new', 'used'])->default('new');
            $table->boolean('is_active')->default(true);
            // Hidden until the seller is verified (see SellerProfileObserver)
            // or an admin/report action hides it independently of that.
            $table->boolean('is_hidden')->default(false);
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();

            $table->index(['seller_id', 'is_active', 'is_hidden']);
            $table->index(['category_id']);

            // SQLite (local dev/tests) has no fulltext index support; the
            // `q` search param falls back to LIKE there (see Product::scopeSearch).
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->fullText(['title', 'description']);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
