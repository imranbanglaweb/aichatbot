<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\User;
use App\Models\Specialization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MedicareDoctorsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $doctors = [
            [
                'name' => 'Dr. Mahmudul Hasan Khan',
                'email' => 'mahmudul.hasan@medicare.local',
                'phone' => '+8801712345678',
                'specialization' => 'Medicine & Chest Diseases',
                'qualification' => 'MBBS, IDM, CCD, CCCD, MPH, MCCP, MSC',
                'hospital' => 'National Institute of Disease of the Chest & Hospital',
                'fee' => 700,
                'experience_years' => 10,
                'rating' => 4.5,
                'license_number' => 'MD-001-MHK',
            ],
            [
                'name' => 'Dr. Md. Abdul Halim',
                'email' => 'abdul.halim@medicare.local',
                'phone' => '+8801712345679',
                'specialization' => 'Family Medicine',
                'qualification' => 'MBBS(CU), MPH(LU), CCD, EDC, BIRDEM(Dhaka), Ex-Registrar Medicine & Diabetologist',
                'hospital' => 'MRMCH',
                'fee' => 500,
                'experience_years' => 8,
                'rating' => 4.3,
                'license_number' => 'MD-002-DAH',
            ],
            [
                'name' => 'Dr. Mohammad Kamrul Hasan',
                'email' => 'kamrul.hasan@medicare.local',
                'phone' => '+8801712345680',
                'specialization' => 'Medicine',
                'qualification' => 'FCPS(Medicine), MD(Critical Care Medicine)',
                'hospital' => 'National Institute of Chest & Hospital',
                'fee' => 800,
                'experience_years' => 12,
                'rating' => 4.7,
                'license_number' => 'MD-003-MKH',
            ],
            [
                'name' => 'Prof. Dr. Mahbub Ali',
                'email' => 'mahbub.ali@medicare.local',
                'phone' => '+8801712345681',
                'specialization' => 'Cardiology',
                'qualification' => 'MD, FACC, FSCAI',
                'hospital' => 'National Institute of Chest & Hospital',
                'fee' => 1000,
                'experience_years' => 20,
                'rating' => 4.9,
                'license_number' => 'MD-004-PMA',
            ],
        ];

        foreach ($doctors as $doctorData) {
            // Create or update user
            $user = User::updateOrCreate(
                ['email' => $doctorData['email']],
                [
                    'name' => $doctorData['name'],
                    'phone' => $doctorData['phone'],
                    'password' => Hash::make('doctor123'),
                ]
            );

            // Find or create specialization
            $specialization = Specialization::updateOrCreate(
                ['name' => $doctorData['specialization']],
                [
                    'slug' => strtolower(str_replace([' ', '&'], ['-', 'and'], $doctorData['specialization'])),
                    'description' => 'Specialist in ' . $doctorData['specialization'],
                ]
            );

            // Create or update doctor
            $doctor = Doctor::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'specialization_id' => $specialization->id,
                    'license_number' => $doctorData['license_number'],
                    'qualification' => $doctorData['qualification'],
                    'experience_years' => $doctorData['experience_years'],
                    'hospital_clinic' => $doctorData['hospital'],
                    'consultation_fee' => $doctorData['fee'],
                    'rating' => $doctorData['rating'],
                    'total_reviews' => rand(50, 200),
                    'languages' => ['en', 'bn'],
                    'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'slot_duration' => 30,
                    'is_available' => true,
                    'is_verified' => true,
                ]
            );

            echo "✅ Added/Updated: " . $doctorData['name'] . " (" . $doctorData['specialization'] . ")\n";
        }

        echo "\n" . str_repeat('=', 60) . "\n";
        echo "Successfully imported " . count($doctors) . " doctors!\n";
        echo str_repeat('=', 60) . "\n";
    }
}
