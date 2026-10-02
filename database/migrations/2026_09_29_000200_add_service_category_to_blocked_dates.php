<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Category-scoped blocking.
 *
 * A block previously named a single `service_id`, which meant an admin had to
 * pick one service to close a whole category, and closing a category meant
 * repeating the block per service. `service_category` holds the category name
 * instead, matching how the service form already groups things.
 *
 * The old column stays: rows written before this migration still reference a
 * service, and `BookingAvailability::blockedRanges()` honours either scope.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blocked_dates', function ($table) {
            $table->string('service_category')->nullable()->after('service_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('blocked_dates', function ($table) {
            $table->dropIndex(['service_category']);
            $table->dropColumn('service_category');
        });
    }
};
