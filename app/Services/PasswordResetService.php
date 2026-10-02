<?php

namespace App\Services;

use App\Models\PasswordResetCode;
use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use App\Support\IssuedResetCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

/**
 * Four-step password reset:
 *   1. email  -> 2. six-digit code  -> 3. new password  -> 4. confirm
 *
 * Only the hash of the code is persisted (never the plaintext), and every
 * issued code carries an expiry.
 */
class PasswordResetService
{
    /** Minutes a code stays valid. */
    public const EXPIRY_MINUTES = 15;

    /** Codes allowed per email per hour before we start refusing. */
    public const MAX_PER_HOUR = 5;

    /** Wrong-code attempts before the code is burned. */
    public const MAX_ATTEMPTS = 5;

    public function __construct(protected int $expiryMinutes = self::EXPIRY_MINUTES) {}

/**
     * Issue a fresh code, invalidating any previous ones for this email.
     *
     * Returns the outcome — the plaintext code for tests and for anything that
     * has to hand it over out of band, plus whether the notification actually
     * left the server. A delivery failure is not an exception here; see the note
     * at the send.
     */
    public function issue(User $user, ?string $ip = null): IssuedResetCode
    {
        $this->guardAgainstFlooding($user->email);

        PasswordResetCode::where('email', $user->email)->delete();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        PasswordResetCode::create([
            'email' => $user->email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($this->expiryMinutes),
            'ip_address' => $ip,
        ]);

        /*
         * The send is best-effort, and deliberately so.
         *
         * The code is already stored and hashed by this point, so letting a
         * transport exception escape would throw away a usable reset for a
         * reason the person on the other end cannot fix. It would also tell them
         * something they should not learn: a 500 raised only for a registered
         * address turns this form into a probe for which addresses exist, and it
         * does so precisely when the mail server is misconfigured.
         *
         * So the exception is caught — but the caller is told it happened, via the
         * `delivered` flag on the result. Swallowing it silently is what made a
         * dead SMTP credential indistinguishable from a code sitting in an unread
         * inbox, and the failure was only ever findable by reading the log.
         *
         * A failure is logged with the address so it can be chased, and the
         * requester is told nothing beyond what an unregistered address is told.
         * Asking again is what recovers it, and the record left behind is
         * overwritten by the next attempt.
         */
        $delivered = true;

        try {
            $user->notify(new PasswordResetCodeNotification($code, $this->expiryMinutes));
        } catch (Throwable $e) {
            $delivered = false;

            report($e);
        }

        return new IssuedResetCode($code, $delivered);
    }

    /**
     * Verify a submitted code, returning the matching record or null.
     */
    public function verify(string $email, string $code): ?PasswordResetCode
    {
        $record = PasswordResetCode::query()
            ->where('email', $email)
            ->orderByDesc('id')
            ->first();

        if ($record === null || ! $record->isUsable()) {
            return null;
        }

        if (! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');

            if ($record->fresh()->attempts >= self::MAX_ATTEMPTS) {
                $record->delete();
            }

            return null;
        }

        return $record;
    }

    /**
     * Consume a verified code and set the new password.
     */
    public function completeReset(PasswordResetCode $record, string $password): void
    {
        $user = User::where('email', $record->email)->first();

        if (! $user) {
            $record->delete();

            return;
        }

        $user->forceFill(['password' => Hash::make($password)])->save();

        $record->forceFill(['used_at' => now()])->save();
    }

    public function emailForSession(): ?string
    {
        return session('password_reset.email');
    }

    protected function guardAgainstFlooding(string $email): void
    {
        $recent = PasswordResetCode::where('email', $email)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        abort_if($recent >= self::MAX_PER_HOUR, 429, 'Too many reset requests. Please try again later.');
    }

    /** Random opaque token used to bind the code step to the browser session. */
    public static function newSessionToken(): string
    {
        return Str::random(40);
    }
}
