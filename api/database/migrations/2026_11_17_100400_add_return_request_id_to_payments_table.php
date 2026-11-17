<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The buyer pays a "changed my mind" return ride through the same Pay now
 * flow as the delivery fee (docs/delivery/RETURNS.md): such a payment row
 * has purpose 'return_fee' and points at its return request. Nullable, so
 * every delivery-fee payment is unchanged. Safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('payments', 'return_request_id')) {
            return;
        }

        try {
            Schema::table('payments', function (Blueprint $table) {
                $table->foreignId('return_request_id')->nullable()->after('delivery_job_id')->constrained()->nullOnDelete();
            });
        } catch (Throwable $e) {
            $this->down();
            throw $e;
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('payments', 'return_request_id')) {
            return;
        }
        Schema::table('payments', function (Blueprint $table) {
            if (collect(Schema::getForeignKeys('payments'))->contains(fn ($fk) => $fk['columns'] === ['return_request_id'])) {
                $table->dropForeign(['return_request_id']);
            }
        });
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('return_request_id'));
    }
};
