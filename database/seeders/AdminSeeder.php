<?php

namespace Database\Seeders;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\SalonSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * The single staff login for the salon.
     *
     * There is one admin and no tiers: `username` is what the login form asks
     * for, and `admin` doubles as the only "Preferred Stylist" the booking form
     * can offer.
     */
    private const ADMIN = [
        'first_name' => 'Maia',
        'last_name' => 'Arjud',
        'username' => 'admin',
        'email' => 'admin@balaitiarjud.test',
    ];

    public function run(): void
    {
        Admin::updateOrCreate(
            ['username' => self::ADMIN['username']],
            self::ADMIN + [
                'role' => AdminRole::Admin,
                'password' => Hash::make('password'),
                'is_active' => true,
            ],
        );

        // The manager/staff accounts this seeder used to create are gone, so a
        // re-seed on an existing database would otherwise leave them behind
        // and quietly re-introduce the tiers. `forceDelete` rather than
        // `delete` so they are actually removed: a soft-deleted admin still
        // occupies its username and email, and still shows up in a role audit.
        // Their `created_by` and `preferred_stylist_id` references are
        // `nullOnDelete`, so removing the rows is safe.
        Admin::query()
            ->where('username', '!=', self::ADMIN['username'])
            ->forceDelete();

        SalonSetting::updateOrCreate(
            ['id' => 1],
            [
                'name' => 'Balai ti Arjud — Glow & Co. Beauty Lounge',
                'address' => 'Purok 5, Abra, Philippines',
                'phone' => '+63 900 000 0000',
                'email' => 'hello@balaitiarjud.test',
                'operating_hours' => SalonSetting::defaultHours(),
                'slot_interval_minutes' => 30,
                'booking_lead_days' => 60,
                // No deposit for a web booking; the form does not ask for a
                // reference. Matches `SalonSetting::current()`'s default.
                'down_payment_required' => false,
                'down_payment_percentage' => 50,
            ],
        );
    }
}
