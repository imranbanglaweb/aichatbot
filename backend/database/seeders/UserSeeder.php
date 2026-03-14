<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed ONLY 3 users: 1 patient, 1 admin, 1 doctor
     */
    public function run(): void
    {
        // Delete ALL existing users first
        User::whereNotNull('id')->delete();
        $this->command->info("🗑️  Deleted all existing users");
        
        // Create 1 patient user
        User::create([
            'name' => 'John Patient',
            'email' => 'patient@demo.com',
            'phone' => '+1234567001',
            'password' => bcrypt('password'),
            'is_active' => true,
            'is_doctor' => false,
            'is_admin' => false,
        ]);
        $this->command->info("✅ Created patient: patient@demo.com / password");

        // Create 1 admin user
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@demo.com',
            'phone' => '+1234567000',
            'password' => bcrypt('admin123'),
            'is_active' => true,
            'is_admin' => true,
            'is_doctor' => false,
        ]);
        $this->command->info("✅ Created admin: admin@demo.com / admin123");

        // Create 1 doctor user (doctor_id will be set by DoctorSeeder)
        User::create([
            'name' => 'Dr. Sarah Doctor',
            'email' => 'doctor@demo.com',
            'phone' => '+1234567002',
            'password' => bcrypt('doctor123'),
            'is_active' => true,
            'is_doctor' => true,
            'is_admin' => false,
            'doctor_id' => null, // Will be linked by DoctorSeeder
        ]);
        $this->command->info("✅ Created doctor: doctor@demo.com / doctor123");

        $this->command->info("📋 Total users: 3 (patient, admin, doctor)");
        $this->command->info("💡 Run DoctorSeeder to link doctor user to doctor record");
    }
}
