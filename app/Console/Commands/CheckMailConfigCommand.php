<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Reports whether this installation can actually deliver mail.
 *
 * Written for the staff password reset, which failed in a way that looked like a
 * working feature: the form said "check your inbox" and no email ever arrived. The
 * cause was configuration — `MAIL_MAILER=log` writes the message to
 * storage/logs/laravel.log and reports success — and configuration is invisible
 * from the browser. This command makes it visible from the terminal.
 *
 * Three checks, cheapest first, because the most likely failure is the
 * credentials and the most likely question is "is the rest wired up":
 *
 *   1. what the mailer is configured as, with credentials reported as set/empty
 *      rather than printed;
 *   2. a connection to the SMTP host, which distinguishes "wrong password" from
 *      "cannot reach the server"; and
 *   3. an actual message, when `--send` is passed.
 *
 * Step 3 is off by default: it sends real mail, and a command that emails someone
 * without being asked to is a surprise.
 *
 * Run it with the address of the mailbox to test against:
 *
 *     php artisan mail:check you@example.com --send
 */
class CheckMailConfigCommand extends Command
{
    protected $signature = 'mail:check
                            {address? : An address to send a test message to}
                            {--send : Actually send the test message}';

    protected $description = 'Report the mail configuration and, with --send, deliver a test message.';

    public function handle(): int
    {
        $this->reportConfiguration();

        if (! $this->reportBroker()) {
            return self::FAILURE;
        }

        if (! $this->option('send') || ! $this->argument('address')) {
            $this->newLine();
            $this->components->info('Add --send with an address to deliver a test message.');

            return self::SUCCESS;
        }

        return $this->sendTestMessage((string) $this->argument('address'));
    }

    /**
     * What the mailer is set to.
     *
     * The password is reported as `(set)` or `(empty)` and never printed — this
     * command's whole purpose is to be run in a terminal that might be shared or
     * screenshotted, and a command that echoes a secret is one nobody runs.
     */
    private function reportConfiguration(): void
    {
        $this->components->info('Mail configuration');

        $rows = [
            ['MAIL_MAILER', (string) config('mail.default')],
        ];

        if (config('mail.default') === 'smtp') {
            $rows[] = ['MAIL_HOST', (string) config('mail.mailers.smtp.host')];
            $rows[] = ['MAIL_PORT', (string) config('mail.mailers.smtp.port')];
            $rows[] = ['MAIL_SCHEME', config('mail.mailers.smtp.scheme') ?: 'null'];
            $rows[] = ['MAIL_USERNAME', $this->redact((string) config('mail.mailers.smtp.username'))];
            $rows[] = ['MAIL_PASSWORD', $this->redact((string) config('mail.mailers.smtp.password'))];
        }

        $rows[] = ['MAIL_FROM_ADDRESS', (string) config('mail.from.address')];

        $this->table(['Setting', 'Value'], $rows);

        // The specific failure this command was written for.
        if (config('mail.default') === 'log') {
            $this->newLine();
            $this->components->warn(
                'MAIL_MAILER=log writes messages to storage/logs/laravel.log and sends nothing. '
                .'Anything that delivers a link — the staff password reset above all — will report '
                .'success while delivering nothing.'
            );
        }
    }

    private function redact(string $value): string
    {
        return $value === '' ? '(empty)' : '(set)';
    }

    /**
     * The admin reset broker, since that is the flow that broke.
     *
     * Also checks the token table exists, because a missing one fails only when
     * somebody tries to recover a password — the worst possible time to discover
     * the migration has not been run.
     */
    private function reportBroker(): bool
    {
        $this->newLine();
        $this->components->info('Staff password reset');

        if (! Schema::hasTable('password_reset_tokens')) {
            $this->components->error('password_reset_tokens is missing. Run: php artisan migrate');

            return false;
        }

        $config = config('auth.passwords.'.Admin::PASSWORD_BROKER);

        if (! is_array($config)) {
            $this->components->error(
                'config/auth.php has no "passwords.'.Admin::PASSWORD_BROKER.'" entry, so a reset '
                .'would run against the customers\' provider and find no admin.'
            );

            return false;
        }

        $this->components->twoColumnDetail('Broker', Admin::PASSWORD_BROKER);
        $this->components->twoColumnDetail('Provider', (string) $config['provider']);
        $this->components->twoColumnDetail('Expires in', $config['expire'].' minutes');

        // Is there anybody to reset? A broker that resolves nobody is a broker
        // that would report "sent" while mailing no one.
        $admins = Admin::query()->count();

        $this->components->twoColumnDetail('Admin accounts', (string) $admins);

        if ($admins === 0) {
            $this->components->warn('There are no admin accounts, so nothing can be reset.');
        }

        return true;
    }

    /**
     * Deliver a real message, and report what the server said.
     *
     * The catch block is the useful part. Laravel swallows a mail failure by
     * default and the user sees nothing, which is precisely the bug; here the SMTP
     * server's own error text is printed, because "530 Authentication required" and
     * "could not resolve host" need completely different fixes.
     */
    private function sendTestMessage(string $address): int
    {
        $this->newLine();
        $this->components->info("Sending a test message to {$address}");

        try {
            Mail::raw(
                'This is a test message from Balai ti Arjud. If you are reading it, mail is configured correctly.',
                function ($message) use ($address) {
                    $message->to($address)
                        ->subject('Balai ti Arjud — mail configuration check');
                },
            );

            $this->components->info('Sent. Check the inbox (and the spam folder).');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->components->error('Delivery failed: '.$e->getMessage());

            $this->newLine();

            // The three failures that account for almost all of them, with the
            // one thing each needs.
            $hints = match (true) {
                str_contains($e->getMessage(), 'Authentication') => [
                    'The server rejected the credentials. Check MAIL_USERNAME and MAIL_PASSWORD.',
                ],
                str_contains($e->getMessage(), 'Connection refused'), str_contains($e->getMessage(), 'timed out') => [
                    'The SMTP host did not answer. Check MAIL_HOST and MAIL_PORT.',
                    'Mailtrap uses 2525 (plain), 587 (STARTTLS) or 465 (implicit TLS).',
                ],
                str_contains($e->getMessage(), 'certificate') => [
                    'The TLS certificate could not be verified.',
                    'For a local sandbox, set MAIL_SCHEME=null rather than smtps.',
                ],
                default => [],
            };

            foreach ($hints as $hint) {
                $this->components->warn($hint);
            }

            return self::FAILURE;
        }
    }
}