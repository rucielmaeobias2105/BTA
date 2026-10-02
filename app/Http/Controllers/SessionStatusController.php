<?php

namespace App\Http\Controllers;

use App\Support\SessionIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Is this browser still signed in, and as whom?" — one key, for the client.
 *
 * Exists for the back/forward cache. When a page comes back from the bfcache it
 * was never re-requested, so it cannot have noticed that a logout happened in
 * another tab; the browser redraws it exactly as it was served. `sessionSync` in
 * `resources/js/app.js` calls this route on `pageshow` and sends the tab to the
 * login screen when the answer is no.
 *
 * What it deliberately does NOT return is any of the identity itself — no id, no
 * name, no email, no session payload. It answers a yes/no and names the guard,
 * because that is all the caller needs and it means the route can sit outside the
 * authenticated middleware groups (a browser that has just been logged out is
 * exactly the browser it has to serve).
 *
 * That is also why the answer is cached hard: a stale "yes" here is the one
 * response that would defeat the entire mechanism.
 */
class SessionStatusController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $identity = SessionIdentity::current();

        $response = response()->json([
            'authenticated' => $identity !== null,
            // 'admin' | 'web' | 'guest' — the same three values the `bta-session`
            // meta tag uses, so the client compares like with like.
            'guard' => $identity['guard'] ?? 'guest',
        ]);

        $this->noStore($response);

        return $response;
    }

    protected function noStore(Response $response): Response
    {
        // See App\Http\Middleware\PreventAuthenticatedCaching for why all four.
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Wed, 11 Jan 1984 05:00:00 GMT');

        return $response;
    }
}