<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Calls all individual seeders in the correct order
     */
    public function run(): void
    {
        $this->call([
            SpecializationSeeder::class,
            DoctorSeeder::class,
            UserSeeder::class,
            AppointmentSeeder::class,
        ]);
    }
}
