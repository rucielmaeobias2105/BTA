<?php

namespace Database\Seeders;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\SalonSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admins = [
            [
                'first_name' => 'Maia',
                'last_name' => 'Arjud',
                'username' => 'admin',
                'email' => 'admin@balaitiarjud.test',
                'role' => AdminRole::SuperAdmin,
            ],
            [
                'first_name' => 'Rina',
                'last_name' => 'Bautista',
                'username' => 'manager',
                'email' => 'manager@balaitiarjud.test',
                'role' => AdminRole::Manager,
            ],
            // Therapists double as the "Preferred Stylist" list on the booking form.
            [
                'first_name' => 'Jade',
                'last_name' => 'Panganiban',
                'username' => 'jade',
                'email' => 'jade@balaitiarjud.test',
                'role' => AdminRole::Staff,
            ],
            [
                'first_name' => 'Marco',
                'last_name' => 'Soriano',
                'username' => 'marco',
                'email' => 'marco@balaitiarjud.test',
                'role' => AdminRole::Staff,
            ],
            [
                'first_name' => 'Aling',
                'last_name' => 'Reyes',
                'username' => 'aling',
                'email' => 'aling@balaitiarjud.test',
                'role' => AdminRole::Staff,
            ],
        ];

        foreach ($admins as $admin) {
            Admin::updateOrCreate(
                ['email' => $admin['email']],
                $admin + [
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ],
            );
        }

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
                'down_payment_required' => true,
                'down_payment_percentage' => 50,
            ],
        );
    }
}
