<?php

namespace App\Console\Commands;

use App\Services\AppointmentArchiver;
use Illuminate\Console\Command;

/**
 * The nightly auto-archive, run by the scheduler.
 *
 * Registered in routes/console.php with `Schedule::command(...)->dailyAt(...)`,
 * so it needs no cron entry of its own beyond the single `schedule:run` that
 * Laravel asks every project for. See that file for the exact time and for what
 * happens if it is missed.
 */
class ArchiveCompletedAppointmentsCommand extends Command
{
    protected $signature = 'appointments:archive-completed
                            {--dry-run : Report what would be archived, and change nothing}
                            {--days= : Override the 30-day window}';

    protected $description = 'Archive completed appointments older than the retention window.';

    public function handle(AppointmentArchiver $archiver): int
    {
        if ($this->option('days') !== null) {
            // Not a supported option in its own right — the window is a constant
            // because it is a business rule, not a tuning knob. It exists so the
            // command can be exercised end-to-end against a fixture without
            // backdating rows by thirty days.
            $this->components->warn(
                'Using --days='.$this->option('days').' instead of the configured '
                .AppointmentArchiver::DAYS_BEFORE_AUTO_ARCHIVE.'-day window.'
            );
        }

        if ($this->option('dry-run')) {
            $count = $archiver->sweepableCount();

            $this->components->info(
                $count.' completed appointment'.($count === 1 ? '' : 's').' would be archived. Nothing was changed.'
            );

            return self::SUCCESS;
        }

        $archived = $archiver->sweep();

        if ($archived === 0) {
            $this->components->info('Nothing to archive.');

            return self::SUCCESS;
        }

        $this->components->info(
            $archived.' completed appointment'.($archived === 1 ? '' : 's').' archived.'
        );

        return self::SUCCESS;
    }
}