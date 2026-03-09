<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed patient users and admin
     */
    public function run(): void
    {
        // Seed patients (is_patient is determined by NOT being admin and NOT being doctor)
        $patientsData = [
            ['name' => 'John Patient', 'email' => 'patient@demo.com', 'phone' => '+1234567001'],
            ['name' => 'Sarah Smith', 'email' => 'sarah.smith@demo.com', 'phone' => '+1234567002'],
            ['name' => 'Mike Johnson', 'email' => 'mike.johnson@demo.com', 'phone' => '+1234567003'],
            ['name' => 'Emily Brown', 'email' => 'emily.brown@demo.com', 'phone' => '+1234567004'],
            ['name' => 'David Wilson', 'email' => 'david.wilson@demo.com', 'phone' => '+1234567005'],
            ['name' => 'Lisa Taylor', 'email' => 'lisa.taylor@demo.com', 'phone' => '+1234567006'],
            ['name' => 'James Anderson', 'email' => 'james.anderson@demo.com', 'phone' => '+1234567007'],
            ['name' => 'Maria Garcia', 'email' => 'maria.garcia@demo.com', 'phone' => '+1234567008'],
            ['name' => 'Robert Martinez', 'email' => 'robert.martinez@demo.com', 'phone' => '+1234567009'],
            ['name' => 'Jennifer Lee', 'email' => 'jennifer.lee@demo.com', 'phone' => '+1234567010'],
        ];

        foreach ($patientsData as $patientData) {
            $existing = User::where('email', $patientData['email'])->first();
            if (!$existing) {
                User::create([
                    'name' => $patientData['name'],
                    'email' => $patientData['email'],
                    'phone' => $patientData['phone'],
                    'password' => bcrypt('password'),
                    'is_active' => true,
                    'is_doctor' => false,
                    'is_admin' => false,
                ]);
                $this->command->info("✅ Created patient: {$patientData['name']}");
            } else {
                $this->command->info("⏭️  Skipped patient: {$patientData['name']} (already exists)");
            }
        }

        // Seed admin user
        $adminData = [
            'name' => 'Admin User',
            'email' => 'admin@demo.com',
            'phone' => '+1234567000',
            'password' => 'admin123',
        ];

        $adminExists = User::where('email', $adminData['email'])->first();
        if (!$adminExists) {
            User::create([
                'name' => $adminData['name'],
                'email' => $adminData['email'],
                'phone' => $adminData['phone'],
                'password' => bcrypt($adminData['password']),
                'is_active' => true,
                'is_admin' => true,
                'is_doctor' => false,
            ]);
            $this->command->info("✅ Created admin: {$adminData['name']}");
        } else {
            $this->command->info("⏭️  Skipped admin (already exists)");
        }
    }
}
