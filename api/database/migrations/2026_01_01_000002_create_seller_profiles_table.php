<?php

use App\Services\Geo\DatabaseDialect;
use App\Services\Geo\ShopLocationSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('shop_name');
            $table->string('handle', 20)->unique();
            $table->text('bio')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('whatsapp')->nullable();
            // Source-of-truth coordinates. Portable across MySQL/SQLite, and
            // what the app/API actually reads and writes. On MySQL a
            // generated `shop_location` POINT column (below) is derived
            // from these for spatial indexing — see DECISIONS.md.
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('address')->nullable();
            $table->string('region')->nullable();
            $table->string('district')->nullable();
            $table->string('nida_number', 20)->nullable();
            $table->string('nida_image')->nullable();
            $table->string('licence_file')->nullable();
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->string('rejection_reason')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->boolean('show_whatsapp')->default(true);
            $table->timestamps();

            $table->index(['status']);
        });

        // MySQL-only: a STORED generated POINT column derived from lat/lng,
        // with a SPATIAL INDEX, powering `ST_Distance_Sphere` radius search
        // in ProductController@index. MySQL 8 permits spatial indexes on
        // nullable spatial columns (rows without coordinates simply aren't
        // indexed), so pending sellers who haven't set a location yet are
        // fine. SQLite (used for local dev/tests) has no spatial column
        // support, so this step is skipped there and distance search falls
        // back to a Haversine calculation over lat/lng — see
        // App\Services\Geo\DistanceQuery.
        //
        // MariaDB reports its driver as `mysql` too (same PDO driver name),
        // but rejects this exact generated-column syntax — see
        // ShopLocationSchema for the MariaDB-vs-MySQL split; on MariaDB this
        // returns no statements at all, and DistanceQuery falls back to
        // computing the distance inline from lat/lng instead of this column.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            foreach (ShopLocationSchema::statements(DatabaseDialect::isMariaDb()) as $statement) {
                DB::statement($statement);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_profiles');
    }
};
