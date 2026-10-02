<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the per-category `version` column from `terms_and_conditions`.
 *
 * Terms used to be append-only: every admin save inserted a new row and bumped
 * `version`, and the old `unique(category, version)` is what let a category
 * accumulate a history. Saving is now an in-place update of the single row a
 * category owns, so the column had no remaining writer and no remaining reader.
 *
 * The interesting part is the de-duplication. That old unique index allowed many
 * rows per category, and real installs have them — the seeder deliberately wrote
 * a superseded v1 plus a live v2 for each of the three policies. A bare
 * `dropColumn` would leave those duplicates behind and then fail on the new
 * `unique(category)` index, so the survivors are chosen *first*:
 *
 *   1. the published row with the highest version, because that is the text
 *      customers are being shown right now and it must not be the row we lose;
 *   2. failing that, the highest version overall, so a category that was only
 *      ever a draft keeps its most recent draft rather than an older one;
 *   3. failing that, nothing — a category with no rows simply stays absent and
 *      is created by the admin's first save.
 *
 * Ordering is done in SQL rather than through the model on purpose: the model has
 * already stopped listing `version` as fillable by the time this runs, and a
 * migration should not depend on application state that is free to change.
 *
 * `doctrine/dbal` is not required for `dropColumn` on MySQL, and the project
 * already drops columns without it (see the down payment status repair), so no
 * new dependency is introduced here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('terms_and_conditions')) {
            return;
        }

        // A fresh install no longer builds `version` (the create migration was
        // updated), so there is nothing to collapse or drop.
        if (! Schema::hasColumn('terms_and_conditions', 'version')) {
            $this->enforceOneRowPerCategory();

            return;
        }

        $this->keepOneRowPerCategory();

        // The old index has to go before the column, and `dropColumn` would
        // otherwise take it implicitly and leave the new index unnamed.
        Schema::table('terms_and_conditions', function (Blueprint $table) {
            $table->dropUnique(['category', 'version']);
        });

        Schema::table('terms_and_conditions', function (Blueprint $table) {
            $table->dropColumn('version');
        });

        $this->enforceOneRowPerCategory();
    }

    /**
     * Delete every row for a category except the one worth keeping.
     */
    private function keepOneRowPerCategory(): void
    {
        $categories = DB::table('terms_and_conditions')->distinct()->pluck('category');

        foreach ($categories as $category) {
            $keeperId = DB::table('terms_and_conditions')
                ->where('category', $category)
                // Published first, then newest first. `orderByDesc` on a boolean
                // sorts true above false, which is exactly the preference wanted.
                ->orderByDesc('is_published')
                ->orderByDesc('version')
                ->value('id');

            DB::table('terms_and_conditions')
                ->where('category', $category)
                ->when($keeperId !== null, fn ($query) => $query->where('id', '!=', $keeperId))
                ->delete();
        }
    }

    /**
     * One row per category, which is what "saving overwrites in place" requires:
     * without this the database would happily accept a second row for a policy
     * and the admin list would show two cards for one policy again.
     */
    private function enforceOneRowPerCategory(): void
    {
        $hasVersionedUnique = collect(Schema::getIndexes('terms_and_conditions'))
            ->contains(fn (array $index) => $index['unique'] === true && $index['columns'] === ['category']);

        if ($hasVersionedUnique) {
            return;
        }

        Schema::table('terms_and_conditions', function (Blueprint $table) {
            $table->unique('category');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('terms_and_conditions')) {
            return;
        }

        Schema::table('terms_and_conditions', function (Blueprint $table) {
            $table->dropUnique(['category']);
        });

        /*
         * `version` is restored as 1 for every row rather than being reconstructed
         * from the version numbers this migration deleted — those numbers are gone,
         * and inventing a plausible history for rows that were rewritten in place
         * would be a lie the model then has to reason about.
         */
        Schema::table('terms_and_conditions', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1);
        });

        Schema::table('terms_and_conditions', function (Blueprint $table) {
            $table->unique(['category', 'version']);
        });
    }
};
