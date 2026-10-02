<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The staff portal's password reset — the counterpart to the customer's four-step
 * wizard, and built on Laravel's broker rather than a bespoke one.
 *
 * Why the broker, when the customer side rolls its own six-digit code:
 *
 *   - the customer flow needs the code, because a customer can ask the front desk
 *     to confirm who they are. An admin cannot, so a mailed link is both the
 *     stronger mechanism and the simpler one.
 *   - `password_reset_tokens` already exists and is keyed on email, so the token
 *     table, the expiry, the throttle and the "was this account found" rule all
 *     come from the framework and are already correct.
 *
 * The one thing that has to be configured is the broker itself. Admins are on
 * their own guard with their own provider, and the default broker reads the
 * `users` provider — so without an `admins` entry in `config/auth.php` a reset
 * link would be issued against the customers table and every admin's request
 * would report an unknown address. See the `passwords` block there.
 *
 * Built on `Password::broker('admins')` rather than the `Password` facade's
 * default, so the broker named here and the one the notification uses cannot
 * drift.
 */
class AdminPasswordResetLinkController extends Controller
{
    /**
     * The broker name.
     *
     * Read from the model rather than spelled out again: the model is the thing
     * that actually calls the broker when it mails the link, so one constant
     * there is one place to get right. `config/auth.php` carries a matching
     * `passwords.admins` entry.
     */
    public const BROKER = Admin::PASSWORD_BROKER;

    /* ------------------------------------------------------------------ */
    /* Step 1 — request a link                                            */
    /* ------------------------------------------------------------------ */

    /**
     * The "Forgot password?" form.
     *
     * Takes an email rather than a username, because the link has to go somewhere
     * and an admin's address is what is on file. The customer wizard asks for the
     * same thing, which keeps the two recovery flows consistent for whoever ends
     * up doing the recovery.
     */
    public function create(Request $request): View|RedirectResponse
    {
        // Someone already signed in has no business on this screen. The customer
        // side sends them to their dashboard; an admin goes to the panel.
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.passwords.email');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        Admin::forgetResetNotificationFailure();

        $status = Password::broker(self::BROKER)->sendResetLink(
            ['email' => strtolower(trim($data['email']))]
        );

        /*
         * The same message either way.
         *
         * A different answer for "no such admin" would turn this form into a way
         * to discover which addresses are registered, and it is the same mistake
         * the customer flow avoids for the same reason. The status *is* still
         * used below — to decide whether the page says "check your inbox" or
         * "try again in a minute" for the throttle case, which is about this
         * request's own rate limit rather than about the account.
         */
        if ($status === Password::RESET_THROTTLED) {
            return back()->withErrors([
                'email' => 'A reset email was just sent to that address. Please wait a minute before asking for another.',
            ]);
        }

        $redirect = redirect()->route('admin.password.sent');

        /*
         * Same correction the customer flow got, and for the same reason.
         *
         * A rejected SMTP credential used to escape the broker as a 500, so an
         * admin who filled the form in correctly was shown a server error and no
         * explanation. `Admin::sendPasswordResetNotification()` now sends quietly
         * and records the outcome, so the honest options are both available: say
         * the link could not be sent, or say nothing at all.
         *
         * As with the customer flow, this only fires for an address that matched,
         * so while the mail server is down it does reveal that an address belongs
         * to a staff account. The window is only open while mail is already broken
         * for everyone, and the alternative was a 500.
         */
        if (Admin::resetNotificationFailed()) {
            return $redirect->with('toast', [
                'type' => 'warning',
                'message' => 'We could not send the reset email. Please try again in a moment.',
            ]);
        }

        return $redirect->with('status', 'If that address belongs to a staff account, a reset link is on its way.');
    }

    /** The "check your inbox" page, so the request does not dead-end. */
    public function sent(): View|RedirectResponse
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.passwords.sent');
    }

    /* ------------------------------------------------------------------ */
    /* Step 2 — the link in the email                                      */
    /* ------------------------------------------------------------------ */

    /**
     * The reset form, reached from the link in the email.
     *
     * The token is in the URL and is never re-read from the session: this screen
     * has to survive a page refresh and work in a tab that was opened before the
     * email arrived, and either would break a flow that stashed the token.
     */
    public function edit(Request $request, string $token): View|RedirectResponse
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        $email = (string) $request->query('email');

        // A token with no address cannot be used, and a dead link that renders a
        // form which then fails on submit is worse than saying so here.
        if ($email === '') {
            return redirect()
                ->route('admin.password.request')
                ->withErrors(['email' => 'That reset link is incomplete. Please request a new one.']);
        }

        return view('admin.auth.passwords.reset', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::broker(self::BROKER)->reset(
            [
                'email' => strtolower(trim($data['email'])),
                'password' => $data['password'],

                /*
                 * `confirmed` has already checked that the two fields match by the
                 * time this runs, and it does not put `password_confirmation` into
                 * the validated data — so the broker is handed the same value
                 * twice. Reading `$data['password_confirmation']` here would be an
                 * undefined array key on every successful reset.
                 */
                'password_confirmation' => $data['password'],

                'token' => $data['token'],
            ],
            /*
             * Called on success.
             *
             * Signs the admin straight in, so the person who just recovered the
             * account does not then have to type the new password a second time —
             * and so they cannot end up staring at the login form wondering whether
             * it worked.
             *
             * `session()->regenerate()` rather than `$request->session()` because
             * the closure the broker takes is invoked with the model and the
             * plaintext password only; reaching for `$request` out here would be a
             * use of a variable that is not in scope inside it.
             */
            function (Admin $admin, string $password) {
                $admin->forceFill([
                    'password' => $password,
                    // A fresh remember token: the old one was issued against the
                    // old password and should not still be a valid credential.
                    'remember_token' => Str::random(60),
                ])->save();

                Auth::guard('admin')->login($admin);

                session()->regenerate();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => __($status),
            ]);
        }

        return redirect()
            ->route('admin.dashboard')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Your password has been reset. You are now signed in.',
            ]);
    }
}