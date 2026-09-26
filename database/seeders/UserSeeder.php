<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            ['Juan', 'Dela Cruz', 'juan@example.test', '09171234567'],
            ['Maria', 'Santos', 'maria@example.test', '09181234567'],
            ['Angeline', 'Reyes', 'angeline@example.test', '09191234567'],
            ['Paolo', 'Garcia', 'paolo@example.test', '09201234567'],
            ['Kristine', 'Mendoza', 'kristine@example.test', '09211234567'],
            ['Diego', 'Aquino', 'diego@example.test', '09221234567'],
        ];

        foreach ($customers as [$first, $last, $email, $phone]) {
            User::updateOrCreate(
                ['email' => $email],
                [
                    'first_name' => $first,
                    'last_name' => $last,
                    'username' => User::deriveUsername($email),
                    'contact_number' => $phone,
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ],
            );
        }

        // One deactivated account so the admin "deactivate" path is demonstrable.
        User::updateOrCreate(
            ['email' => 'inactive@example.test'],
            [
                'first_name' => 'Test',
                'last_name' => 'Deactivated',
                'username' => 'testdeactivated',
                'contact_number' => '09231234567',
                'password' => Hash::make('password'),
                'is_active' => false,
            ],
        );
    }
}
