<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AppointmentSeeder extends Seeder
{
    /**
     * Seed sample appointments
     */
    public function run(): void
    {
        $doctors = Doctor::with('user')->get();
        // Patients are users who are NOT admin and NOT doctor
        $patients = User::where('is_admin', false)->where('is_doctor', false)->get();

        if ($doctors->isEmpty()) {
            $this->command->warn('⚠️  No doctors found. Please run DoctorSeeder first.');
            return;
        }

        if ($patients->isEmpty()) {
            $this->command->warn('⚠️  No patients found. Please run UserSeeder first.');
            return;
        }

        // Create appointments for ALL patients to ensure demo data works
        $created = 0;
        
        foreach ($patients as $patient) {
            // Create 1-2 appointments per patient
            $numAppointments = rand(1, 2);
            
            for ($i = 0; $i < $numAppointments; $i++) {
                $doctor = $doctors->random();
                $daysFromNow = rand(-10, 10); // Some past, some future
                $status = $daysFromNow < 0 ? 'completed' : 'confirmed';
                
                Appointment::create([
                    'patient_id' => $patient->id,
                    'doctor_id' => $doctor->id,
                    'appointment_date' => Carbon::now()->addDays($daysFromNow)->toDateString(),
                    'start_time' => sprintf('%02d:00:00', rand(9, 16)),
                    'end_time' => sprintf('%02d:30:00', rand(9, 16)),
                    'status' => $status,
                    'type' => 'in_person',
                    'reason' => 'General health checkup',
                    'fee' => $doctor->consultation_fee,
                    'is_paid' => true,
                ]);
                $created++;
            }
        }
        
        $this->command->info('✅ Appointments seeded successfully! Created ' . $created . ' appointments for all patients.');
    }
}
