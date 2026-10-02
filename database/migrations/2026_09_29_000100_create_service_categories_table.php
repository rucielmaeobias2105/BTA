<?php

use App\Models\ServiceCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Categories become real rows so they can carry a colour.
 *
 * Until now `services.category` was a free-text string, which cannot hold the
 * per-category colour the admin calendar legend and its chips read. This lifts
 * the existing strings into a `service_categories` table, backfills each with
 * the salon palette, and points `services` at the new row.
 *
 * Existing `services.category` values are left in place. The app resolves a
 * service's category by name in several places, and a partial second migration
 * away from the string would be a much larger change than this feature needs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_categories', function ($table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color', 7)->default('#7A241B');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('sort_order');
        });

        Schema::table('services', function ($table) {
            $table->foreignId('service_category_id')
                ->nullable()
                ->after('category')
                ->constrained('service_categories')
                ->nullOnDelete();
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('services', function ($table) {
            $table->dropConstrainedForeignId('service_category_id');
        });

        Schema::dropIfExists('service_categories');
    }

    /**
     * Copy every distinct category string into the table, in the order the
     * categories were first seen so the admin list matches the existing
     * catalogue rather than an alphabetical guess.
     */
    private function backfill(): void
    {
        $names = DB::table('services')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('id')
            ->pluck('category')
            ->filter()
            ->map(fn ($name) => trim($name))
            ->unique()
            ->values();

        $used = [];
        $order = 0;

        foreach ($names as $name) {
            // Look the name up on every plausible palette key, so the salon's
            // own wording ("Bleaching", "Spa Services") still takes the colour
            // the price list gives it instead of consuming a palette slot.
            $color = ServiceCategory::paletteColorFor($name)
                ?? ServiceCategory::nextColorFromPalette($used);

            $used[] = $color;

            $category = ServiceCategory::create([
                'name' => $name,
                'color' => $color,
                'sort_order' => $order++,
            ]);

            DB::table('services')
                ->where('category', $name)
                ->update(['service_category_id' => $category->id]);
        }
    }
};
