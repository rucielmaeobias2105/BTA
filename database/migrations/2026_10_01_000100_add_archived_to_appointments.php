<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Archiving for settled appointments.
 *
 * A salon does not want its appointment queue to grow without bound, but it also
 * must not lose a booking's record — the revenue reports read these rows, and a
 * booking that was serviced is evidence it was serviced. So "finished with this
 * row" is expressed as a flag rather than a delete.
 *
 * Two columns rather than one, because both questions are worth being able to
 * answer later:
 *
 *   archived_at  — when it left the working list. This is what the daily task
 *                  keys off, and what the Archived view orders by.
 *   archived_by  — which admin moved it, if a person did. Null for the automatic
 *                  sweep, which is what distinguishes "an admin tidied up" from
 *                  "the 30-day rule fired". Nullable FK with nullOnDelete so
 *                  retiring an admin does not delete their tidying history.
 *
 * No index on `archived_at` alone: the working list filters on
 * `archived_at IS NULL`, which every engine can serve off the primary key scan
 * for the handful of rows a salon holds, and the archived view filters on
 * `IS NOT NULL` for the same reason. An index here would be written and never
 * chosen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('started_at');
            $table->foreignId('archived_by')->nullable()->constrained('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['archived_by']);
            $table->dropColumn(['archived_at', 'archived_by']);
        });
    }
};