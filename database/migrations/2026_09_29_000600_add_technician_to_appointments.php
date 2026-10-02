<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which technician the customer picked, on the appointment itself.
     *
     * Nullable with `nullOnDelete` on purpose. Every appointment that already
     * exists has no technician and keeps working, "No preference" is a real
     * answer the booking form offers, and retiring a technician must not
     * cascade a delete across the booking history — it nulls the link and the
     * row reads "No preference" again.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('technician_id')
                ->nullable()
                ->after('preferred_stylist_id')
                ->constrained('technicians')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('technician_id');
        });
    }
};
