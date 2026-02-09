<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ChatSession;
use App\Models\Doctor;
use App\Models\Specialization;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingService
{
    protected AIService $aiService;
    protected NotificationService $notificationService;

    public function __construct(
        AIService $aiService,
        NotificationService $notificationService
    ) {
        $this->aiService = $aiService;
        $this->notificationService = $notificationService;
    }

    /**
     * Process chat message and return bot response
     */
    public function processChat(ChatSession $session, string $message, bool $isVoice = false): array
    {
        try {
            // Update session activity
            $session->incrementMessageCount();
            $session->updateActivity();

            // Detect emergency first
            if ($this->aiService->detectEmergency($message)) {
                $response = $this->aiService->getEmergencyResponse($session->language);
                
                // Save assistant message
                $this->saveMessage($session, $response, 'assistant');

                return [
                    'response' => $response,
                    'intent' => 'emergency',
                    'emergency' => true,
                    'audio_url' => null,
                ];
            }

            // Process with AI
            $aiResponse = $this->aiService->processMessage($message, [
                'language' => $session->language,
                'extracted_data' => $session->extracted_data,
                'current_intent' => $session->current_intent,
            ]);

            // Update extracted data
            $session->setExtractedData($aiResponse['extracted_data']);
            $session->setCurrentIntent($aiResponse['intent']);

            // Save messages
            $this->saveMessage($session, $message, 'user');
            $this->saveMessage($session, $aiResponse['response'], 'assistant');

            // Handle specific intents
            $response = $aiResponse['response'];
            $audioUrl = null;

            switch ($aiResponse['intent']) {
                case 'book_appointment':
                    $result = $this->handleBooking($session, $aiResponse['extracted_data']);
                    if ($result['success']) {
                        $response = $result['message'];
                    }
                    break;

                case 'cancel_appointment':
                    $result = $this->handleCancellation($session, $aiResponse['extracted_data']);
                    $response = $result['message'];
                    break;

                case 'reschedule_appointment':
                    $result = $this->handleReschedule($session, $aiResponse['extracted_data']);
                    $response = $result['message'];
                    break;

                case 'check_availability':
                    $result = $this->handleAvailabilityCheck($session, $aiResponse['extracted_data']);
                    $response = $result['message'];
                    break;
            }

            return [
                'response' => $response,
                'intent' => $aiResponse['intent'],
                'emergency' => $aiResponse['emergency'],
                'audio_url' => $audioUrl,
                'extracted_data' => $aiResponse['extracted_data'],
            ];
        } catch (Exception $e) {
            Log::error('Chat Processing Error: ' . $e->getMessage());
            
            return [
                'response' => 'I apologize, something went wrong. Please try again.',
                'intent' => 'error',
                'emergency' => false,
                'audio_url' => null,
            ];
        }
    }

    /**
     * Handle appointment booking
     */
    protected function handleBooking(ChatSession $session, array $data): array
    {
        $required = ['specialization', 'date', 'patient_name', 'phone'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return [
                    'success' => false,
                    'message' => "I need some more information to book your appointment. Please provide: " . implode(', ', $required),
                ];
            }
        }

        // Find specialization
        $specialization = Specialization::where('slug', strtolower(str_replace(' ', '-', $data['specialization'])))
            ->orWhere('name', 'LIKE', '%' . $data['specialization'] . '%')
            ->first();

        if (!$specialization) {
            return [
                'success' => false,
                'message' => "Sorry, I couldn't find a specialization for '{$data['specialization']}'. Could you please specify the type of doctor you need?",
            ];
        }

        // Find available doctors
        $doctors = Doctor::where('specialization_id', $specialization->id)
            ->available()
            ->verified()
            ->when(!empty($data['location']), function ($query) use ($data) {
                return $query->where('city', 'LIKE', '%' . $data['location'] . '%');
            })
            ->with(['user', 'specialization'])
            ->limit(5)
            ->get();

        if ($doctors->isEmpty()) {
            return [
                'success' => false,
                'message' => "Sorry, there are no available doctors for {$specialization->name} at the moment.",
            ];
        }

        // Get available slots for first doctor
        $doctor = $doctors->first();
        $slots = $doctor->getAvailableTimeSlotsForDate($data['date']);

        if (empty($slots)) {
            return [
                'success' => false,
                'message' => "Sorry, Dr. {$doctor->user->name} is not available on {$data['date']}. Would you like to check another doctor or date?",
            ];
        }

        // Create or get patient user
        $patient = $this->getOrCreatePatient($data);

        // Book appointment with first available slot
        $slot = $slots[0];
        $appointment = $this->createAppointment($patient, $doctor, $data['date'], $slot);

        // Send notifications
        $this->notificationService->sendAppointmentConfirmation($appointment);

        // Mark session as completed
        $session->complete();

        return [
            'success' => true,
            'message' => "🎉 Appointment booked successfully!\n\n" .
                        "Appointment Number: {$appointment->appointment_number}\n" .
                        "Doctor: Dr. {$doctor->user->name}\n" .
                        "Date: {$appointment->formatted_date}\n" .
                        "Time: {$slot['formatted_time']}\n\n" .
                        "You will receive a confirmation SMS, WhatsApp, and email shortly.",
        ];
    }

    /**
     * Handle appointment cancellation
     */
    protected function handleCancellation(ChatSession $session, array $data): array
    {
        if (empty($data['appointment_number'])) {
            return [
                'success' => false,
                'message' => "To cancel your appointment, please provide your appointment number (e.g., APT-XXXXXXXX).",
            ];
        }

        $appointment = Appointment::where('appointment_number', $data['appointment_number'])
            ->whereIn('status', ['pending', 'confirmed'])
            ->first();

        if (!$appointment) {
            return [
                'success' => false,
                'message' => "I couldn't find an appointment with number {$data['appointment_number']}. Please check and try again.",
            ];
        }

        if (!$appointment->canBeCancelled()) {
            return [
                'success' => false,
                'message' => "Sorry, this appointment cannot be cancelled because it has already " . $appointment->status . ".",
            ];
        }

        // Cancel appointment
        $appointment->cancel($data['cancellation_reason'] ?? 'Cancelled by patient');

        // Send notifications
        $this->notificationService->sendAppointmentCancellation($appointment, $data['cancellation_reason'] ?? '');

        $session->complete();

        return [
            'success' => true,
            'message' => "✅ Your appointment {$appointment->appointment_number} has been cancelled successfully. We hope to see you again soon!",
        ];
    }

    /**
     * Handle appointment reschedule
     */
    protected function handleReschedule(ChatSession $session, array $data): array
    {
        if (empty($data['appointment_number']) || empty($data['date'])) {
            return [
                'success' => false,
                'message' => "To reschedule, please provide your appointment number and the new preferred date.",
            ];
        }

        $appointment = Appointment::where('appointment_number', $data['appointment_number'])
            ->whereIn('status', ['pending', 'confirmed'])
            ->first();

        if (!$appointment) {
            return [
                'success' => false,
                'message' => "I couldn't find an appointment with number {$data['appointment_number']}.",
            ];
        }

        $doctor = $appointment->doctor;
        $slots = $doctor->getAvailableTimeSlotsForDate($data['date']);

        if (empty($slots)) {
            return [
                'success' => false,
                'message' => "Sorry, Dr. {$doctor->user->name} is not available on {$data['date']}. Please try another date.",
            ];
        }

        // Reschedule to first available slot
        $slot = $slots[0];
        $oldDate = $appointment->appointment_date;
        $oldTime = $appointment->start_time;

        $appointment->update([
            'appointment_date' => $data['date'],
            'start_time' => $slot['start_time'],
            'end_time' => $slot['end_time'],
            'status' => 'pending',
        ]);

        $session->complete();

        return [
            'success' => true,
            'message' => "🔄 Your appointment has been rescheduled!\n\n" .
                        "Appointment Number: {$appointment->appointment_number}\n" .
                        "Old: {$oldDate} at {$oldTime}\n" .
                        "New: {$appointment->formatted_date} at {$slot['formatted_time']}\n\n" .
                        "You will receive a confirmation notification.",
        ];
    }

    /**
     * Handle availability check
     */
    protected function handleAvailabilityCheck(ChatSession $session, array $data): array
    {
        if (empty($data['specialization']) || empty($data['date'])) {
            return [
                'success' => false,
                'message' => "To check availability, please specify the doctor type and preferred date.",
            ];
        }

        $specialization = Specialization::where('name', 'LIKE', '%' . $data['specialization'] . '%')
            ->orWhere('slug', strtolower(str_replace(' ', '-', $data['specialization'])))
            ->first();

        if (!$specialization) {
            return [
                'success' => false,
                'message' => "Sorry, I couldn't find specialization for '{$data['specialization']}'.",
            ];
        }

        $doctors = Doctor::where('specialization_id', $specialization->id)
            ->available()
            ->with(['user', 'specialization'])
            ->get();

        if ($doctors->isEmpty()) {
            return [
                'success' => false,
                'message' => "No doctors available for {$specialization->name} at the moment.",
            ];
        }

        $response = "Here are available doctors for {$specialization->name} on {$data['date']}:\n\n";

        foreach ($doctors as $doctor) {
            $slots = $doctor->getAvailableTimeSlotsForDate($data['date']);
            
            $response .= "👨‍⚕️ Dr. {$doctor->user->name}\n";
            $response .= "   ⭐ Rating: {$doctor->rating} ({$doctor->total_reviews} reviews)\n";
            $response .= "   💰 Fee: {$doctor->formatted_fee}\n";
            
            if (!empty($slots)) {
                $response .= "   🕐 Available: ";
                $response .= implode(', ', array_slice(array_map(fn($s) => $s['formatted_time'], $slots), 0, 3));
                $response .= "\n";
            } else {
                $response .= "   ❌ No slots available\n";
            }
            $response .= "\n";
        }

        return [
            'success' => true,
            'message' => $response,
        ];
    }

    /**
     * Get or create patient
     */
    protected function getOrCreatePatient(array $data): User
    {
        // Check if user exists by phone
        $user = User::where('phone', $data['phone'])->first();

        if (!$user) {
            // Create new user
            $user = User::create([
                'name' => $data['patient_name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'password' => bcrypt(str_random(16)),
            ]);
        }

        return $user;
    }

    /**
     * Create appointment
     */
    protected function createAppointment(User $patient, Doctor $doctor, string $date, array $slot): Appointment
    {
        return Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'appointment_date' => $date,
            'start_time' => $slot['start_time'],
            'end_time' => $slot['end_time'],
            'status' => 'confirmed',
            'type' => 'in_person',
            'fee' => $doctor->consultation_fee,
            'reason' => $slot['reason'] ?? null,
        ]);
    }

    /**
     * Save chat message
     */
    protected function saveMessage(ChatSession $session, string $content, string $role): void
    {
        $session->messages()->create([
            'role' => $role,
            'content' => $content,
        ]);
    }
}
