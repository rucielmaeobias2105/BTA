<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categories gain a photo of their own.
 *
 * The Services page shows one picture per category beside its price list, and
 * until now there was nowhere to put one: `service_categories` had no image
 * column at all, so the picture came from whichever service in the category had
 * an uploaded photo, then from `config/salon.php`, then from a shared default —
 * three of which mean the salon cannot change the picture for a category
 * without a developer.
 *
 * Nullable, and every existing row stays null. Those categories keep resolving
 * exactly as they did, through the same fallback chain, so the migration cannot
 * change a rendered page for anybody.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('service_categories', 'photo')) {
            Schema::table('service_categories', function (Blueprint $table) {
                // A path on the `public` disk, relative to its root — the same
                // shape as `services.photo_path` and `technicians.photo_path`,
                // and served as `/storage/...` by the symlink Laravel makes.
                $table->string('photo')->nullable()->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('service_categories', 'photo')) {
            Schema::table('service_categories', function (Blueprint $table) {
                $table->dropColumn('photo');
            });
        }
    }
};