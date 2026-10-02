<?php

use App\Http\Middleware\AuthenticateAdmin;
use App\Http\Middleware\AuthenticateCustomer;
use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\PreventAuthenticatedCaching;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\RedirectIfAuthenticatedAdmin;
use App\Support\ValidationToast;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        /*
         * NOT registered as a middleware *group*. `MiddlewareNameResolver` checks
         * groups before aliases, so registering a group called `auth` or
         * `auth.admin` would shadow the alias of the same name and silently
         * un-guard every protected route. It is aliased as `no.store` instead and
         * applied on the two authenticated route groups in routes/web.php, where
         * it sits alongside `auth` rather than pretending to be it.
         */
        $middleware->alias([
            'no.store' => PreventAuthenticatedCaching::class,
            'auth.customer' => AuthenticateCustomer::class,
            'auth.admin' => AuthenticateAdmin::class,
            'admin.role' => EnsureAdminRole::class,
            'admin.guest' => RedirectIfAuthenticatedAdmin::class,
            'user.active' => EnsureUserIsActive::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));

        // `home`, not the removed customer Dashboard. This is the target for a
        // signed-in customer who asks for the login or register screen.
        $middleware->redirectUsersTo(fn () => route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        /*
         * A failed validation now tells the person through a toast rather than a
         * red block above the form.
         *
         * This is a *flash*, not a response: returning null lets Laravel carry on
         * and do its normal redirect-back-with-the-error-bag, so `old()` and every
         * `@error` still work and the 66 `assertSessionHasErrors` in the suite
         * keep passing. The toast is layered on top of that, and the views no
         * longer render a summary block of their own, so nothing appears twice.
         *
         * Skipped for JSON and AJAX callers, which want the 422 body itself —
         * turning a machine-readable failure into a session flash would leave
         * them with a validation error and no explanation.
         *
         * A Form Request that already picked a tone for this failure keeps it.
         * The booking form raises an amber toast for the minimum-notice rule
         * deliberately, because the customer filled the form in correctly and the
         * date picker already greys that date out — a red toast there reads as a
         * failure on their side. Red is the right default for a blank required
         * field, but it is not right for every rule, and only the request knows
         * which is which. Overwriting unconditionally both discarded that
         * decision and flashed the same sentence twice, once per handler.
         */
        $exceptions->render(function (ValidationException $e, $request) {
            if ($request->expectsJson() || $request->ajax() || ! $request->hasSession()) {
                return null;
            }

            // Already flashed by a Form Request that chose its own tone.
            if ($request->session()->has('toast')) {
                return null;
            }

            $message = ValidationToast::messageFor($e->errors());

            if ($message !== '') {
                $request->session()->flash('toast', [
                    'type' => 'error',
                    'message' => $message,
                ]);
            }

            return null;
        });
    })->create();
