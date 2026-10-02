<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Appointments remember whether the salon has looked at them.
 *
 * The sidebar badge and the tab title's "(n)" were both "how many bookings are
 * in Pending", which cannot be cleared by looking at them: an admin who opened
 * the Appointments page, read every row on it and closed the tab still saw the
 * number, because the count was a status rather than an acknowledgement. The
 * only thing that used to clear it was deciding each booking individually, so a
 * salon that wanted to see the list without committing to approve or decline had
 * no way to do it.
 *
 * This column separates the two ideas. `status` is where a booking *is* in the
 * salon's process; `admin_seen_at` is whether anyone has *looked*. A booking can
 * be Pending and seen, or Confirmed and unseen, and the badge counts only the
 * second.
 *
 * Nullable and deliberately left null on every existing row — see below.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('appointments', 'admin_seen_at')) {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('admin_seen_at')->nullable()->after('status')->index();

            // Deliberately NOT backfilled. Setting every existing row to `now()`
            // would declare that the salon had already seen bookings that
            // predate this column by weeks, and the badge would start life at
            // zero with nobody having looked at any of them.
            //
            // Leaving them null instead means they read as unseen, so the badge
            // starts at the number of bookings actually outstanding, shows that
            // number on the first load, and clears the first time an admin opens
            // the page — which is exactly the behaviour being fixed, arriving at
            // it honestly rather than by backdating an acknowledgement.
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('appointments', 'admin_seen_at')) {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('admin_seen_at');
        });
    }
};
