<?php

namespace Tests\Feature;

use App\Enums\TermsCategory;
use App\Models\TermsAndCondition;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The migration that removes the `version` column from `terms_and_conditions`.
 *
 * Terms used to be append-only: one row per version, and `unique(category,
 * version)` let a policy accumulate a history. Saving now overwrites the single
 * row a category owns, so the column is dropped and replaced by
 * `unique(category)`.
 *
 * Every other test in the suite runs against a database migrated from scratch,
 * where the create migration no longer builds `version` and the migration below
 * finds nothing to collapse. That is the easy path. The path that matters is the
 * one an existing install takes, where each policy really does have a superseded
 * draft *and* a live row — so these tests rebuild the old schema, fill it with
 * that shape of data, and run the migration against it for real.
 */
class TermsVersionRemovalMigrationTest extends TestCase
{
    use RefreshDatabase;

    /* ------------------------------------------------------------------ */
    /* The existing-install path                                           */
    /* ------------------------------------------------------------------ */

    public function test_it_collapses_each_policy_to_its_live_row(): void
    {
        $this->restoreLegacyTable();

        // What the old seeder produced: a superseded v1 draft and a live v2.
        $this->legacyRow('booking', 1, '<p>Original booking terms.</p>', published: false);
        $bookingId = $this->legacyRow('booking', 2, '<p>Live booking terms.</p>', published: true);

        $this->legacyRow('cancellation', 1, '<p>Original cancellation terms.</p>', published: false);
        $cancellationId = $this->legacyRow('cancellation', 2, '<p>Live cancellation terms.</p>', published: true);

        $this->runMigration();

        $this->assertSame(
            2,
            DB::table('terms_and_conditions')->count(),
            'Two policies in, two rows out — one per category.',
        );

        // The survivor is the row customers were already reading, not the draft.
        $booking = DB::table('terms_and_conditions')->where('category', 'booking')->first();

        $this->assertSame($bookingId, $booking->id, 'The live row must be the one kept.');
        $this->assertSame('<p>Live booking terms.</p>', $booking->content);
        $this->assertTrue((bool) $booking->is_published);

        $this->assertSame(
            $cancellationId,
            DB::table('terms_and_conditions')->where('category', 'cancellation')->value('id'),
        );
    }

    public function test_it_drops_the_version_column_and_backs_the_category_with_a_unique_index(): void
    {
        $this->restoreLegacyTable();
        $this->legacyRow('booking', 1, '<p>Draft.</p>', published: false);
        $this->legacyRow('booking', 2, '<p>Live.</p>', published: true);

        $this->runMigration();

        $this->assertFalse(Schema::hasColumn('terms_and_conditions', 'version'));

        // The index is the only thing stopping a second row for one policy
        // creeping back in now that saving no longer appends deliberately.
        $indexes = collect(Schema::getIndexes('terms_and_conditions'))
            ->filter(fn (array $index) => $index['unique'] === true)
            ->pluck('columns')
            ->map(fn (array $columns) => implode(',', $columns));

        $this->assertTrue(
            $indexes->contains('category'),
            'A unique index on category is required. Found: '.$indexes->implode(' | '),
        );

        $this->assertFalse(
            $indexes->contains('category,version'),
            'The old category+version unique index should be gone.',
        );

        // And the database itself now rejects a second row, rather than the app
        // merely not writing one.
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('terms_and_conditions')->insert([
            'category' => 'booking',
            'content' => '<p>A second booking policy.</p>',
            'is_published' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * A policy that was never published has no "live" row to keep, so the newest
     * row wins — keeping an older draft and discarding a newer one would silently
     * roll a policy back.
     */
    public function test_an_entirely_unpublished_policy_keeps_its_newest_row(): void
    {
        $this->restoreLegacyTable();

        $this->legacyRow('rescheduling', 1, '<p>Oldest draft.</p>', published: false);
        $newestId = $this->legacyRow('rescheduling', 3, '<p>Newest draft.</p>', published: false);
        $this->legacyRow('rescheduling', 2, '<p>Middle draft.</p>', published: false);

        $this->runMigration();

        $row = DB::table('terms_and_conditions')->where('category', 'rescheduling')->first();

        $this->assertSame($newestId, $row->id);
        $this->assertSame('<p>Newest draft.</p>', $row->content);
        $this->assertFalse((bool) $row->is_published);
    }

    /**
     * A category whose rows disagree about which is live must not crash on a
     * duplicate-key insert, which is what would happen if the old
     * `unique(category, version)` were left in place while the new
     * `unique(category)` was added alongside it.
     */
    public function test_it_copes_with_a_category_that_has_several_live_rows(): void
    {
        $this->restoreLegacyTable();

        $this->legacyRow('booking', 1, '<p>Live v1.</p>', published: true);
        $this->legacyRow('booking', 2, '<p>Live v2.</p>', published: true);

        $this->runMigration();

        $row = DB::table('terms_and_conditions')->where('category', 'booking')->first();

        $this->assertSame(1, DB::table('terms_and_conditions')->count());
        $this->assertSame(
            '<p>Live v2.</p>',
            $row->content,
            'The newest live row wins when the old data was already inconsistent.',
        );
    }

    public function test_it_leaves_a_fresh_install_alone(): void
    {
        // No legacy table rebuild: this is the state a new database is in, since
        // the create migration no longer makes a `version` column. The migration
        // must be a no-op rather than an error.
        $this->assertFalse(Schema::hasColumn('terms_and_conditions', 'version'));

        TermsAndCondition::create([
            'category' => TermsCategory::Booking,
            'content' => '<p>Fresh booking terms.</p>',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->runMigration();

        $this->assertSame(1, TermsAndCondition::where('category', TermsCategory::Booking->value)->count());
    }

    /* ------------------------------------------------------------------ */
    /* The seeder                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Re-seeding must update each policy, not add a second row for it.
     *
     * The seeder used to write two rows per category — a superseded v1 and a live
     * v2 — keyed on `[category, version]`. It is now keyed on the category alone,
     * because that is the unique index the schema has. Running it twice against a
     * seeder that had drifted back to appending would now fail on a duplicate key
     * rather than quietly doubling up, so this is the guard on that.
     */
    public function test_seeding_the_terms_twice_leaves_one_row_per_policy(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $afterFirstRun = TermsAndCondition::count();

        $this->assertSame(
            count(TermsCategory::cases()),
            $afterFirstRun,
            'The seeder should write exactly one row per policy.',
        );

        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->assertSame(
            $afterFirstRun,
            TermsAndCondition::count(),
            'Re-seeding must not add a second row for a policy.',
        );

        foreach (TermsCategory::cases() as $category) {
            $row = TermsAndCondition::where('category', $category->value)->sole();

            $this->assertTrue($row->is_published, "The seeded {$category->value} policy should be published.");
        }
    }

    /* ------------------------------------------------------------------ */
    /* Rollback                                                            */
    /* ------------------------------------------------------------------ */

    public function test_it_rolls_back_without_losing_the_terms(): void
    {
        $this->restoreLegacyTable();

        $this->legacyRow('booking', 1, '<p>Draft.</p>', published: false);
        $keptId = $this->legacyRow('booking', 2, '<p>Live booking terms.</p>', published: true);

        $migration = $this->runMigration();
        $migration->down();

        $this->assertTrue(Schema::hasColumn('terms_and_conditions', 'version'));

        // The row survives, with the content it had. `version` comes back as 1
        // for everything rather than a reconstructed history.
        $row = DB::table('terms_and_conditions')->find($keptId);

        $this->assertNotNull($row, 'Rollback must not delete the policy.');
        $this->assertSame('<p>Live booking terms.</p>', $row->content);
        $this->assertSame(1, (int) $row->version);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Recreate the pre-removal schema, `version` column and all.
     */
    private function restoreLegacyTable(): void
    {
        Schema::dropIfExists('terms_and_conditions');

        Schema::create('terms_and_conditions', function (Blueprint $table) {
            $table->id();
            $table->enum('category', ['booking', 'cancellation', 'rescheduling'])->index();
            $table->unsignedInteger('version');
            $table->longText('content');
            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();

            $table->unique(['category', 'version']);
            $table->index(['category', 'is_published']);
        });
    }

    /**
     * Insert straight through the query builder — the model no longer lists
     * `version` as fillable, which is the whole point, so it cannot build these.
     */
    private function legacyRow(string $category, int $version, string $content, bool $published): int
    {
        return DB::table('terms_and_conditions')->insertGetId([
            'category' => $category,
            'version' => $version,
            'content' => $content,
            'is_published' => $published,
            'published_at' => $published ? now()->subWeek() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function runMigration(): object
    {
        $migration = require database_path('migrations/2026_10_02_000400_drop_terms_version_column.php');

        $migration->up();

        return $migration;
    }
}
