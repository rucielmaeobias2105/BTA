<?php

namespace App\Http\Middleware;

use App\Support\IntendedUrl;
use Illuminate\Auth\Middleware\Authenticate;

/**
 * Guards the customer pages, and remembers where a rejected guest was going.
 *
 * The framework's `Authenticate` in all but one respect: it also notes the
 * destination before turning the guest away, under this guard's own key. See
 * App\Support\IntendedUrl for why the destination has to be scoped.
 *
 * Why this is a subclass rather than a middleware sitting in front of `auth`:
 * `Illuminate\Routing\SortedMiddleware` orders route middleware against a fixed
 * priority list, and the framework's `Authenticate` is on it while an ordinary
 * application middleware is not. Anything added "before `auth`" in the route
 * group is therefore sorted *behind* it — and `auth` throws on the spot, so a
 * middleware placed there never runs at all. Overriding `unauthenticated()` puts
 * the write on the only path that is guaranteed to execute: the moment the
 * decision to reject the request has been made.
 *
 * The admin panel's equivalent already had a hand-written guard to hook into,
 * which is why this class exists only for the customer side — see
 * AuthenticateAdmin.
 */
class AuthenticateCustomer extends Authenticate
{
    protected function unauthenticated($request, array $guards)
    {
        if (! $request->expectsJson() && ! $request->ajax()) {
            IntendedUrl::remember($request, 'customer');
        }

        parent::unauthenticated($request, $guards);
    }
}
