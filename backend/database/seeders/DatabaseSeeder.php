<?php

namespace Database\Seeders;

use App\Models\Specialization;
use App\Models\User;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create Specializations
        $specializations = [
            ['name' => 'Cardiologist', 'slug' => 'cardiologist', 'description' => 'Heart and cardiovascular system specialist', 'common_symptoms' => ['chest pain', 'shortness of breath', 'palpitations', 'high blood pressure']],
            ['name' => 'Dermatologist', 'slug' => 'dermatologist', 'description' => 'Skin, hair, and nails specialist', 'common_symptoms' => ['rash', 'acne', 'itching', 'skin discoloration']],
            ['name' => 'Neurologist', 'slug' => 'neurologist', 'description' => 'Brain and nervous system specialist', 'common_symptoms' => ['headache', 'dizziness', 'numbness', 'seizures']],
            ['name' => 'Pediatrician', 'slug' => 'pediatrician', 'description' => 'Children\'s health specialist', 'common_symptoms' => ['fever', 'cough', 'growth concerns', 'vaccination']],
            ['name' => 'Orthopedist', 'slug' => 'orthopedist', 'description' => 'Bones, joints, and muscles specialist', 'common_symptoms' => ['joint pain', 'fracture', 'back pain', 'stiffness']],
            ['name' => 'General Physician', 'slug' => 'general-physician', 'description' => 'Primary care physician', 'common_symptoms' => ['fever', 'cold', 'fatigue', 'general illness']],
            ['name' => 'Psychiatrist', 'slug' => 'psychiatrist', 'description' => 'Mental health specialist', 'common_symptoms' => ['anxiety', 'depression', 'stress', 'sleep problems']],
            ['name' => 'Ophthalmologist', 'slug' => 'ophthalmologist', 'description' => 'Eye specialist', 'common_symptoms' => ['blurred vision', 'eye pain', 'red eyes', 'dry eyes']],
            ['name' => 'ENT Specialist', 'slug' => 'ent', 'description' => 'Ear, nose, and throat specialist', 'common_symptoms' => ['sore throat', 'ear pain', 'hearing loss', 'sinus issues']],
            ['name' => 'Gynecologist', 'slug' => 'gynecologist', 'description' => 'Women\'s health specialist', 'common_symptoms' => ['irregular periods', 'pelvic pain', 'pregnancy care', 'menopause']],
        ];

        foreach ($specializations as $spec) {
            Specialization::create($spec);
        }

        // Create Sample Doctors
        $doctorsData = [
            [
                'user' => ['name' => 'Dr. Sarah Johnson', 'email' => 'sarah.johnson@hospital.com', 'phone' => '+1234567890'],
                'doctor' => [
                    'specialization_id' => 1, // Cardiologist
                    'license_number' => 'MD-CARD-001',
                    'qualification' => 'MD, PhD - Harvard Medical School',
                    'experience_years' => 15,
                    'bio' => 'Expert in cardiovascular diseases with 15+ years of experience.',
                    'consultation_fee' => 150.00,
                    'hospital_clinic' => 'Heart Care Center',
                    'address' => '123 Medical Street',
                    'city' => 'New York',
                    'rating' => 4.8,
                    'total_reviews' => 245,
                    'languages' => ['en', 'es'],
                    'available_days' => ['monday', 'tuesday', 'thursday', 'friday'],
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'slot_duration' => 30,
                ]
            ],
            [
                'user' => ['name' => 'Dr. Michael Chen', 'email' => 'michael.chen@hospital.com', 'phone' => '+1234567891'],
                'doctor' => [
                    'specialization_id' => 6, // General Physician
                    'license_number' => 'MD-GEN-002',
                    'qualification' => 'MD - Johns Hopkins University',
                    'experience_years' => 10,
                    'bio' => 'Primary care physician with expertise in preventive medicine.',
                    'consultation_fee' => 80.00,
                    'hospital_clinic' => 'City Medical Clinic',
                    'address' => '456 Health Avenue',
                    'city' => 'New York',
                    'rating' => 4.6,
                    'total_reviews' => 180,
                    'languages' => ['en', 'zh'],
                    'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
                    'start_time' => '08:00:00',
                    'end_time' => '18:00:00',
                    'slot_duration' => 30,
                ]
            ],
            [
                'user' => ['name' => 'Dr. Emily Williams', 'email' => 'emily.williams@hospital.com', 'phone' => '+1234567892'],
                'doctor' => [
                    'specialization_id' => 4, // Pediatrician
                    'license_number' => 'MD-PED-003',
                    'qualification' => 'MD - Stanford University',
                    'experience_years' => 12,
                    'bio' => 'Caring pediatrician with special interest in child development.',
                    'consultation_fee' => 100.00,
                    'hospital_clinic' => 'Children\'s Wellness Center',
                    'address' => '789 Kids Lane',
                    'city' => 'New York',
                    'rating' => 4.9,
                    'total_reviews' => 320,
                    'languages' => ['en'],
                    'available_days' => ['monday', 'wednesday', 'friday'],
                    'start_time' => '09:00:00',
                    'end_time' => '16:00:00',
                    'slot_duration' => 30,
                ]
            ],
        ];

        foreach ($doctorsData as $data) {
            $user = User::create([
                'name' => $data['user']['name'],
                'email' => $data['user']['email'],
                'phone' => $data['user']['phone'],
                'password' => bcrypt('password123'),
            ]);

            $doctor = Doctor::create(array_merge(
                $data['doctor'],
                ['user_id' => $user->id]
            ));

            // Create schedules for each doctor
            foreach ($data['doctor']['available_days'] as $day) {
                DoctorSchedule::create([
                    'doctor_id' => $doctor->id,
                    'day_of_week' => $day,
                    'start_time' => $data['doctor']['start_time'],
                    'end_time' => $data['doctor']['end_time'],
                    'break_start' => '12:00:00',
                    'break_end' => '13:00:00',
                    'max_appointments' => 16,
                    'is_active' => true,
                ]);
            }
        }

        $this->command->info('Database seeded successfully!');
        $this->command->info('Sample doctors created with login password: password123');
    }
}
