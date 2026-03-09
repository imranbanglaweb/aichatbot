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

        // Sample appointments data
        // Note: type must be 'in_person' or 'online' (from enum)
        $appointmentsData = [
            // Upcoming appointments
            [
                'doctor_index' => 0,
                'patient_index' => 0,
                'days_from_now' => 1,
                'time' => '09:00:00',
                'status' => 'confirmed',
                'type' => 'in_person',
                'reason' => 'Regular cardiac checkup',
                'is_paid' => true,
            ],
            [
                'doctor_index' => 1,
                'patient_index' => 1,
                'days_from_now' => 2,
                'time' => '10:00:00',
                'status' => 'pending',
                'type' => 'in_person',
                'reason' => 'General illness - fever and cold',
                'is_paid' => false,
            ],
            [
                'doctor_index' => 2,
                'patient_index' => 2,
                'days_from_now' => 3,
                'time' => '11:00:00',
                'status' => 'confirmed',
                'type' => 'in_person',
                'reason' => 'Child vaccination',
                'is_paid' => true,
            ],
            [
                'doctor_index' => 3,
                'patient_index' => 3,
                'days_from_now' => 4,
                'time' => '14:00:00',
                'status' => 'pending',
                'type' => 'in_person',
                'reason' => 'Toothache examination',
                'is_paid' => false,
            ],
            [
                'doctor_index' => 4,
                'patient_index' => 4,
                'days_from_now' => 5,
                'time' => '09:00:00',
                'status' => 'confirmed',
                'type' => 'in_person',
                'reason' => 'Headache followup - MRI results review',
                'is_paid' => true,
            ],
            // Past appointments - completed
            [
                'doctor_index' => 0,
                'patient_index' => 5,
                'days_from_now' => -1,
                'time' => '10:00:00',
                'status' => 'completed',
                'type' => 'in_person',
                'reason' => 'Chest pain consultation',
                'is_paid' => true,
            ],
            [
                'doctor_index' => 1,
                'patient_index' => 6,
                'days_from_now' => -2,
                'time' => '11:00:00',
                'status' => 'completed',
                'type' => 'in_person',
                'reason' => 'Annual health checkup',
                'is_paid' => true,
            ],
            [
                'doctor_index' => 2,
                'patient_index' => 7,
                'days_from_now' => -3,
                'time' => '09:00:00',
                'status' => 'completed',
                'type' => 'in_person',
                'reason' => 'Child fever treatment',
                'is_paid' => true,
            ],
            [
                'doctor_index' => 3,
                'patient_index' => 8,
                'days_from_now' => -5,
                'time' => '15:00:00',
                'status' => 'completed',
                'type' => 'in_person',
                'reason' => 'Dental cleaning and checkup',
                'is_paid' => true,
            ],
            [
                'doctor_index' => 5,
                'patient_index' => 9,
                'days_from_now' => -7,
                'time' => '10:00:00',
                'status' => 'completed',
                'type' => 'in_person',
                'reason' => 'Skin rash examination',
                'is_paid' => true,
            ],
            // Cancelled appointment
            [
                'doctor_index' => 1,
                'patient_index' => 0,
                'days_from_now' => -4,
                'time' => '14:00:00',
                'status' => 'cancelled',
                'type' => 'in_person',
                'reason' => 'Cancelled by patient - schedule conflict',
                'is_paid' => false,
                'cancellation_reason' => 'Schedule conflict',
            ],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($appointmentsData as $aptData) {
            // Skip if doctor or patient index doesn't exist
            if (!isset($doctors[$aptData['doctor_index']]) || !isset($patients[$aptData['patient_index']])) {
                $skipped++;
                continue;
            }

            $doctor = $doctors[$aptData['doctor_index']];
            $patient = $patients[$aptData['patient_index']];
            
            $appointmentDate = Carbon::now()->addDays($aptData['days_from_now']);
            $time = $aptData['time'];
            $endTime = Carbon::createFromFormat('H:i:s', $time)->addMinutes(30)->format('H:i:s');

            // Check if similar appointment already exists
            $exists = Appointment::where('doctor_id', $doctor->id)
                ->where('patient_id', $patient->id)
                ->where('appointment_date', $appointmentDate->toDateString())
                ->where('start_time', $time)
                ->exists();

            if (!$exists) {
                Appointment::create([
                    'patient_id' => $patient->id,
                    'doctor_id' => $doctor->id,
                    'appointment_date' => $appointmentDate->toDateString(),
                    'start_time' => $time,
                    'end_time' => $endTime,
                    'status' => $aptData['status'],
                    'type' => $aptData['type'],
                    'reason' => $aptData['reason'],
                    'fee' => $doctor->consultation_fee,
                    'is_paid' => $aptData['is_paid'],
                    'cancelled_at' => $aptData['status'] === 'cancelled' ? $appointmentDate : null,
                    'cancellation_reason' => $aptData['cancellation_reason'] ?? null,
                ]);
                $created++;
                $this->command->info("✅ Created: {$patient->name} -> {$doctor->user->name} on {$appointmentDate->toDateString()}");
            } else {
                $skipped++;
                $this->command->info("⏭️  Skipped: {$patient->name} -> Dr. {$doctor->user->name} (already exists)");
            }
        }

        $this->command->info("📅 Appointments: {$created} created, {$skipped} skipped");
    }
}
