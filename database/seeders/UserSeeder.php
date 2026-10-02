<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Customer accounts.
 *
 * One account, and only one: the developer's own, so there is always something
 * to sign in as on a freshly seeded database without registering through the form
 * first.
 *
 * The demo customers this used to create — six named customers plus a deactivated
 * one — are deliberately still gone. Real customers register themselves, and
 * invented ones filled the admin's Registered Users list and put their bookings
 * into the reports and the admin calendar as if they were real. A single known
 * account costs one row; a cast of them costs the screens' credibility.
 *
 * Two things to know about the one that remains:
 *
 *   - it is **not** idempotent in the password. `updateOrCreate` resets the
 *     password every time, so re-seeding to get back to a known state also
 *     discards a password changed through the profile screen. That is the point
 *     of a seeder, but it is a footgun worth naming.
 *   - it appears in the admin's Registered Users list like any other customer,
 *     and its appointments appear in the reports. Clear it with:
 *
 *         php artisan users:clear --keep=rucielmaeobias277 --force
 *
 * `AppointmentSeeder` picks its customers from the active users, so on a database
 * with this account it will attach bookings to it, and on one without it simply
 * creates no appointments rather than failing.
 *
 * @see \Database\Seeders\DatabaseSeeder for where this sits in the order.
 */
class UserSeeder extends Seeder
{
    /**
     * The developer account.
     *
     * @var array<string, mixed>
     */
    private const ACCOUNT = [
        'first_name' => 'Ruciel Mae',
        'last_name' => 'Obias',
        'username' => 'rucielmaeobias277',
        'email' => 'rucielmaeobias277@gmail.com',
        'contact_number' => '09318467600',
        // Plain, not pre-hashed: `User` casts `password` to `hashed`, so handing
        // it an already-hashed string would hash it a second time and produce an
        // account that looks seeded and cannot sign in.
        'password' => 'Rucielmae@21',
        'is_active' => true,
    ];

    public function run(): void
    {
        // `profile_photo_path` is deliberately not seeded. It names a file under
        // storage/, which a fresh checkout does not have, and a seeded path with
        // no file behind it renders as a broken avatar — the customer initials
        // are the honest fallback.

        User::updateOrCreate(
            ['email' => self::ACCOUNT['email']],
            self::ACCOUNT,
        );
    }
}
