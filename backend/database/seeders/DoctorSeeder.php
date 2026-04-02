<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Specialization;
use Illuminate\Database\Seeder;

class DoctorSeeder extends Seeder
{
    /**
     * Seed doctors with their schedules
     */
    public function run(): void
    {
        $doctorsData = $this->getDoctorsData();

        foreach ($doctorsData as $doctorData) {
            // Find or create specialization
            $specialization = $this->getOrCreateSpecialization($doctorData['specialization']);

            // Create user for doctor
            $user = $this->createDoctorUser($doctorData['user']);

            // Check if doctor already exists
            $doctor = Doctor::where('user_id', $user->id)->first();

            if (!$doctor) {
                // Create doctor
                $doctor = Doctor::create([
                    'user_id' => $user->id,
                    'specialization_id' => $specialization->id,
                    'license_number' => $doctorData['license_number'],
                    'qualification' => $doctorData['qualification'],
                    'experience_years' => $doctorData['experience_years'],
                    'bio' => $doctorData['bio'],
                    'consultation_fee' => $doctorData['consultation_fee'],
                    'hospital_clinic' => $doctorData['hospital_clinic'],
                    'address' => $doctorData['address'],
                    'city' => $doctorData['city'],
                    'rating' => $doctorData['rating'],
                    'total_reviews' => $doctorData['total_reviews'],
                    'languages' => $doctorData['languages'],
                ]);

                // Create doctor schedules
                $this->createDoctorSchedules($doctor, $doctorData['schedule']);

                $this->command->info("✅ Created: {$user->name} ({$specialization->name})");
            } else {
                // Update schedules even if doctor exists
                $this->createDoctorSchedules($doctor, $doctorData['schedule']);
                $this->command->info("⏭️  Skipped: Dr. {$user->name} (already exists, schedules updated)");
            }
        }
    }

    /**
     * Get doctors data
     */
    protected function getDoctorsData(): array
    {
        return [
            [
                'user' => ['name' => 'Sarah Johnson', 'email' => 'sarah.johnson@hospital.com', 'phone' => '+1234567890'],
                'specialization' => 'Cardiologist',
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
                'schedule' => [
                    'days' => ['monday', 'tuesday', 'thursday', 'friday'],
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                ]
            ],
            [
                'user' => ['name' => 'Michael Chen', 'email' => 'michael.chen@hospital.com', 'phone' => '+1234567891'],
                'specialization' => 'General Physician',
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
                'schedule' => [
                    'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
                    'start_time' => '08:00:00',
                    'end_time' => '18:00:00',
                ]
            ],
            [
                'user' => ['name' => 'Emily Williams', 'email' => 'emily.williams@hospital.com', 'phone' => '+1234567892'],
                'specialization' => 'Pediatrician',
                'license_number' => 'MD-PED-003',
                'qualification' => 'MD - Stanford University',
                'experience_years' => 12,
                'bio' => 'Caring pediatrician with special interest in child development.',
                'consultation_fee' => 100.00,
                'hospital_clinic' => "Children's Wellness Center",
                'address' => '789 Kids Lane',
                'city' => 'New York',
                'rating' => 4.9,
                'total_reviews' => 320,
                'languages' => ['en'],
                'schedule' => [
                    'days' => ['monday', 'wednesday', 'friday'],
                    'start_time' => '09:00:00',
                    'end_time' => '16:00:00',
                ]
            ],
            [
                'user' => ['name' => 'James Wilson', 'email' => 'james.wilson@hospital.com', 'phone' => '+1234567893'],
                'specialization' => 'Dentist',
                'license_number' => 'DDS-DENT-001',
                'qualification' => 'DDS - Dental College',
                'experience_years' => 8,
                'bio' => 'Experienced dentist specializing in preventive and restorative dentistry.',
                'consultation_fee' => 75.00,
                'hospital_clinic' => 'Smile Dental Clinic',
                'address' => '321 Dental Drive',
                'city' => 'New York',
                'rating' => 4.7,
                'total_reviews' => 150,
                'languages' => ['en', 'es'],
                'schedule' => [
                    'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                    'start_time' => '10:00:00',
                    'end_time' => '18:00:00',
                ]
            ],
            [
                'user' => ['name' => 'Sarah Ahmed', 'email' => 'sarah.ahmed@dental.com', 'phone' => '+1234567894'],
                'specialization' => 'Dentist',
                'license_number' => 'DDS-DENT-002',
                'qualification' => 'BDS, MDS - Orthodontics',
                'experience_years' => 12,
                'bio' => 'Specialist in braces and teeth alignment.',
                'consultation_fee' => 100.00,
                'hospital_clinic' => 'Perfect Smile Dental Center',
                'address' => '456 Ortho Street',
                'city' => 'Dhaka',
                'rating' => 4.8,
                'total_reviews' => 200,
                'languages' => ['en', 'bn'],
                'schedule' => [
                    'days' => ['sunday', 'tuesday', 'thursday', 'saturday'],
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                ]
            ],
            [
                'user' => ['name' => 'Mohammad Khan', 'email' => 'mohammad.khan@dental.com', 'phone' => '+1234567895'],
                'specialization' => 'Dentist',
                'license_number' => 'DDS-DENT-003',
                'qualification' => 'BDS - Conservative Dentistry',
                'experience_years' => 6,
                'bio' => 'Expert in root canal treatment and dental fillings.',
                'consultation_fee' => 50.00,
                'hospital_clinic' => 'City Dental Care',
                'address' => '789 Root Canal Road',
                'city' => 'Dhaka',
                'rating' => 4.5,
                'total_reviews' => 80,
                'languages' => ['en', 'bn'],
                'schedule' => [
                    'days' => ['monday', 'wednesday', 'friday'],
                    'start_time' => '10:00:00',
                    'end_time' => '16:00:00',
                ]
            ],
            [
                'user' => ['name' => 'Fatema Begum', 'email' => 'fatema.begum@dental.com', 'phone' => '+1234567896'],
                'specialization' => 'Dentist',
                'license_number' => 'DDS-DENT-004',
                'qualification' => 'BDS, MPH - Dental Surgery',
                'experience_years' => 15,
                'bio' => 'General dentist with expertise in tooth extraction and dental surgery.',
                'consultation_fee' => 80.00,
                'hospital_clinic' => 'National Dental Hospital',
                'address' => '321 Surgery Avenue',
                'city' => 'Chittagong',
                'rating' => 4.9,
                'total_reviews' => 320,
                'languages' => ['en', 'bn'],
                'schedule' => [
                    'days' => ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday'],
                    'start_time' => '08:00:00',
                    'end_time' => '14:00:00',
                ]
            ],
            [
                'user' => ['name' => 'Ali Hassan', 'email' => 'ali.hassan@dental.com', 'phone' => '+1234567897'],
                'specialization' => 'Dentist',
                'license_number' => 'DDS-DENT-005',
                'qualification' => 'DDS - Implantology',
                'experience_years' => 10,
                'bio' => 'Specialist in dental implants and prosthetic dentistry.',
                'consultation_fee' => 150.00,
                'hospital_clinic' => 'Implant Dental Center',
                'address' => '555 Implant Plaza',
                'city' => 'Dhaka',
                'rating' => 4.6,
                'total_reviews' => 120,
                'languages' => ['en', 'bn', 'ar'],
                'schedule' => [
                    'days' => ['tuesday', 'wednesday', 'thursday', 'saturday'],
                    'start_time' => '11:00:00',
                    'end_time' => '19:00:00',
                ]
            ],
            [
                'user' => ['name' => 'Robert Martinez', 'email' => 'robert.martinez@hospital.com', 'phone' => '+1234567898'],
                'specialization' => 'Neurologist',
                'license_number' => 'MD-NEURO-001',
                'qualification' => 'MD, PhD - Neurology',
                'experience_years' => 18,
                'bio' => 'Expert in brain and nervous system disorders.',
                'consultation_fee' => 200.00,
                'hospital_clinic' => 'Neuro Care Center',
                'address' => '555 Neuro Way',
                'city' => 'New York',
                'rating' => 4.9,
                'total_reviews' => 280,
                'languages' => ['en', 'es'],
                'schedule' => [
                    'days' => ['monday', 'tuesday', 'thursday'],
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                ]
            ],
            [
                'user' => ['name' => 'Lisa Anderson', 'email' => 'lisa.anderson@hospital.com', 'phone' => '+1234567895'],
                'specialization' => 'Dermatologist',
                'license_number' => 'MD-DERM-001',
                'qualification' => 'FCPS Dermatology',
                'experience_years' => 7,
                'bio' => 'Specializing in skin conditions and cosmetic dermatology.',
                'consultation_fee' => 120.00,
                'hospital_clinic' => 'Skin Health Clinic',
                'address' => '777 Skin Street',
                'city' => 'New York',
                'rating' => 4.6,
                'total_reviews' => 165,
                'languages' => ['en'],
                'schedule' => [
                    'days' => ['monday', 'wednesday', 'friday', 'saturday'],
                    'start_time' => '10:00:00',
                    'end_time' => '18:00:00',
                ]
            ],
            [
                'user' => ['name' => 'David Taylor', 'email' => 'david.taylor@hospital.com', 'phone' => '+1234567896'],
                'specialization' => 'Orthopedist',
                'license_number' => 'MD-ORTHO-001',
                'qualification' => 'FCPS Orthopedics',
                'experience_years' => 14,
                'bio' => 'Expert in orthopedic surgery and sports medicine.',
                'consultation_fee' => 180.00,
                'hospital_clinic' => 'Ortho Care Center',
                'address' => '888 Bone Street',
                'city' => 'New York',
                'rating' => 4.8,
                'total_reviews' => 210,
                'languages' => ['en'],
                'schedule' => [
                    'days' => ['monday', 'tuesday', 'thursday', 'friday'],
                    'start_time' => '08:00:00',
                    'end_time' => '16:00:00',
                ]
            ],
            // added ophthalmologist so that "eye doctor" queries return a match
            [
                'user' => ['name' => 'Maya Patel', 'email' => 'maya.patel@hospital.com', 'phone' => '+1234567801'],
                'specialization' => 'Ophthalmologist',
                'license_number' => 'MD-EYE-001',
                'qualification' => 'MD Ophthalmology - Columbia University',
                'experience_years' => 13,
                'bio' => 'Experienced eye doctor specializing in vision care and surgery.',
                'consultation_fee' => 140.00,
                'hospital_clinic' => 'Vision Care Center',
                'address' => '101 Eye Street',
                'city' => 'New York',
                'rating' => 4.8,
                'total_reviews' => 200,
                'languages' => ['en'],
                'schedule' => [
                    'days' => ['monday', 'tuesday', 'wednesday', 'friday'],
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                ]
            ],
            [
                'user' => ['name' => 'Jennifer Lee', 'email' => 'jennifer.lee@hospital.com', 'phone' => '+1234567897'],
                'specialization' => 'Gynecologist',
                'license_number' => 'MD-GYNAE-001',
                'qualification' => 'FCPS Gynecology',
                'experience_years' => 11,
                'bio' => "Women's health expert with focus on prenatal care.",
                'consultation_fee' => 130.00,
                'hospital_clinic' => 'Women Health Center',
                'address' => '999 Wellness Way',
                'city' => 'New York',
                'rating' => 4.9,
                'total_reviews' => 290,
                'languages' => ['en', 'es'],
                'schedule' => [
                    'days' => ['monday', 'wednesday', 'friday'],
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                ]
            ],
            [
                'user' => ['name' => 'Christopher Brown', 'email' => 'chris.brown@hospital.com', 'phone' => '+1234567898'],
                'specialization' => 'ENT Specialist',
                'license_number' => 'MD-ENT-002',
                'qualification' => 'FCPS ENT',
                'experience_years' => 9,
                'bio' => 'Ear, nose, and throat specialist with expertise in sinus treatment.',
                'consultation_fee' => 110.00,
                'hospital_clinic' => 'ENT Care Clinic',
                'address' => '111 Sinus Street',
                'city' => 'New York',
                'rating' => 4.5,
                'total_reviews' => 140,
                'languages' => ['en'],
                'schedule' => [
                    'days' => ['tuesday', 'wednesday', 'thursday', 'saturday'],
                    'start_time' => '10:00:00',
                    'end_time' => '18:00:00',
                ]
            ],
            [
                'user' => ['name' => 'Amanda White', 'email' => 'amanda.white@hospital.com', 'phone' => '+1234567899'],
                'specialization' => 'Psychiatrist',
                'license_number' => 'MD-PSYCH-001',
                'qualification' => 'MD Psychiatry',
                'experience_years' => 16,
                'bio' => 'Mental health expert specializing in anxiety and depression.',
                'consultation_fee' => 160.00,
                'hospital_clinic' => 'Mental Wellness Center',
                'address' => '222 Mind Street',
                'city' => 'New York',
                'rating' => 4.7,
                'total_reviews' => 180,
                'languages' => ['en'],
                'schedule' => [
                    'days' => ['monday', 'tuesday', 'thursday'],
                    'start_time' => '11:00:00',
                    'end_time' => '19:00:00',
                ]
            ],
        ];
    }

    /**
     * Get or create specialization dynamically
     */
    protected function getOrCreateSpecialization(string $name): \App\Models\Specialization
    {
        $slug = strtolower(str_replace([' ', '&'], ['-', 'and'], $name));
        
        $specialization = \App\Models\Specialization::where('name', $name)->first();
        
        if (!$specialization) {
            $specialization = \App\Models\Specialization::where('slug', $slug)->first();
        }
        
        if (!$specialization) {
            $specialization = \App\Models\Specialization::create([
                'name' => $name,
                'slug' => $slug,
                'description' => "Specialist in {$name}",
                'common_symptoms' => [],
            ]);
        }
        
        return $specialization;
    }

    /**
     * Create doctor user
     */
    protected function createDoctorUser(array $userData): \App\Models\User
    {
        $user = User::where('email', $userData['email'])->first();
        
        if (!$user) {
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'phone' => $userData['phone'],
                'password' => bcrypt('password123'),
                'is_active' => true,
                'is_doctor' => true,
            ]);
        }
        
        return $user;
    }

    /**
     * Create doctor schedules - 2 days/week, evening 6-8 PM, for next 6 months
     */
    protected function createDoctorSchedules(\App\Models\Doctor $doctor, array $schedule): void
    {
        // Delete existing schedules
        DoctorSchedule::where('doctor_id', $doctor->id)->delete();

        // Two days per week (friday and saturday)
        $days = ['friday', 'saturday'];
        
        // Generate dates for next 6 months
        $startDate = now();
        $endDate = now()->addMonths(6);
        
        // Find all fridays and saturdays in the next 6 months
        $scheduleDates = [];
        $currentDate = $startDate->copy();
        
        while ($currentDate->lte($endDate)) {
            if (in_array(strtolower($currentDate->format('l')), $days)) {
                $scheduleDates[] = $currentDate->format('Y-m-d');
            }
            $currentDate->addDay();
        }

        // Create schedules for each date with evening time 6 PM to 8 PM
        foreach ($scheduleDates as $date) {
            $dayOfWeek = strtolower(date('l', strtotime($date)));
            DoctorSchedule::create([
                'doctor_id' => $doctor->id,
                'day_of_week' => $dayOfWeek,
                'schedule_date' => $date,
                'start_time' => '18:00:00',
                'end_time' => '20:00:00',
                'break_start' => null,
                'break_end' => null,
                'max_appointments' => 8, // 2 hours, 15 min per appointment = 8
                'is_active' => true,
            ]);
        }

        $this->command->info("   ✅ Created " . count($scheduleDates) . " schedules for Dr. {$doctor->user->name} (2 days/week, 6-8 PM, 6 months)");
    }
}
