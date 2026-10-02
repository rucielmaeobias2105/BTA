<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categories gain an Active flag, and nothing else changes shape.
 *
 * The admin category list now carries an Active column with an inline toggle,
 * and the service form's Category dropdown only offers the active categories —
 * so a category an admin has retired stays on every existing service (nothing
 * is reassigned or deleted) but stops being offered to new ones.
 *
 * The column defaults to true so the existing rows are active after the
 * migration, which is what they all were before the flag existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('name')->index();
        });
    }

    public function down(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
