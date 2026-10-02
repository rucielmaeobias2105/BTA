<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Split the service price column in two.
 *
 * `price` is what the customer is shown, and a salon price list is not always a
 * single number: "100+", "249/499", "1,200". It becomes a string and is
 * rendered exactly as typed.
 *
 * `base_price` is the number the booking maths needs. It is a real column rather
 * than something parsed out of `price` on read, because parsing "249/499" would
 * silently invent a total nobody entered, and appointment_service.price,
 * appointments.total_amount and down_payment_amount are all DECIMAL.
 */
return new class extends Migration
{
    /** Tables whose price is a customer-facing string, keyed by primary key. */
    protected array $tables = ['services', 'service_variants'];

    public function up(): void
    {
        // MySQL has no transactional DDL, so this migration is written to be
        // re-runnable: the schema change is guarded, and the data fix below is
        // idempotent and therefore always runs. A failure after the DDL leaves
        // the columns in place with nothing backfilled, which re-running fixes.
        if (! Schema::hasColumn('services', 'base_price')) {
            foreach ($this->tables as $name) {
                Schema::table($name, function (Blueprint $table) {
                    $table->string('price', 50)->nullable()->change();
                    $table->decimal('base_price', 10, 2)->nullable();
                });
            }

            // appointment_service.price stays DECIMAL: it is the snapshotted
            // line amount that lineTotal() multiplies. display_price carries
            // the string the customer actually saw, so a summary or invoice can
            // show "249/499" without re-reading a service that may since have
            // been edited.
            Schema::table('appointment_service', function (Blueprint $table) {
                $table->string('display_price', 50)->nullable()->after('price');
            });
        }

        $this->backfill();
    }

    public function down(): void
    {
        if (! Schema::hasColumn('services', 'base_price')) {
            return;
        }

        // A range has no DECIMAL equivalent, so the first figure wins. Written
        // out explicitly rather than left to the column change: SQLite does not
        // coerce on ALTER, so a range would otherwise survive in a DECIMAL
        // column here while MySQL silently truncated it. Same result either way,
        // but this one is deliberate and testable.
        //
        // DB::table rather than the models, so soft-deleted rows come too.
        foreach ($this->tables as $name) {
            foreach (DB::table($name)->cursor() as $row) {
                $figure = $row->base_price ?? $this->firstFigure($row->price);

                DB::table($name)->where('id', $row->id)->update([
                    'base_price' => $figure,
                    'price' => $figure,
                ]);
            }
        }

        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('base_price');
                $table->decimal('price', 10, 2)->nullable()->change();
            });
        }

        Schema::table('appointment_service', function (Blueprint $table) {
            $table->dropColumn('display_price');
        });
    }

    /**
     * base_price mirrors the old numeric column and price becomes its 2dp text,
     * so a service that showed "799.00" keeps showing "799.00".
     */
    protected function backfill(): void
    {
        foreach ($this->tables as $name) {
            $rows = DB::table($name)
                ->select('id', 'price')
                ->whereNull('base_price')
                ->cursor();

            foreach ($rows as $row) {
                $base = $this->firstFigure($row->price);

                DB::table($name)->where('id', $row->id)->update([
                    'base_price' => $base,
                    'price' => $base,
                ]);
            }
        }

        // Only rows with nothing snapshotted, so a re-run cannot overwrite a
        // display_price that a later booking has already written.
        DB::table('appointment_service')
            ->whereNull('display_price')
            ->update(['display_price' => DB::raw('price')]);
    }

    /**
     * The first number in a price-ish string, normalised to 2dp.
     */
    protected function firstFigure(mixed $price): string
    {
        $first = is_numeric($price)
            ? (float) $price
            : (float) (preg_match('/[0-9][0-9,]*(?:\.[0-9]+)?/', (string) $price, $m) ? $m[0] : 0);

        return number_format((float) str_replace(',', '', (string) $first), 2, '.', '');
    }
};
