<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * Resolves which nav item should render as "active" from the current route.
 * Keeps the active-state logic out of the Blade partials.
 */
class Nav
{
    /**
     * Customer nav keys, matched against the active route name prefix.
     *
     * @var array<string, string>
     */
    protected const CUSTOMER = [
        'services.index' => 'services',
        'services.refined' => 'refined',
        'appointments.*' => 'appointments',
        'dashboard' => 'dashboard',
        'notifications.*' => 'notifications',
        'profile.*' => 'profile',
        'contact.*' => 'contact',
        'about' => 'about',
        'home' => 'home',
    ];

    /**
     * Admin nav keys.
     *
     * @var array<string, string>
     */
    protected const ADMIN = [
        'admin.dashboard' => 'dashboard',
        'admin.appointments.*' => 'appointments',
        'admin.catalog.*' => 'catalog',
        'admin.services.*' => 'services',
        'admin.inventory.*' => 'inventory',
        'admin.tags.*' => 'tags',
        'admin.calendar.*' => 'calendar',
        'admin.users.*' => 'users',
        'admin.terms.*' => 'terms',
        'admin.reviews.*' => 'reviews',
        'admin.reports.*' => 'reports',
        'admin.promos.*' => 'promos',
        'admin.messages.*' => 'messages',
    ];

    public static function customerCurrent(): ?string
    {
        return static::match(Route::current()?->getName(), self::CUSTOMER);
    }

    public static function adminCurrent(): ?string
    {
        return static::match(Route::current()?->getName(), self::ADMIN);
    }

    /**
     * @param  array<string, string>  $map
     */
    protected static function match(?string $routeName, array $map): ?string
    {
        if ($routeName === null) {
            return null;
        }

        // Exact matches win over wildcards.
        foreach ($map as $pattern => $key) {
            if ($pattern === $routeName) {
                return $key;
            }
        }

        foreach ($map as $pattern => $key) {
            if (str_ends_with($pattern, '.*') && str_starts_with($routeName, substr($pattern, 0, -1))) {
                return $key;
            }
        }

        return null;
    }
}
