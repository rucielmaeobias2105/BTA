<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a partial-day block.
 *
 * `time` is a nullable `H:i` string, mirroring the column the MCA Café calendar
 * uses for the same purpose. Null means the whole day is blocked, which is what
 * every block created before this migration does — so the column is additive and
 * no existing row needs rewriting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blocked_dates', function (Blueprint $table) {
            $table->string('time', 5)->nullable()->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('blocked_dates', function (Blueprint $table) {
            $table->dropColumn('time');
        });
    }
};
