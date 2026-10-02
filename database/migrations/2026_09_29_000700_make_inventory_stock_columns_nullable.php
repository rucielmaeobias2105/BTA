<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The item form is name, category, dates, quantity and unit, so the reorder
     * threshold and the supplier are no longer written by anything.
     *
     * They are made nullable rather than dropped: `reorder_threshold` is still
     * what the low-stock scope and the booking-time stock deduction compare
     * against, and keeping the column means a threshold set before this change
     * — or by the reports filter — keeps its meaning. A null threshold simply
     * reads as "no threshold set".
     */
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->decimal('reorder_threshold', 12, 2)->nullable()->default(null)->change();
            $table->string('supplier')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Rows written while the columns were nullable have nothing to put back
        // into a NOT NULL decimal, so the rollback fills the blanks with the
        // zeros and empty strings the column used to hold.
        DB::table('inventory_items')->whereNull('reorder_threshold')->update(['reorder_threshold' => 0]);
        DB::table('inventory_items')->whereNull('supplier')->update(['supplier' => '']);

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->decimal('reorder_threshold', 12, 2)->nullable(false)->default(0)->change();
            $table->string('supplier')->nullable(false)->change();
        });
    }
};
