<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Customer Flow 2 — Login.
 *
 * The field is labelled "Username or Email" per the mockup and matches either
 * column. Attempts are rate limited per identifier + IP.
 */
class AuthenticatedSessionController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $this->ensureIsNotRateLimited($request);

        $login = $request->string('login')->toString();

        $user = User::query()
            ->where('email', $login)
            ->orWhere('username', $login)
            ->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            RateLimiter::hit($this->throttleKey($request), self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'login' => 'These credentials do not match our records.',
            ]);
        }

        if (! $user->is_active) {
            RateLimiter::hit($this->throttleKey($request), self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'login' => 'Your account has been deactivated. Please contact us.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(LoginRequest $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'login' => "Too many login attempts. Please try again in {$seconds} seconds.",
        ]);
    }

    private function throttleKey(LoginRequest $request): string
    {
        return Str::transliterate(Str::lower($request->string('login')->toString()).'|'.$request->ip());
    }
}
