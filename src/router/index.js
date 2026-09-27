import { createRouter, createWebHistory } from 'vue-router';

/**
 * Scroll handling.
 *
 * Mirrors the browser's own behaviour: a new page starts at the top, but going
 * back or forward restores where the visitor was. Hash links (the home page's
 * `/#offers` promo anchor) are left to the browser to resolve.
 */
function scrollBehavior(to, from, savedPosition) {
    if (savedPosition) return savedPosition;
    if (to.hash) return { el: to.hash, behavior: 'smooth' };

    return { top: 0 };
}

/**
 * Routes.
 *
 * The paths mirror `routes/web.php` one for one, so every URL that worked under
 * Laravel still resolves. Route `meta.title` replaces the `@section('title')`
 * each Blade view declared; `meta.layout` picks the shell that used to be
 * named by `@extends`.
 */

const routes = [
    /* ---------------------------------------------------------------- */
    /* Public                                                            */
    /* ---------------------------------------------------------------- */
    {
        path: '/',
        name: 'home',
        component: () => import('@/pages/HomePage.vue'),
        meta: { title: 'Home', layout: 'customer' },
    },
    {
        path: '/about',
        name: 'about',
        component: () => import('@/pages/AboutPage.vue'),
        meta: { title: 'About', layout: 'customer' },
    },
    {
        path: '/contact',
        name: 'contact.create',
        component: () => import('@/pages/ContactPage.vue'),
        meta: { title: 'Contact', layout: 'customer' },
    },
    {
        path: '/services',
        name: 'services.index',
        component: () => import('@/pages/services/ServicesIndexPage.vue'),
        meta: { title: 'Services', layout: 'customer' },
    },
    {
        path: '/services-grid',
        name: 'services.refined',
        component: () => import('@/pages/services/ServicesRefinedPage.vue'),
        meta: { title: 'Refined Grid View', layout: 'customer' },
    },
    {
        // Declared after `/services-grid` so the literal path is not swallowed
        // by the slug parameter, mirroring the note in routes/web.php.
        path: '/services/:slug',
        name: 'services.show',
        component: () => import('@/pages/services/ServiceShowPage.vue'),
        meta: { title: 'Service', layout: 'customer' },
    },
    {
        path: '/terms/:category',
        name: 'terms.show',
        component: () => import('@/pages/customer/terms/TermsShowPage.vue'),
        meta: { title: 'Terms', layout: 'customer' },
    },

    /* ---------------------------------------------------------------- */
    /* Auth                                                              */
    /* ---------------------------------------------------------------- */
    {
        path: '/register',
        name: 'register',
        component: () => import('@/pages/auth/RegisterPage.vue'),
        meta: { title: 'Log In', layout: 'guest' },
    },
    {
        path: '/login',
        name: 'login',
        component: () => import('@/pages/auth/LoginPage.vue'),
        meta: { title: 'Log In', layout: 'guest' },
    },
    {
        path: '/password',
        name: 'password.request',
        component: () => import('@/pages/auth/PasswordEmailPage.vue'),
        meta: { title: 'Forgot Password', layout: 'guest' },
    },
    {
        path: '/password/code',
        name: 'password.code',
        component: () => import('@/pages/auth/PasswordCodePage.vue'),
        meta: { title: 'Verify Code', layout: 'guest' },
    },
    {
        path: '/password/reset',
        name: 'password.reset',
        component: () => import('@/pages/auth/PasswordResetPage.vue'),
        meta: { title: 'Reset Password', layout: 'guest' },
    },

    /* ---------------------------------------------------------------- */
    /* Customer                                                          */
    /* ---------------------------------------------------------------- */
    {
        path: '/book',
        name: 'appointments.create',
        component: () => import('@/pages/customer/appointments/AppointmentCreatePage.vue'),
        meta: { title: 'Book Appointment', layout: 'customer' },
    },
    {
        path: '/book/slots',
        name: 'appointments.slots',
        component: () => import('@/pages/NotFoundPage.vue'),
        meta: { title: 'Not Found', layout: 'customer' },
    },
    {
        path: '/dashboard',
        name: 'dashboard',
        component: () => import('@/pages/customer/CustomerDashboardPage.vue'),
        meta: { title: 'Dashboard', layout: 'customer' },
    },
    {
        path: '/appointments',
        name: 'appointments.index',
        component: () => import('@/pages/customer/appointments/AppointmentIndexPage.vue'),
        meta: { title: 'My Appointments', layout: 'customer' },
    },
    {
        path: '/appointments/:id/cancel',
        name: 'appointments.cancel',
        component: () => import('@/pages/customer/appointments/AppointmentCancelPage.vue'),
        meta: { title: 'Cancel Appointment', layout: 'customer' },
    },
    {
        path: '/appointments/:id/reschedule',
        name: 'appointments.reschedule',
        component: () => import('@/pages/customer/appointments/AppointmentReschedulePage.vue'),
        meta: { title: 'Reschedule Appointment', layout: 'customer' },
    },
    {
        path: '/appointments/:id/rate',
        name: 'appointments.rate.create',
        component: () => import('@/pages/customer/appointments/AppointmentRatePage.vue'),
        meta: { title: 'Rate Your Visit', layout: 'customer' },
    },
    {
        path: '/appointments/:id',
        name: 'appointments.show',
        component: () => import('@/pages/customer/appointments/AppointmentShowPage.vue'),
        meta: { title: 'Appointment', layout: 'customer' },
    },
    {
        path: '/notifications',
        name: 'notifications.index',
        component: () => import('@/pages/customer/notifications/NotificationsIndexPage.vue'),
        meta: { title: 'Notifications', layout: 'customer' },
    },
    {
        path: '/profile',
        name: 'profile.edit',
        component: () => import('@/pages/customer/profile/ProfileEditPage.vue'),
        meta: { title: 'Profile', layout: 'customer' },
    },

    /* ---------------------------------------------------------------- */
    /* Admin                                                             */
    /* ---------------------------------------------------------------- */
    {
        path: '/admin/login',
        name: 'admin.login',
        component: () => import('@/pages/auth/AdminLoginPage.vue'),
        meta: { title: 'Log In', layout: 'guest' },
    },
    {
        path: '/admin',
        name: 'admin.dashboard',
        component: () => import('@/pages/admin/AdminDashboardPage.vue'),
        meta: { title: 'Dashboard', layout: 'admin' },
    },
    {
        path: '/admin/appointments',
        name: 'admin.appointments.index',
        component: () => import('@/pages/admin/appointments/AdminAppointmentsIndexPage.vue'),
        meta: { title: 'Appointments', layout: 'admin' },
    },
    {
        path: '/admin/appointments/:id',
        name: 'admin.appointments.show',
        component: () => import('@/pages/admin/appointments/AdminAppointmentsShowPage.vue'),
        meta: { title: 'Appointment', layout: 'admin' },
    },
    {
        path: '/admin/appointments/:id/edit',
        name: 'admin.appointments.edit',
        component: () => import('@/pages/admin/appointments/AdminAppointmentsEditPage.vue'),
        meta: { title: 'Edit Appointment', layout: 'admin' },
    },
    {
        path: '/admin/calendar',
        name: 'admin.calendar.index',
        component: () => import('@/pages/admin/calendar/AdminCalendarPage.vue'),
        meta: { title: 'Calendar', layout: 'admin' },
    },
    {
        path: '/admin/catalog',
        name: 'admin.catalog.index',
        component: () => import('@/pages/admin/catalog/AdminCatalogPage.vue'),
        meta: { title: 'Catalog', layout: 'admin' },
    },
    {
        path: '/admin/catalog/:type/:id/edit',
        name: 'admin.catalog.edit',
        component: () => import('@/pages/admin/catalog/AdminCatalogEditPage.vue'),
        meta: { title: 'Edit Catalog', layout: 'admin' },
    },
    {
        path: '/admin/services',
        name: 'admin.services.index',
        component: () => import('@/pages/admin/services/AdminServicesIndexPage.vue'),
        meta: { title: 'Services', layout: 'admin' },
    },
    {
        path: '/admin/services/create',
        name: 'admin.services.create',
        component: () => import('@/pages/admin/services/AdminServiceFormPage.vue'),
        meta: { title: 'New Service', layout: 'admin' },
    },
    {
        path: '/admin/services/:id/variants',
        name: 'admin.services.variants',
        component: () => import('@/pages/admin/services/AdminServiceVariantsPage.vue'),
        meta: { title: 'Service Variants', layout: 'admin' },
    },
    {
        path: '/admin/services/:id/edit',
        name: 'admin.services.edit',
        component: () => import('@/pages/admin/services/AdminServiceFormPage.vue'),
        meta: { title: 'Edit Service', layout: 'admin' },
    },
    {
        path: '/admin/inventory',
        name: 'admin.inventory.index',
        component: () => import('@/pages/admin/inventory/AdminInventoryIndexPage.vue'),
        meta: { title: 'Inventory', layout: 'admin' },
    },
    {
        path: '/admin/inventory/create',
        name: 'admin.inventory.create',
        component: () => import('@/pages/admin/inventory/AdminInventoryFormPage.vue'),
        meta: { title: 'New Item', layout: 'admin' },
    },
    {
        path: '/admin/inventory/:id/edit',
        name: 'admin.inventory.edit',
        component: () => import('@/pages/admin/inventory/AdminInventoryFormPage.vue'),
        meta: { title: 'Edit Item', layout: 'admin' },
    },
    {
        path: '/admin/tags',
        name: 'admin.tags.index',
        component: () => import('@/pages/admin/tags/AdminTagsPage.vue'),
        meta: { title: 'Tags', layout: 'admin' },
    },
    {
        path: '/admin/users',
        name: 'admin.users.index',
        component: () => import('@/pages/admin/users/AdminUsersIndexPage.vue'),
        meta: { title: 'Users', layout: 'admin' },
    },
    {
        path: '/admin/users/:id',
        name: 'admin.users.show',
        component: () => import('@/pages/admin/users/AdminUserShowPage.vue'),
        meta: { title: 'User', layout: 'admin' },
    },
    {
        path: '/admin/terms',
        name: 'admin.terms.index',
        component: () => import('@/pages/admin/terms/AdminTermsIndexPage.vue'),
        meta: { title: 'Terms', layout: 'admin' },
    },
    {
        path: '/admin/terms/create',
        name: 'admin.terms.create',
        component: () => import('@/pages/admin/terms/AdminTermFormPage.vue'),
        meta: { title: 'New Terms', layout: 'admin' },
    },
    {
        path: '/admin/terms/:id/edit',
        name: 'admin.terms.edit',
        component: () => import('@/pages/admin/terms/AdminTermFormPage.vue'),
        meta: { title: 'Edit Terms', layout: 'admin' },
    },
    {
        path: '/admin/reviews',
        name: 'admin.reviews.index',
        component: () => import('@/pages/admin/reviews/AdminReviewsPage.vue'),
        meta: { title: 'Reviews', layout: 'admin' },
    },
    {
        path: '/admin/reports',
        name: 'admin.reports.index',
        component: () => import('@/pages/admin/reports/AdminReportsPage.vue'),
        meta: { title: 'Reports', layout: 'admin' },
    },
    {
        path: '/admin/messages',
        name: 'admin.messages.index',
        component: () => import('@/pages/admin/messages/AdminMessagesPage.vue'),
        meta: { title: 'Messages', layout: 'admin' },
    },
    {
        path: '/admin/promos',
        name: 'admin.promos.index',
        component: () => import('@/pages/admin/promos/AdminPromosIndexPage.vue'),
        meta: { title: 'Promos', layout: 'admin' },
    },
    {
        path: '/admin/promos/create',
        name: 'admin.promos.create',
        component: () => import('@/pages/admin/promos/AdminPromoFormPage.vue'),
        meta: { title: 'New Promo', layout: 'admin' },
    },
    {
        path: '/admin/promos/:id/edit',
        name: 'admin.promos.edit',
        component: () => import('@/pages/admin/promos/AdminPromoFormPage.vue'),
        meta: { title: 'Edit Promo', layout: 'admin' },
    },
    {
        path: '/admin/profile',
        name: 'admin.profile.edit',
        component: () => import('@/pages/admin/profile/AdminProfilePage.vue'),
        meta: { title: 'Profile', layout: 'admin' },
    },

    /* ---------------------------------------------------------------- */
    /* Fallback                                                          */
    /* ---------------------------------------------------------------- */
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('@/pages/NotFoundPage.vue'),
        meta: { title: 'Not Found', layout: 'customer' },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior,
});

const SUFFIX = {
    admin: 'Admin',
    customer: '',
};

/**
 * Sets `document.title` from `meta.title`, the way each layout used to compose
 * it from `@section('title')`. The admin shell appended "Admin" to the name.
 */
router.afterEach((to) => {
    const section = String(to.meta.title ?? '');
    const layout = to.meta.layout ?? 'customer';
    const base = import.meta.env.VITE_APP_NAME ?? 'Balai ti Arjud';

    document.title = section ? `${section.toUpperCase()} | ${base}${SUFFIX[layout] ?? ''}` : base;
});

export default router;
