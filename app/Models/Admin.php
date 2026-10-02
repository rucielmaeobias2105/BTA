<?php

namespace App\Models;

use App\Enums\AdminRole;
use App\Notifications\AdminPasswordResetNotification;
use App\Support\SendsNotificationsQuietly;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class Admin extends Authenticatable
{
    use HasFactory;
    use Notifiable;
    use SendsNotificationsQuietly;
    use SoftDeletes;

    /**
     * Whether the most recent reset link failed to send, for the controller to read.
     *
     * Static because the broker builds its own `Admin` instance internally, so the
     * model that swallowed the failure is not the one the controller is holding.
     * A property would read `false` on a different object and the controller would
     * report success while nothing had been sent — the exact bug this records.
     *
     * Set by `sendPasswordResetNotification()` below, which is the hook the broker
     * calls. Reset per request by `forgetResetNotificationFailure()`.
     */
    protected static bool $resetNotificationFailed = false;

    public static function resetNotificationFailed(): bool
    {
        return static::$resetNotificationFailed;
    }

    public static function forgetResetNotificationFailure(): void
    {
        static::$resetNotificationFailed = false;
    }

    protected $table = 'admins';

    /**
     * The password broker for this model.
     *
     * `Illuminate\Foundation\Auth\User` sends through whatever
     * `auth.defaults.password_brokers` names, and that default is `users`. An
     * admin's address is not in the `users` table, so the default broker reports
     * an unknown account for every request and the flow looks broken while
     * claiming to have sent a link.
     *
     * Declared here rather than in the controller because the model is what calls
     * the broker. The controller reads this constant rather than repeating the
     * string, so the two cannot drift — and `config/auth.php` carries a matching
     * `passwords.admins` entry with the same name.
     */
    public const PASSWORD_BROKER = 'admins';

    protected $fillable = [
        'first_name',
        'last_name',
        'username',
        'email',
        'password',
        'role',
        'profile_photo_path',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'role' => AdminRole::class,
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getInitialsAttribute(): string
    {
        return Str::upper(Str::substr((string) $this->first_name, 0, 1).Str::substr((string) $this->last_name, 0, 1));
    }

    public function termsPolicies(): HasMany
    {
        return $this->hasMany(TermsAndCondition::class, 'created_by');
    }

    /**
     * Send the reset link through the *admins* broker, not the default one.
     *
     * `Illuminate\Foundation\Auth\User` — which this extends — sends through
     * whatever `auth.defaults.password_brokers` names, and that default is
     * `users`. An admin's address is not in the `users` table, so the default
     * broker would report an unknown account for every request and the flow would
     * look broken while reporting "we sent you a link".
     *
     * Overridden rather than by changing the global default: customers genuinely
     * do reset through the `users` broker in the framework's own paths, and this
     * panel must not depend on which one an unrelated default happens to be. The
     * broker name is the controller's constant, so the two cannot drift.
     */
    public function sendPasswordResetNotification($token): void
    {
        /*
         * This project's own notification rather than the framework's.
         *
         * `Illuminate\Auth\Notifications\ResetPassword` builds its link to
         * `route('password.reset')` — the customer's four-step wizard, which asks
         * for a six-digit code. An admin following that link would land on a
         * screen that cannot help them, which for a reset link means the flow is
         * broken rather than cosmetically wrong.
         *
         * `AdminPasswordResetNotification` overrides only the URL and the expiry
         * line, and inherits the rest of the framework's wording.
         *
         * Sent quietly, and the outcome recorded rather than thrown.
         *
         * The broker calls this and does not guard it, so a rejected SMTP
         * credential propagated straight out of the request: the admin reset
         * answered 500 where the customer reset answered a cheerful "it's on its
         * way". Neither was right, and they failed in opposite directions — one
         * blamed the person for not checking their inbox, the other showed them a
         * server error for a form they filled in correctly.
         *
         * `notifyQuietly()` logs the real cause at `error`, so the operator still
         * gets the `535` and the address to chase.
         */
        static::$resetNotificationFailed = ! $this->notifyQuietly(
            $this,
            new AdminPasswordResetNotification($token),
            'admin password reset',
        );
    }

    /**
     * The broker's "was this account found" check.
     *
     * Declared so the reset flow can see it: `PasswordBroker` calls this to decide
     * between "sent" and "no such user", and the base `User` declares it anyway.
     * Here it also keeps a soft-deleted admin from being reset — a retired account
     * that could still receive a working link would be able to sign back in.
     */
    public function canResetPassword(): bool
    {
        return ! $this->trashed();
    }
}
