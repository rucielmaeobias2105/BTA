<?php

namespace Tests\Feature;

use App\Enums\DownPaymentStatus;
use App\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `appointments.down_payment_status` had drifted out of the enum it is cast to.
 *
 * The shipped schema declared the column `varchar(255) DEFAULT 'pending'`, while
 * `DownPaymentStatus` has only ever had `unverified`, `verified`, `rejected` and
 * `not_required`. So a booking that inherited the column's default — inserted by
 * a raw query, an import, or simply by omitting the column — held `'pending'`,
 * and reading it threw `"pending" is not a valid backing value`.
 *
 * That is a whole-page failure, not a bad-cell one. `/appointments` reads the
 * status for every row it lists, so one legacy row took the customer's list down
 * with it; this file is what would notice it coming back.
 */
class DownPaymentStatusRepairTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The legacy value reads back as a real case.
     *
     * The whole bug in one assertion: this cast used to throw.
     */
    public function test_the_legacy_pending_value_is_read_as_a_case(): void
    {
        $this->makeAppointment(attributes: ['down_payment_status' => 'pending']);

        $appointment = Appointment::first();

        $this->assertSame(
            DownPaymentStatus::NotRequired,
            $appointment->down_payment_status
        );
    }

    /** `normalize` maps the legacy spelling and admits when it does not know. */
    public function test_normalize_understands_the_legacy_spelling(): void
    {
        $this->assertSame(DownPaymentStatus::NotRequired, DownPaymentStatus::normalize('pending'));
        $this->assertSame(DownPaymentStatus::NotRequired, DownPaymentStatus::normalize('PENDING'));
        $this->assertSame(DownPaymentStatus::Unverified, DownPaymentStatus::normalize('unverified'));

        // Whitespace, from a hand-edited query.
        $this->assertSame(DownPaymentStatus::Verified, DownPaymentStatus::normalize(' verified '));

        // Something nobody defined at all. Null, not a guess: reading it as
        // `NotRequired` would tell an admin verifying a deposit that there was no
        // deposit to verify, and that is not a thing to get wrong by accident.
        $this->assertNull(DownPaymentStatus::normalize('nonsense'));
        $this->assertNull(DownPaymentStatus::normalize(null));
    }

    /**
     * An unreadable value renders as a question, not as a fact.
     *
     * This is the pair that makes the fix permanent rather than one-shot: even if
     * a value nobody defines gets written again — by an import, a raw query, a dump
     * — the screen that reads the status still renders, and says it needs a look
     * instead of quietly showing one.
     *
     * Asserted on the admin's verification screen, which is where the status is
     * still read. The customer's Appointment Details dialog no longer has a
     * payment section at all, so it cannot be the thing that proves this.
     */
    public function test_an_unknown_stored_value_renders_as_needing_review(): void
    {
        $this->makeAppointment(attributes: ['down_payment_status' => 'nonsense']);

        $appointment = Appointment::first();

        $this->assertNull($appointment->down_payment_status);
        $this->assertSame('Needs review', $appointment->downPaymentLabel());
        $this->assertSame('pending', $appointment->downPaymentBadgeTone());

        $this->actingAs($this->makeAdmin(), 'admin')
            ->get(route('admin.appointments.show', $appointment))
            ->assertOk()
            ->assertSee('Needs review');
    }

    /**
     * The customer's own appointments list renders a bad row.
     *
     * This is the page the original bug took down, so it is the one that has to
     * keep working whatever is in the column. It no longer reads the status at
     * all — the payment section is gone — and that is exactly why it survives.
     */
    public function test_the_customer_list_survives_a_legacy_row(): void
    {
        $user = $this->makeUser();

        $this->makeAppointment($user, attributes: ['down_payment_status' => 'pending']);
        $this->makeAppointment($user, attributes: ['down_payment_status' => 'unverified']);

        $this->actingAs($user)
            ->get(route('appointments.index'))
            ->assertOk();
    }

    /**
     * A whole list of appointments, some legacy, still renders.
     *
     * The failure this fixes was not per-row: the page builds a payload for every
     * appointment it lists, so a single bad row took all of them down. Asserted
     * across a mixed set rather than one row for that reason.
     */
    public function test_a_list_mixed_with_legacy_rows_still_renders(): void
    {
        $user = $this->makeUser();

        $this->makeAppointment($user, attributes: ['down_payment_status' => 'pending']);
        $this->makeAppointment($user, attributes: ['down_payment_status' => 'unverified']);
        $this->makeAppointment($user, attributes: ['down_payment_status' => 'not_required']);

        // Three rows, one of them the value the enum never had.
        $this->actingAs($user)
            ->get(route('appointments.index'))
            ->assertOk();

        // And the statuses still resolve to real cases, one row at a time — which
        // is the mapping the page relied on before the dialog stopped reading it.
        $statuses = Appointment::orderBy('id')->pluck('down_payment_status')->all();

        $this->assertContains(DownPaymentStatus::NotRequired, $statuses);
        $this->assertContains(DownPaymentStatus::Unverified, $statuses);
    }

    /**
     * The repair moves the value; it does not delete the row.
     *
     * Every other column has to survive, because the repair exists to make the
     * page work and not to lose anybody's booking.
     */
    public function test_the_repair_keeps_the_appointment_and_its_reference(): void
    {
        $appointment = $this->makeAppointment(attributes: [
            'down_payment_status' => 'pending',
            'down_payment_reference' => 'GCASH123456',
            'down_payment_amount' => 300,
        ]);

        DownPaymentStatus::normalize($appointment->down_payment_status->value);

        $fresh = Appointment::find($appointment->id);

        $this->assertNotNull($fresh, 'The appointment must still exist.');
        $this->assertSame('GCASH123456', $fresh->down_payment_reference);
        $this->assertSame(300.0, (float) $fresh->down_payment_amount);
    }

    /**
     * The migration actually rewrites the bad values.
     *
     * Run against the suite's own driver rather than only on MySQL, so the repair
     * is covered by every run rather than only by the environments where the
     * drift happened. The values are written with a raw update because that is
     * how they got there in the first place — through something that did not go
     * through the model — and going through the model would exercise the tolerant
     * cast instead of the schema.
     */
    public function test_the_migration_rewrites_every_unreadable_value(): void
    {
        $this->makeAppointment(attributes: ['down_payment_status' => 'unverified']);

        DB::table('appointments')->update(['down_payment_status' => 'pending']);

        $this->assertSame(
            1,
            DB::table('appointments')->where('down_payment_status', 'pending')->count(),
            'The fixture should hold the legacy value before the repair runs.'
        );

        $migration = require database_path('migrations/2026_10_02_000200_repair_legacy_down_payment_status.php');
        $migration->up();

        $this->assertSame(
            0,
            DB::table('appointments')->where('down_payment_status', 'pending')->count(),
            'No unreadable value may survive the migration.'
        );

        $this->assertSame(
            DownPaymentStatus::NotRequired,
            Appointment::first()->down_payment_status
        );
    }

    /**
     * The column's default cannot hand out the bad value again.
     *
     * Fixing the data alone would leave the generator of the bug in place: the
     * next insert that omits the column would take `pending` from the default and
     * be unreadable again. Only assertable on MySQL, which is the driver whose
     * DEFAULT produced the original rows — SQLite builds its schema from the
     * migrations, where the default has always been valid.
     */
    public function test_the_column_default_is_a_value_the_enum_can_read(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Only MySQL has the default this repair corrects.');
        }

        $default = DB::selectOne(
            "SELECT COLUMN_DEFAULT AS `default` FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'appointments'
               AND COLUMN_NAME = 'down_payment_status'"
        )?->default;

        $this->assertNotNull(
            DownPaymentStatus::tryFrom(trim((string) $default, "'")),
            "The column default '{$default}' is not a backing value of DownPaymentStatus, so the next insert that omits the column recreates the bug."
        );
    }

    /**
     * The schema the salon would import agrees with the enum.
     *
     * The dump is where the drift came from: it is a sanitized snapshot, so
     * nothing imports it automatically, but it is what anyone rebuilding this
     * database from the file gets. Checked against the file itself rather than
     * against a live column, because the live column is fixed by a migration and
     * the file is not.
     */
    public function the_shipped_sql_dump_does_not_declare_the_legacy_default(): void
    {
        $path = base_path('balai_ti_arjud.sql');

        if (! is_file($path)) {
            $this->markTestSkipped('The dump is not in this checkout.');
        }

        $schema = (string) file_get_contents($path);

        $this->assertDoesNotMatchRegularExpression(
            '/`down_payment_status`[^,]*DEFAULT \'pending\'/i',
            $schema,
            'The dump must not declare a default the enum cannot read.'
        );
    }
}
