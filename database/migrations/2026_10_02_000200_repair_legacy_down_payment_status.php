<?php

use App\Enums\DownPaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Repairs `appointments.down_payment_status`, which had drifted out of the enum.
 *
 * The shipped schema — `balai_ti_arjud.sql` — declares the column as
 * `varchar(255) NOT NULL DEFAULT 'pending'`. The enum it is cast to has only ever
 * had `unverified`, `verified`, `rejected` and `not_required`. So any row that
 * inherited the column's own default, or was written by a raw query or an import
 * that did not name a status, holds `'pending'` — and the cast on read throws
 * `"pending" is not a valid backing value`.
 *
 * That is a whole-page failure, not a bad-cell one: `/appointments` reads the
 * status for every row it lists, so one legacy row took the customer's list down
 * with it.
 *
 * Two things are repaired, and both are needed — fixing the data alone leaves the
 * column's default still handing out a value the enum rejects to the next insert:
 *
 *   1. every stored value the enum cannot read is rewritten to the case it meant;
 *   2. the column default becomes a value the enum can read, so the next insert
 *      that omits the column cannot recreate the bug.
 *
 * NOTHING IS DROPPED AND NO ROW IS DELETED. Only the four bytes of one status
 * string change, on rows where it was unreadable, to the reading the application
 * itself would have written for that booking. Every other column — the reference,
 * the amount, the appointment, the customer — is untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('appointments') || ! Schema::hasColumn('appointments', 'down_payment_status')) {
            return;
        }

        $this->repairStoredValues();
        $this->repairColumnDefault();
    }

    /**
     * Rewrite every stored value the enum cannot cast.
     *
     * Driven off the distinct values actually present rather than a hardcoded list
     * of known-bad ones, so an import that introduced something else entirely is
     * repaired on the same run instead of needing a second migration.
     */
    protected function repairStoredValues(): void
    {
        $stored = DB::table('appointments')
            ->whereNotNull('down_payment_status')
            ->distinct()
            ->pluck('down_payment_status');

        foreach ($stored as $value) {
            // A value the enum already understands is left exactly as it is.
            if (DownPaymentStatus::tryFrom((string) $value) !== null) {
                continue;
            }

            // `normalize()` returns null for a value it cannot explain at all,
            // rather than guessing — which is the right answer for a *display* and
            // the wrong one for a *repair*. Storing "needs review" is not a value
            // this column can hold, so the fallback is the case that cannot be
            // making a claim about money: no deposit to verify. The tolerant cast
            // on the model still surfaces a genuinely unknown value as unknown
            // until an admin sets it by hand.
            $repaired = (DownPaymentStatus::normalize((string) $value) ?? DownPaymentStatus::NotRequired)->value;

            $affected = DB::table('appointments')
                ->where('down_payment_status', $value)
                ->update(['down_payment_status' => $repaired]);

            $this->report("down_payment_status '{$value}' -> '{$repaired}' on {$affected} row(s)");
        }
    }

    /**
     * Point the column default at a value the enum can read.
     *
     * Reads the current default first and returns early when it is already valid,
     * so a schema created straight from the migrations — where the default has
     * always been `unverified` — is not rebuilt for nothing. Only MySQL is
     * touched: it is the one driver whose `DEFAULT` is what produced the bad rows
     * in the first place, and it is the only one that can alter a default in
     * place. SQLite cannot, and does not need to, because the migration-built
     * schema never had the bad default to begin with.
     */
    protected function repairColumnDefault(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $current = DB::selectOne(
            "SELECT COLUMN_DEFAULT AS `default` FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'appointments'
               AND COLUMN_NAME = 'down_payment_status'"
        );

        $current = $current?->default;

        // A nullable column with no default inserts NULL, which the enum cast
        // handles, and a NOT NULL one with no default fails loudly at the insert
        // rather than storing something unreadable. Neither is the bug.
        if ($current === null) {
            return;
        }

        $current = trim((string) $current, "'");

        if (DownPaymentStatus::tryFrom($current) !== null) {
            return;
        }

        $target = DownPaymentStatus::NotRequired->value;

        DB::statement(
            "ALTER TABLE `appointments` ALTER COLUMN `down_payment_status` SET DEFAULT '{$target}'"
        );

        $this->report("down_payment_status default '{$current}' -> '{$target}'");
    }

    /**
     * Say what was rewritten.
     *
     * A data repair that runs silently is a data repair nobody can audit after
     * the fact, so each change is written to the application log — where the
     * operator reads it when they ask why a booking's deposit badge changed.
     * Skipped under the test suite, where the assertions say it instead.
     */
    protected function report(string $message): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        Log::info('down_payment_status repair: '.$message);
    }

    /**
     * Only the default is reverted.
     *
     * The rewritten values are deliberately not put back: they were unreadable
     * before this migration, and restoring them would put the page that was
     * crashing straight back into the state that crashed it. Rolling a data
     * repair back to corruption is not a useful capability.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql' && Schema::hasTable('appointments')) {
            DB::statement(
                "ALTER TABLE `appointments` ALTER COLUMN `down_payment_status` SET DEFAULT 'unverified'"
            );
        }
    }
};
