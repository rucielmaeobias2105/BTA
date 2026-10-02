<?php

namespace App\Providers;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * Every entry in AdminRole::abilities() becomes an `admin.<ability>` Gate.
     * One definition then drives both halves of enforcement: the
     * `admin.role:*` middleware on the admin routes and the `@can` directives
     * in the views, so a button is never shown to a role the server would
     * reject anyway.
     */
    public function boot(): void
    {
        foreach (AdminRole::abilities() as $ability) {
            // The parameter is untyped and defaults to null on purpose. Gate
            // passes the *default* guard's user as the first argument, and the
            // default guard here is the customer `web` guard — so as soon as
            // somebody is signed in as a customer, that argument is a User, and
            // a `?Admin` hint would fatal with a TypeError before this body
            // ever ran. Keeping it untyped means Gate can still evaluate the
            // gate for a guest, and the decision reads the guard that actually
            // owns the panel.
            Gate::define('admin.'.$ability, function ($admin = null) use ($ability): bool {
                $admin = Auth::guard('admin')->user();

                return $admin instanceof Admin && $admin->role->can($ability);
            });
        }
    }
}
