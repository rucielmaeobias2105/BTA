<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the `blocked_dates` table.
 *
 * The Calendar & Blocked Dates feature is gone: no screen creates a block, no
 * booking rule reads one, and no screen displays them. The table went last, after
 * all of that, so the order of operations is the one that would have failed
 * loudly rather than silently.
 *
 * `down()` recreates the table but not its rows. Restoring the schema without the
 * data is the honest half of a reversible migration here — inventing demo
 * closures would be worse than an empty table, and the real ones were only ever
 * ever closures the salon had entered by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('blocked_dates');
    }

    public function down(): void
    {
        Schema::create('blocked_dates', function (Blueprint $table) {
            $table->id();
            $table->date('start_date');
            $table->date('end_date');
            $table->time('time')->nullable();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_category', 120)->nullable();
            $table->string('reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['start_date', 'end_date']);
        });
    }
};