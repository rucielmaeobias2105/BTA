<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Collapses the admin role to a single `admin` value.
 *
 * The panel used to distinguish `super_admin`, `manager` and `staff`. The salon
 * runs it as one account, so the column is narrowed to the one value that
 * survives. MySQL rejects a value the enum does not list — and silently blanks
 * one it drops — so this widens the column first, rewrites the rows, then
 * narrows it again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('admins', 'role')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            $table->enum('role', ['super_admin', 'manager', 'staff', 'admin'])->default('staff')->change();
        });

        DB::table('admins')->update(['role' => 'admin']);

        Schema::table('admins', function (Blueprint $table) {
            // No `->index()` here: the column already carries one, and a
            // duplicate key name aborts the whole migration.
            $table->enum('role', ['admin'])->default('admin')->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('admins', 'role')) {
            return;
        }

        // Widening the enum back is lossless: every row already reads `admin`,
        // which is a valid member of the old three-value column.
        Schema::table('admins', function (Blueprint $table) {
            $table->enum('role', ['super_admin', 'manager', 'staff', 'admin'])->default('staff')->change();
        });
    }
};
