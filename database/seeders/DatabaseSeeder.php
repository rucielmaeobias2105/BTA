<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Staff + salon operating configuration must exist first.
            AdminSeeder::class,
            ServiceSeeder::class,
            // Inventory is deliberately NOT seeded. Stock is what the salon
            // actually holds, and demo rows would both misrepresent that and
            // sit at the top of every low-stock count. The admin adds real
            // items from the Inventory screen; `php artisan inventory:clear`
            // removes any rows a previous seed left behind.
            UserSeeder::class,
            AppointmentSeeder::class,
            TermsSeeder::class,
            ContentSeeder::class,
        ]);
    }
}
