<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
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

        // One account at a time in this browser.
        //
        // The customer and admin guards share a session cookie, so signing in
        // here while signed in there left both guards authenticated at once —
        // the panel was reachable from a customer session and vice versa, and
        // the "Log Out" button in one panel left the other still signed in.
        // Logging the other guard out is what makes "one active role per
        // browser" true on the server; `sessionSync` in `resources/js/app.js`
        // is what makes the other tabs in this browser notice.
        Auth::guard('admin')->logout();

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        /*
         * `IntendedUrl::pull()` rather than `redirect()->intended()`.
         *
         * Both guards share one session cookie, so Laravel's single
         * `url.intended` holds whichever destination was asked for most
         * recently by *either* guard. Reading it here meant a guest who had
         * been bounced off `/admin` was sent there after signing in as a
         * customer — to a page that then bounced them straight back out.
         * Scoping the destination to this guard is what stops the two logins
         * stealing each other's; see App\Support\IntendedUrl.
         */
        return redirect()->to(IntendedUrl::pull($request, 'customer', route('home')));
    }

    /**
     * Sign out.
     *
     * Three things happen here, and each covers a different way a signed-out
     * browser could still be showing a customer's page:
     *
     *   1. the guard is logged out and the session invalidated, so the *next*
     *      request for a protected page redirects to the login screen;
     *   2. this response is marked uncacheable, so the browser does not keep the
     *      logout redirect around either; and
     *   3. `sessionSync` in `resources/js/app.js` announces `guest` from the
     *      login page this redirects to, which is what takes any *other* tab
     *      still showing a customer page to the login screen.
     *
     * The `PreventAuthenticatedCaching` middleware covers (2) for every
     * authenticated response; it is repeated here because `/logout` is behind
     * `auth` on the way in but is not itself an authenticated page — it is the
     * boundary between the two states, so its own response must not be a
     * candidate for the Back button either.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->uncacheable(redirect()->route('home'));
    }

    /**
     * Stamp no-store headers onto a response the browser must not keep.
     *
     * @see \App\Http\Middleware\PreventAuthenticatedCaching for why these four
     *      headers rather than just `Cache-Control: no-store`.
     */
    protected function uncacheable(RedirectResponse $response): RedirectResponse
    {
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Wed, 11 Jan 1984 05:00:00 GMT');

        return $response;
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
