<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the `reviews` table — the rating feature is gone from both the customer
 * site and the admin panel, so the table behind it has no writers and no readers.
 *
 * The `hasTable()` guard is not defensive noise: the create migration no longer
 * builds this table, so on a fresh install it is never there to drop, while an
 * existing install still has it. One migration therefore covers both, instead of
 * needing a "create it then drop it" pair that would leave a pointless table in
 * a new database's history.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reviews')) {
            Schema::drop('reviews');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('reviews')) {
            return;
        }

        // Not restored on rollback. The feature that used these rows is gone, so
        // rebuilding an empty table would only leave something for a future
        // developer to mistake for live data. The original column set is kept
        // here as the record of what it held.
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('message');
            $table->string('customer_name');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['rating', 'created_at']);
        });
    }
};
