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

        return view('auth.passwords.email');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        $email = strtolower(trim($data['email']));
        $user = User::where('email', $email)->first();

        if ($user) {
            $this->service->issue($user, $request->ip());
        }

        // Always advance, so the form cannot be used to discover which
        // email addresses are registered.
        $request->session()->put('password_reset', [
            'email' => $email,
            'token' => PasswordResetService::newSessionToken(),
        ]);

        return redirect()->route('password.code')
            ->with('status', $user
                ? 'We sent a 6-digit verification code to '.$email.'.'
                : 'If that email is registered, a 6-digit verification code is on its way.');
    }

    /* ------------------------------------------------------------------ */
    /* Step 2 — verification code                                         */
    /* ------------------------------------------------------------------ */

    public function code(): View|RedirectResponse
    {
        if (! $this->hasStarted()) {
            return redirect()->route('password.request');
        }

        return view('auth.passwords.code');
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

        return view('auth.passwords.reset');
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

        return redirect()->route('dashboard')
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
