<?php

use App\Http\Controllers\Admin\AppointmentController as AdminAppointmentController;
use App\Http\Controllers\Admin\Auth\AdminSessionController;
use App\Http\Controllers\Admin\CalendarController;
use App\Http\Controllers\Admin\CatalogController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\ItemTagController;
use App\Http\Controllers\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Admin\PromoController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReviewModerationController;
use App\Http\Controllers\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Admin\TermsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Customer\AppointmentController;
use App\Http\Controllers\Customer\CancelAppointmentController;
use App\Http\Controllers\Customer\DashboardController;
use App\Http\Controllers\Customer\NotificationController;
use App\Http\Controllers\Customer\ProfileController;
use App\Http\Controllers\Customer\RateServiceController;
use App\Http\Controllers\Customer\RescheduleAppointmentController;
use App\Http\Controllers\Customer\TermsController as CustomerTermsController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public / customer routes
|--------------------------------------------------------------------------
*/

Route::get('/', HomeController::class)->name('home');
Route::get('/about', [HomeController::class, 'about'])->name('about');

// Customer Flow 4 — Browse Services (public, read-only)
Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
Route::get('/services/{service}', [ServiceController::class, 'show'])->name('services.show');
// Customer Flow 15 — Refined Grid View (category-filtered)
Route::get('/services-grid', [ServiceController::class, 'refined'])->name('services.refined');

// Customer Flow 12 — Contact Us
Route::get('/contact', [ContactController::class, 'create'])->name('contact.create');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:12,1')->name('contact.store');

// Published T&C, linked from the booking / cancellation / reschedule checkboxes
Route::get('/terms/{category}', CustomerTermsController::class)->name('terms.show');

/*
|--------------------------------------------------------------------------
| Customer auth
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    // Customer Flow 1 — Register
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');

    // Customer Flow 2 — Login
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.store');

    // Customer Flow 3 — Forgot Password (4-step wizard)
    Route::prefix('password')->name('password.')->group(function () {
        Route::get('/', [PasswordResetLinkController::class, 'create'])->name('request');
        Route::post('/', [PasswordResetLinkController::class, 'store'])->middleware('throttle:6,1')->name('email');
        Route::get('/code', [PasswordResetLinkController::class, 'code'])->name('code');
        Route::post('/code', [PasswordResetLinkController::class, 'verifyCode'])->middleware('throttle:12,1')->name('verify');
        Route::get('/reset', [PasswordResetLinkController::class, 'reset'])->name('reset');
        Route::post('/reset', [PasswordResetLinkController::class, 'update'])->name('update');
    });
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Customer — authenticated
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'user.active'])->group(function () {
    // Customer Flow 5 — Book Appointment.
    // Guests may browse the catalogue but must register before booking, so the
    // booking is always tied to an account.
    Route::get('/book', [AppointmentController::class, 'create'])->name('appointments.create');
    Route::post('/book', [AppointmentController::class, 'store'])->name('appointments.store');

    // Customer Flow 13 — Customer Dashboard
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Customer Flow 5 — My Appointments / Transactions
    Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show'])->name('appointments.show');

    // Customer Flow 7 — Cancel Appointment
    Route::get('/appointments/{appointment}/cancel', [CancelAppointmentController::class, 'edit'])
        ->name('appointments.cancel');
    Route::patch('/appointments/{appointment}/cancel', [CancelAppointmentController::class, 'update'])
        ->name('appointments.cancel.update');

    // Customer Flow 8 — Reschedule Appointment
    Route::get('/appointments/{appointment}/reschedule', [RescheduleAppointmentController::class, 'edit'])
        ->name('appointments.reschedule');
    Route::patch('/appointments/{appointment}/reschedule', [RescheduleAppointmentController::class, 'update'])
        ->name('appointments.reschedule.update');

    // Customer Flow 10 — Rate Service
    Route::get('/appointments/{appointment}/rate', [RateServiceController::class, 'create'])
        ->name('appointments.rate.create');
    Route::post('/appointments/{appointment}/rate', [RateServiceController::class, 'store'])
        ->name('appointments.rate.store');

    // Customer Flow 9 — Notifications (read-only)
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead'])
        ->name('notifications.read-all');
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead'])
        ->name('notifications.read');

    // Customer Flow 11 — Profile Management
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile/photo', [ProfileController::class, 'destroyPhoto'])->name('profile.photo.destroy');
});

/*
|--------------------------------------------------------------------------
| Live slot lookup for the booking form's date picker
|--------------------------------------------------------------------------
|
| Left public so service pages can show availability to visitors before they
| commit to logging in.
*/

Route::get('/book/slots', [AppointmentController::class, 'slots'])
    ->middleware('throttle:60,1')
    ->name('appointments.slots');

/*
|--------------------------------------------------------------------------
| Admin auth (separate guard — never shares a session with customers)
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->name('admin.')->group(function () {
    // Admin Flow 1 — Admin Login
    Route::middleware('admin.guest')->group(function () {
        Route::get('/login', [AdminSessionController::class, 'create'])->name('login');
        Route::post('/login', [AdminSessionController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('login.store');
    });

    Route::post('/logout', [AdminSessionController::class, 'destroy'])
        ->middleware('auth.admin')
        ->name('logout');

    /*
    |----------------------------------------------------------------------
    | Admin panel
    |----------------------------------------------------------------------
    |
    | `auth.admin` keeps customers out. The `admin.role:*` middleware then narrows
    | each screen to the roles listed in App\Enums\AdminRole: Super Admin
    | holds everything, Manager runs the salon but cannot edit legal text or
    | destroy customer records, and Staff get the appointment queue plus
    | read-only reference screens.
    |
    */

    Route::middleware('auth.admin')->group(function () {
        // Admin Flow 2 — Dashboard
        Route::get('/', AdminDashboardController::class)
            ->middleware('admin.role:admin.dashboard.view')
            ->name('dashboard');

        // Admin Flow 3 — Appointment Management
        Route::resource('appointments', AdminAppointmentController::class)
            ->except(['create', 'destroy'])
            ->middleware('admin.role:admin.appointments.manage');
        Route::patch('appointments/{appointment}/status', [AdminAppointmentController::class, 'updateStatus'])
            ->middleware('admin.role:admin.appointments.manage')
            ->name('appointments.status');
        Route::patch('appointments/{appointment}/notes', [AdminAppointmentController::class, 'updateNotes'])
            ->middleware('admin.role:admin.appointments.manage')
            ->name('appointments.notes');
        Route::patch('appointments/{appointment}/down-payment', [AdminAppointmentController::class, 'updateDownPayment'])
            ->middleware('admin.role:admin.appointments.manage')
            ->name('appointments.down-payment');

        // Admin Flow 4 — Service & Item overview (combined)
        Route::get('/catalog', [CatalogController::class, 'index'])
            ->middleware('admin.role:admin.catalog.view')
            ->name('catalog.index');
        Route::get('/catalog/{type}/{id}/edit', [CatalogController::class, 'edit'])
            ->middleware('admin.role:admin.catalog.manage')
            ->name('catalog.edit');

        // Admin Flow 5 — Service Management.
        // Index is split out so a read-only role can browse the catalogue
        // while `admin.role:admin.catalog.manage` guards every write route.
        Route::get('/services', [AdminServiceController::class, 'index'])
            ->middleware('admin.role:admin.catalog.view')
            ->name('services.index');
        Route::resource('services', AdminServiceController::class)
            ->except(['show', 'index'])
            ->middleware('admin.role:admin.catalog.manage');
        Route::get('services/{service}/variants', [AdminServiceController::class, 'variants'])
            ->middleware('admin.role:admin.catalog.manage')
            ->name('services.variants');
        Route::post('services/{service}/variants', [AdminServiceController::class, 'storeVariant'])
            ->middleware('admin.role:admin.catalog.manage')
            ->name('services.variants.store');
        Route::patch('variants/{variant}', [AdminServiceController::class, 'updateVariant'])
            ->middleware('admin.role:admin.catalog.manage')
            ->name('variants.update');
        Route::delete('variants/{variant}', [AdminServiceController::class, 'destroyVariant'])
            ->middleware('admin.role:admin.catalog.manage')
            ->name('variants.destroy');

        // Admin Flow 6 — Inventory / Item Management
        Route::get('/inventory', [InventoryController::class, 'index'])
            ->middleware('admin.role:admin.inventory.view')
            ->name('inventory.index');
        Route::resource('inventory', InventoryController::class)
            ->except(['show', 'index'])
            ->middleware('admin.role:admin.inventory.manage');

        // Admin Flow 7 — Low-Stock / Not-Available Tagging.
        // NOTE: /tags/bulk must be declared before /tags/{item} or the
        // literal path is swallowed by the {item} parameter.
        Route::get('/tags', [ItemTagController::class, 'index'])
            ->middleware('admin.role:admin.inventory.view')
            ->name('tags.index');
        Route::patch('/tags/bulk', [ItemTagController::class, 'bulkUpdate'])
            ->middleware('admin.role:admin.tags.manage')
            ->name('tags.bulk');
        Route::patch('/tags/{item}', [ItemTagController::class, 'update'])
            ->middleware('admin.role:admin.tags.manage')
            ->name('tags.update');

        // Admin Flow 8 — Calendar & Blocked Dates
        Route::get('/calendar', [CalendarController::class, 'index'])
            ->middleware('admin.role:admin.calendar.view')
            ->name('calendar.index');
        Route::post('/calendar', [CalendarController::class, 'store'])
            ->middleware('admin.role:admin.calendar.manage')
            ->name('calendar.store');
        Route::delete('/calendar/{blockedDate}', [CalendarController::class, 'destroy'])
            ->middleware('admin.role:admin.calendar.manage')
            ->name('calendar.destroy');
        Route::put('/calendar/settings', [CalendarController::class, 'updateSettings'])
            ->middleware('admin.role:admin.calendar.manage')
            ->name('calendar.settings');

        // Admin Flow 9 — Registered Users Management (view + delete only)
        Route::get('/users', [UserController::class, 'index'])
            ->middleware('admin.role:admin.users.view')
            ->name('users.index');
        Route::get('/users/{user}', [UserController::class, 'show'])
            ->middleware('admin.role:admin.users.view')
            ->name('users.show');
        Route::patch('/users/{user}/status', [UserController::class, 'toggleStatus'])
            ->middleware('admin.role:admin.users.manage')
            ->name('users.status');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])
            ->middleware('admin.role:admin.users.delete')
            ->name('users.destroy');

        // Admin Flow 10 — Terms & Conditions Editor
        Route::get('/terms', [TermsController::class, 'index'])
            ->middleware('admin.role:admin.terms.view')
            ->name('terms.index');
        Route::get('/terms/create', [TermsController::class, 'create'])
            ->middleware('admin.role:admin.terms.manage')
            ->name('terms.create');
        Route::post('/terms', [TermsController::class, 'store'])
            ->middleware('admin.role:admin.terms.manage')
            ->name('terms.store');
        Route::get('/terms/{term}/edit', [TermsController::class, 'edit'])
            ->middleware('admin.role:admin.terms.manage')
            ->name('terms.edit');
        Route::put('/terms/{term}', [TermsController::class, 'update'])
            ->middleware('admin.role:admin.terms.manage')
            ->name('terms.update');
        Route::post('/terms/{term}/publish', [TermsController::class, 'publish'])
            ->middleware('admin.role:admin.terms.manage')
            ->name('terms.publish');
        Route::delete('/terms/{term}', [TermsController::class, 'destroy'])
            ->middleware('admin.role:admin.terms.manage')
            ->name('terms.destroy');

        // Admin Flow 11 — Review / Ratings Moderation (view + delete only)
        Route::get('/reviews', [ReviewModerationController::class, 'index'])
            ->middleware('admin.role:admin.reviews.view')
            ->name('reviews.index');
        Route::delete('/reviews/{review}', [ReviewModerationController::class, 'destroy'])
            ->middleware('admin.role:admin.reviews.manage')
            ->name('reviews.destroy');

        // Admin Flow 12 — Sales / Usage Reports
        Route::get('/reports', [ReportController::class, 'index'])
            ->middleware('admin.role:admin.reports.view')
            ->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])
            ->middleware('admin.role:admin.reports.view')
            ->name('reports.export');

        // Admin Flow 13 — Promo / Announcements
        Route::resource('promos', PromoController::class)
            ->except(['show'])
            ->middleware('admin.role:admin.promos.manage');
        Route::post('/promos/{promo}/announce', [PromoController::class, 'announce'])
            ->middleware('admin.role:admin.promos.manage')
            ->name('promos.announce');

        // Contact messages from Customer Flow 12
        Route::get('/messages', [\App\Http\Controllers\Admin\ContactMessageController::class, 'index'])
            ->middleware('admin.role:admin.messages.manage')
            ->name('messages.index');
        Route::patch('/messages/{message}', [\App\Http\Controllers\Admin\ContactMessageController::class, 'update'])
            ->middleware('admin.role:admin.messages.manage')
            ->name('messages.update');
        Route::delete('/messages/{message}', [\App\Http\Controllers\Admin\ContactMessageController::class, 'destroy'])
            ->middleware('admin.role:admin.messages.manage')
            ->name('messages.destroy');

        // Admin profile — every role may edit its own account
        Route::get('/profile', [AdminProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [AdminProfileController::class, 'update'])->name('profile.update');
    });
});
