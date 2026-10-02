<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Models\Admin;
use App\Support\IntendedUrl;
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
            // The shared auth layout defaults to the narrow card for customer
            // forms; the staff portal takes the full-width one.
            'wideCard' => true,
            // The shared auth layout defaults to the customer-facing feature
            // row; the staff portal lists what an admin actually manages.
            'features' => [
                ['Manage Appointments', 'heroicon-o-calendar-days'],
                ['Track Customers', 'heroicon-o-user-group'],
                ['Monitor Inventory', 'heroicon-o-cube-transparent'],
                ['Grow Your Business', 'heroicon-o-chart-bar'],
            ],
        ]);
    }

    public function store(AdminLoginRequest $request): RedirectResponse
    {
        $this->ensureIsNotRateLimited($request);

        $username = $request->string('username')->toString();

        $admin = Admin::where('username', $username)
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

        // One account at a time in this browser — see the note in
        // `AuthenticatedSessionController::store()`. The two guards share a
        // session cookie, so without this a customer session and an admin
        // session coexisted and either panel's Log Out button left the other
        // still signed in.
        Auth::guard('web')->logout();

        Auth::guard('admin')->login($admin, $request->boolean('remember'));
        $request->session()->regenerate();

        $admin->forceFill(['last_login_at' => now()])->saveQuietly();

        /*
         * `IntendedUrl::pull()` rather than `redirect()->intended()`.
         *
         * This is the bug that sent a correctly signed-in staff member to the
         * *customer* dashboard. Both guards share one session cookie, so a guest
         * bounced off `/appointments` — which is what happens the moment a tab
         * follows a stale identity — left `/appointments` in Laravel's shared
         * `url.intended`, and this read it back. The staff member then landed on
         * a page the customer guard owns, was bounced to `/login` for being a
         * guest there, and appeared to have signed in to nothing.
         *
         * Scoping the destination to this guard is the fix; see
         * App\Support\IntendedUrl.
         */
        return redirect()->to(IntendedUrl::pull($request, 'admin', route('admin.dashboard')));
    }

    /**
     * Sign out of the staff portal.
     *
     * The same three-part logout as the customer side: the guard is cleared and
     * the session invalidated so the next request redirects, this response is
     * marked uncacheable so the Back button has nothing to redraw, and
     * `sessionSync` announces `guest` from the login page this redirects to so
     * any other tab showing the panel is taken to that login screen too.
     *
     * The extra force beyond the customer case: this tab must not be able to
     * redraw the panel at all. `PreventAuthenticatedCaching` already stamps
     * every authenticated response; it is applied again here because `/logout`
     * is the boundary between the two states rather than a page inside one, so
     * its own response must not become a Back-button candidate.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $response = redirect()->route('admin.login');

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Wed, 11 Jan 1984 05:00:00 GMT');

        return $response;
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
