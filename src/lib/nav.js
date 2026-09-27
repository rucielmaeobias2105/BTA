/**
 * Which nav item should render as "active".
 *
 * Ported from `App\Support\Nav`. The Blade partials called
 * `Nav::customerCurrent()` / `Nav::adminCurrent()` and compared the result
 * against a key, which kept the active-state logic out of the markup. The same
 * map is applied here to the current route name.
 */

/** route name (or `prefix.*` pattern) -> nav key. Exact matches beat wildcards. */
const CUSTOMER = {
    'services.index': 'services',
    'services.refined': 'refined',
    'services.show': 'services',
    'appointments.create': 'appointments',
    'appointments.*': 'appointments',
    dashboard: 'dashboard',
    'notifications.*': 'notifications',
    'profile.*': 'profile',
    'terms.*': 'terms',
    'contact.*': 'contact',
    about: 'about',
    home: 'home',
};

const ADMIN = {
    'admin.dashboard': 'dashboard',
    'admin.appointments.*': 'appointments',
    'admin.catalog.*': 'catalog',
    'admin.services.*': 'services',
    'admin.inventory.*': 'inventory',
    'admin.tags.*': 'tags',
    'admin.calendar.*': 'calendar',
    'admin.users.*': 'users',
    'admin.terms.*': 'terms',
    'admin.reviews.*': 'reviews',
    'admin.reports.*': 'reports',
    'admin.promos.*': 'promos',
    'admin.messages.*': 'messages',
    'admin.profile.*': 'profile',
    'admin.login': 'login',
};

function match(routeName, map) {
    if (!routeName) return null;

    for (const [pattern, key] of Object.entries(map)) {
        if (pattern === routeName) return key;
    }

    for (const [pattern, key] of Object.entries(map)) {
        if (pattern.endsWith('.*') && routeName.startsWith(pattern.slice(0, -1))) return key;
    }

    return null;
}

export function customerCurrent(routeName) {
    return match(routeName, CUSTOMER);
}

export function adminCurrent(routeName) {
    return match(routeName, ADMIN);
}
