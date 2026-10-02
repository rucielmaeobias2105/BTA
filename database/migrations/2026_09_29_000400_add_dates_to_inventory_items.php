<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `date_in` is when the stock arrived, `expiry_date` when it goes out of use.
     *
     * Both are nullable so the rows written before the item form offered them
     * keep whatever they had — a null here means "not recorded", not "unknown
     * to the database", and nothing else in the app reads either column yet.
     */
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->date('date_in')->nullable()->after('unit');
            $table->date('expiry_date')->nullable()->after('date_in');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn(['date_in', 'expiry_date']);
        });
    }
};
