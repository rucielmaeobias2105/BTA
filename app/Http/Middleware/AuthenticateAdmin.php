<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the whole admin panel. Uses the dedicated `admin` guard so an
 * authenticated customer can never reach these routes.
 */
class AuthenticateAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin) {
            return redirect()->guest(route('admin.login'));
        }

        if (! $admin->is_active) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                ->withErrors(['email' => 'This admin account has been deactivated.']);
        }

        return $next($request);
    }
}
