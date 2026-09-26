<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts an admin route to the roles that hold the given abilities.
 *
 * Laravel's built-in `can:` middleware authorises against the *default*
 * guard, which for this application is the customer `web` guard. Admins
 * authenticate on the separate `admin` guard, so `can:` would see no user and
 * deny every admin. This middleware reads the admin guard directly and
 * delegates the decision to AdminRole, keeping AdminRole::abilities() as the
 * single source of truth shared with the `admin.*` Gates used by the views.
 *
 * Usage: ->middleware('admin.role:admin.catalog.manage')
 */
class EnsureAdminRole
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $admin = Auth::guard('admin')->user();

        foreach ($abilities as $ability) {
            // Routes are written `admin.role:admin.catalog.manage` so they
            // read the same as the Gate name; AdminRole keys off the bare
            // ability.
            $ability = str_starts_with($ability, 'admin.')
                ? substr($ability, strlen('admin.'))
                : $ability;

            if (! $admin?->role->can($ability)) {
                abort(403, 'Your admin role does not have access to this area.');
            }
        }

        return $next($request);
    }
}
