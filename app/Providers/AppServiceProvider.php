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
            // The nullable ?Admin parameter matters: Gate only calls a
            // callback for a guest when its first parameter is nullable.
            // Without it every gate would be denied, because the panel
            // authenticates on the `admin` guard while Gate resolves its user
            // from the default (`web`) guard. The admin is therefore read
            // from that guard explicitly rather than from the argument.
            Gate::define('admin.'.$ability, function (?Admin $admin) use ($ability): bool {
                $admin = Auth::guard('admin')->user();

                return $admin instanceof Admin && $admin->role->can($ability);
            });
        }
    }
}
