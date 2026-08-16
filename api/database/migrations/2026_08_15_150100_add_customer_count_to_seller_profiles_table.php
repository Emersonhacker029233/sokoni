<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table) {
            // Denormalised off `customers` by CustomerObserver, same
            // rationale/shape as rating_avg/rating_count off `reviews` —
            // excluded from SellerProfile's #[Fillable], set only via
            // forceFill() in the observer.
            $table->unsignedInteger('customer_count')->default(0)->after('rating_count');
        });
    }

    public function down(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->dropColumn('customer_count');
        });
    }
};
