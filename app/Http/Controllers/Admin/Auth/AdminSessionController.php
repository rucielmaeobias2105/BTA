<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin Flow 1 — Admin Login.
 *
 * Completely separate from customer auth: dedicated `admin` guard, dedicated
 * `admins` table, dedicated rate-limit bucket and its own session namespace.
 */
class AdminSessionController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function create(): View
    {
        return view('admin.auth.login', [
            'panelTitle' => 'Salon Management,<br>At Your Fingertips.',
            'panelScript' => 'every screen in one place.',
        ]);
    }

    public function store(AdminLoginRequest $request): RedirectResponse
    {
        $this->ensureIsNotRateLimited($request);

        $username = $request->string('username')->toString();

        $admin = \App\Models\Admin::where('username', $username)
            ->orWhere('email', strtolower($username))
            ->first();

        if (! $admin || ! Hash::check($request->string('password')->toString(), $admin->password)) {
            RateLimiter::hit($this->throttleKey($request), self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'username' => 'These credentials do not match our records.',
            ]);
        }

        if (! $admin->is_active) {
            RateLimiter::hit($this->throttleKey($request), self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'username' => 'This admin account has been deactivated.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        Auth::guard('admin')->login($admin, $request->boolean('remember'));
        $request->session()->regenerate();

        $admin->forceFill(['last_login_at' => now()])->saveQuietly();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(AdminLoginRequest $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'username' => "Too many login attempts. Please try again in {$seconds} seconds.",
        ]);
    }

    private function throttleKey(AdminLoginRequest $request): string
    {
        return 'admin|'.Str::transliterate(Str::lower($request->string('username')->toString()).'|'.$request->ip());
    }
}
