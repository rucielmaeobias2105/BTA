<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks every authenticated response as uncacheable.
 *
 * The problem this solves is the browser's Back button. Signing out invalidates
 * the session, so the *next request* for a protected page correctly redirects to
 * the login screen — but the page itself may already be sitting in the
 * browser's back/forward cache or on disk from when it was served. Pressing Back
 * after a logout then redraws that authenticated HTML, with working links and a
 * signed-in navbar, until the user happens to click something.
 *
 * Two halves close that window:
 *
 *   - server: the headers below, which tell the browser not to store this
 *     response at all, so there is nothing to redraw; and
 *   - client: `sessionSync` in `resources/js/app.js`, which watches for the
 *     identity changing and takes a stale tab to the login screen — that covers
 *     the bfcache entry a browser had already made before these headers existed.
 *
 * Applied to both authenticated groups — the customer `web` guard and the
 * separate `admin` guard — because the leak is identical on either side and the
 * panel is the more damaging of the two to expose.
 *
 * Public pages are deliberately left alone. A marketing page is meant to be
 * cached, and telling a browser not to store it costs bandwidth for nothing.
 *
 * The headers are set on the way out rather than by mutating the request, so
 * this composes with any other middleware that also touches the response —
 * `AddQueuedCookiesToResponse` and `StartSession` both append to it, and neither
 * of them strips headers a later middleware added.
 */
class PreventAuthenticatedCaching
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        /*
         * `no-store` is the one that matters: it forbids writing the response to
         * any storage at all, which is what keeps the Back button from having
         * anything to show. The rest are belt and braces for proxies and for
         * older browsers that honour the weaker directives on their own:
         *
         *   no-cache        — revalidate before every reuse.
         *   no-cache, no-store, must-revalidate, private — the full set, spelled
         *                     the way it is expected to appear so a reviewer can
         *                     see each part rather than inferring it.
         *   Pragma          — HTTP/1.0 clients, including some corporate proxies.
         *   Expires         — a date already in the past, for anything that
         *                     pre-dates Cache-Control.
         *
         * `private` rather than `public`: this is per-account content, so a shared
         * proxy must never be told it may hand it to the next person.
         */
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Wed, 11 Jan 1984 05:00:00 GMT');

        return $response;
    }
}