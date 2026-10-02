<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Empties the customer table, keeping only the accounts you name.
 *
 * The demo customers the seeder used to create are gone, and this is the
 * one-off cleanup for a database that was seeded while they still existed. It
 * removes the `users` rows and nothing else — the `admins` table is not read or
 * written here at all, so the panel login you are using is never at risk.
 *
 * `--keep` takes usernames or emails, so the account to survive does not have to
 * be hard-coded into the command.
 *
 * Two things are worth knowing before running it with --force:
 *
 *   - `appointments.user_id` is ON DELETE CASCADE, so an account that still has
 *     bookings takes them with it. The command counts them first and says so
 *     rather than deleting quietly.
 *   - `carts`, `user_notifications`, `activity_logs` and `otp_codes` are ON
 *     DELETE RESTRICT, which would make the delete fail outright. Their
 *     user_id columns are all nullable, so they are detached first.
 *
 * Guarded behind --force because deleting accounts is not something to do by
 * accident.
 */
class ClearUsersCommand extends Command
{
    protected $signature = 'users:clear
                            {--keep=* : Usernames or emails to keep; everything else is removed}
                            {--force : Actually delete the accounts, not just report what would go}
                            {--dry-run : List the accounts that would be removed and stop}';

    protected $description = 'Remove customer accounts, keeping only the ones named by --keep. The admins table is untouched.';

    /**
     * Tables whose user_id is ON DELETE RESTRICT and nullable, so their rows are
     * detached before the delete instead of blocking it.
     *
     * Guarded on `hasTable` because these are not all created by the current
     * migrations — a database predating them simply has no such table, and the
     * command should clean what is there rather than fail on what is not.
     */
    private const DETACH = ['carts', 'user_notifications', 'activity_logs', 'otp_codes'];

    public function handle(): int
    {
        $keep = collect($this->option('keep'))
            ->map(fn (string $value) => mb_strtolower(trim($value)))
            ->filter()
            ->all();

        $doomed = User::withTrashed()
            ->when($keep, fn ($query) => $query->whereNotIn('username', $keep)->whereNotIn('email', $keep))
            ->get();

        $kept = User::withTrashed()->count() - $doomed->count();

        if ($doomed->isEmpty()) {
            $this->info('Nothing to remove — every account is in the --keep list.');

            return self::SUCCESS;
        }

        $ids = $doomed->pluck('id')->all();
        $appointments = DB::table('appointments')->whereIn('user_id', $ids)->count();

        if ($this->option('dry-run') || ! $this->option('force')) {
            $this->table(
                ['ID', 'Username', 'Email', 'Name', 'Appointments'],
                $doomed->sortBy('id')->map(fn (User $user) => [
                    $user->id,
                    $user->username,
                    $user->email,
                    $user->full_name,
                    DB::table('appointments')->where('user_id', $user->id)->count(),
                ])->all()
            );

            $this->info(sprintf(
                '%d account(s) would be removed, %d kept. %d appointment(s) belong to them and would go with them (that column is ON DELETE CASCADE).',
                $doomed->count(),
                $kept,
                $appointments,
            ));

            $this->line($this->option('force')
                ? 'Re-run without --force, or with --dry-run to see the list first.'
                : 'Re-run with --force to do it, or --dry-run to see the list first.');

            return self::SUCCESS;
        }

        if ($appointments > 0) {
            $this->warn("{$appointments} appointment(s) will be deleted along with these accounts — appointments.user_id is ON DELETE CASCADE and the appointment history cannot be reassigned to another customer.");
        }

        DB::transaction(function () use ($ids) {
            foreach (self::DETACH as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->whereIn('user_id', $ids)->update(['user_id' => null]);
                }
            }

            // Laravel's own notification inbox is polymorphic, so it has no
            // foreign key to cascade and has to be cleared by hand.
            DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->whereIn('notifiable_id', $ids)
                ->delete();

            // A hard delete, not a soft one: a soft-deleted row still holds the
            // unique email and username, so the same person could never
            // register again.
            DB::table('users')->whereIn('id', $ids)->delete();
        });

        $this->info(sprintf('Removed %d account(s). %d kept.', $doomed->count(), $kept));
        $this->line('The admins table was not touched, so the panel login is unchanged.');

        return self::SUCCESS;
    }
}
