<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Customer Flow 3 — Forgot Password.
 *
 * Four steps held in the session:
 *   1. email  → 2. six-digit code  → 3. new password  → 4. confirm password
 */
class PasswordResetLinkController extends Controller
{
    public function __construct(protected PasswordResetService $service) {}

    /* ------------------------------------------------------------------ */
    /* Step 1 — email                                                     */
    /* ------------------------------------------------------------------ */

    public function create(): View|RedirectResponse
    {
        if ($this->hasStarted()) {
            return redirect()->route('password.code');
        }

        return view('auth.passwords.email', ['panelTitle' => 'Account<br>Recovery', 'panelScript' => 'back to glowing in a moment.']);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        $email = strtolower(trim($data['email']));
        $user = User::where('email', $email)->first();

        $issued = $user ? $this->service->issue($user, $request->ip()) : null;

        // Always advance, so the form cannot be used to discover which
        // email addresses are registered.
        $request->session()->put('password_reset', [
            'email' => $email,
            'token' => PasswordResetService::newSessionToken(),
        ]);

        /*
         * One message for both cases, deliberately.
         *
         * These two used to differ — a registered address was told "we sent a
         * 6-digit code", an unknown one "if that email is registered…". That is
         * the whole enumeration oracle the redirect above exists to close, so
         * the wording is now identical whether or not an account matched.
         */
        $redirect = redirect()->route('password.code');

        /*
         * …with one exception, and it is not about the account.
         *
         * A rejected SMTP credential used to produce the same cheerful "it's on
         * its way" as a real send, because `issue()` reported nothing back. The
         * code was written to the database and the mail never left the server, so
         * the page advanced to a code box and waited for a message that could not
         * arrive — which reads to the person as "check your spam", sends them off
         * to wait fifteen minutes for a code that was never sent, and hides a dead
         * mail configuration behind a plausible story. That is the exact report
         * this branch exists for.
         *
         * Note the cost honestly: this warning fires only for an address that
         * matched, so while SMTP is broken it does reveal that an address is
         * registered. It cannot be avoided without attempting a real send to every
         * typed address, which would email strangers who used a reset form. The
         * trade is worth it because the alternative is a silent failure that
         * presents as working software, and the window is only open while the mail
         * server is already down for everyone.
         */
        if ($issued !== null && ! $issued->delivered) {
            return $redirect->with('toast', [
                'type' => 'warning',
                'message' => 'We could not send the verification email. Please try again in a moment.',
            ]);
        }

        return $redirect->with('status', 'If that email is registered, a 6-digit verification code is on its way.');
    }

    /* ------------------------------------------------------------------ */
    /* Step 2 — verification code                                         */
    /* ------------------------------------------------------------------ */

    public function code(): View|RedirectResponse
    {
        if (! $this->hasStarted()) {
            return redirect()->route('password.request');
        }

        return view('auth.passwords.code', ['panelTitle' => 'Confirm<br>It Is You', 'panelScript' => 'a quick code, then you are in.']);
    }

    public function verifyCode(Request $request): RedirectResponse
    {
        $this->assertStarted();

        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $email = session('password_reset.email');

        $record = $this->service->verify($email, $data['code']);

        if ($record === null) {
            throw ValidationException::withMessages([
                'code' => 'That code is invalid or has expired. Please request a new one.',
            ]);
        }

        $request->session()->put('password_reset.verified', true);
        $request->session()->put('password_reset.record_id', $record->id);

        return redirect()->route('password.reset');
    }

    /* ------------------------------------------------------------------ */
    /* Steps 3 & 4 — new password + confirmation                          */
    /* ------------------------------------------------------------------ */

    public function reset(): View|RedirectResponse
    {
        $this->assertVerified();

        return view('auth.passwords.reset', ['panelTitle' => 'Choose a<br>Stronger Secret', 'panelScript' => 'then you are back in.']);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->assertVerified();

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $record = \App\Models\PasswordResetCode::findOrFail(session('password_reset.record_id'));

        $this->service->completeReset($record, $data['password']);

        $email = $record->email;
        $request->session()->forget('password_reset');

        // Sign the customer straight in.
        Auth::login(User::where('email', $email)->first());
        $request->session()->regenerate();

        return redirect()->route('home')
            ->with('status', 'Your password has been reset successfully.');
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                            */
    /* ------------------------------------------------------------------ */

    protected function hasStarted(): bool
    {
        return session()->has('password_reset.email');
    }

    protected function assertStarted(): void
    {
        abort_unless($this->hasStarted(), 403, 'Start the password reset flow first.');
    }

    protected function assertVerified(): void
    {
        $this->assertStarted();

        abort_unless(session('password_reset.verified') === true, 403, 'Verify your code first.');
    }
}
