<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed only 3 users: patient, doctor, admin
     */
    public function run(): void
    {
        // Seed 1 patient user
        $patientData = [
            'name' => 'John Patient',
            'email' => 'patient@demo.com',
            'phone' => '+1234567001',
            'password' => 'password',
        ];

        $patientExists = User::where('email', $patientData['email'])->first();
        if (!$patientExists) {
            User::create([
                'name' => $patientData['name'],
                'email' => $patientData['email'],
                'phone' => $patientData['phone'],
                'password' => bcrypt($patientData['password']),
                'is_active' => true,
                'is_doctor' => false,
                'is_admin' => false,
            ]);
            $this->command->info("✅ Created patient: {$patientData['name']}");
        } else {
            $this->command->info("⏭️  Skipped patient (already exists)");
        }

        // Seed 1 admin user
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

        // Note: Doctor users are created in DoctorSeeder
        // They link to the doctors table and have is_doctor = true
    }
}
