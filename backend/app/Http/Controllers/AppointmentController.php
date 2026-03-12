<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Specialization;
use App\Services\NotificationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppointmentController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Book appointment
     * POST /api/appointment/book
     */
    public function book(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'doctor_id' => 'required|exists:doctors,id',
                'appointment_date' => 'required|date|after_or_equal:today',
                'start_time' => 'required',
                'patient_name' => 'required|string|max:255',
                'patient_phone' => 'required|string|max:20',
                'patient_email' => 'nullable|email',
                'reason' => 'nullable|string|max:500',
            ]);

            // Check if doctor is available
            $doctor = Doctor::findOrFail($validated['doctor_id']);
            
            // Check for conflicts
            $conflict = Appointment::where('doctor_id', $doctor->id)
                ->where('appointment_date', $validated['appointment_date'])
                ->where('start_time', $validated['start_time'])
                ->whereIn('status', ['pending', 'confirmed'])
                ->exists();

            if ($conflict) {
                return response()->json([
                    'success' => false,
                    'error' => 'This time slot is already booked. Please choose another time.',
                ], 400);
            }

            // Get the authenticated patient (logged-in user)
            $patient = $request->user();
            
            // If no authenticated user, create or get patient by phone (guest booking)
            if (!$patient) {
                $patient = \App\Models\User::where('phone', $validated['patient_phone'])->first();
                
                if (!$patient) {
                    $patient = \App\Models\User::create([
                        'name' => $validated['patient_name'],
                        'phone' => $validated['patient_phone'],
                        'email' => $validated['patient_email'] ?? null,
                        'password' => bcrypt(\Illuminate\Support\Str::random(16)),
                    ]);
                }
            } else {
                // If user is a doctor or admin, they can't book appointment
                if ($patient->is_doctor || $patient->is_admin) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Only patients can book appointments.',
                    ], 403);
                }
                
                // If patient info is provided, update the user profile
                if (!empty($validated['patient_name']) && $patient->name !== $validated['patient_name']) {
                    $patient->name = $validated['patient_name'];
                }
                if (!empty($validated['patient_phone']) && $patient->phone !== $validated['patient_phone']) {
                    $patient->phone = $validated['patient_phone'];
                }
                if (!empty($validated['patient_email'])) {
                    $patient->email = $validated['patient_email'];
                }
                $patient->save();
            }

            // Calculate end time
            $startTime = \Carbon\Carbon::parse($validated['start_time']);
            $endTime = $startTime->addMinutes($doctor->slot_duration);

            // Create appointment
            $appointment = Appointment::create([
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'appointment_date' => $validated['appointment_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $endTime->format('H:i:s'),
                'status' => 'confirmed',
                'type' => 'in_person',
                'reason' => $validated['reason'] ?? null,
                'fee' => $doctor->consultation_fee,
            ]);

            // Send notifications
            $this->notificationService->sendAppointmentConfirmation($appointment);

            return response()->json([
                'success' => true,
                'message' => 'Appointment booked successfully',
                'appointment' => [
                    'id' => $appointment->id,
                    'appointment_number' => $appointment->appointment_number,
                    'doctor_name' => 'Dr. ' . $doctor->user->name,
                    'specialization' => $doctor->specialization->name,
                    'date' => $appointment->formatted_date,
                    'time' => $appointment->formatted_time,
                    'fee' => $appointment->fee,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Appointment Booking Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to book appointment',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel appointment
     * POST /api/appointment/cancel
     */
    public function cancel(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'appointment_number' => 'required|string|exists:appointments,appointment_number',
                'reason' => 'nullable|string|max:500',
            ]);

            $appointment = Appointment::where('appointment_number', $validated['appointment_number'])
                ->firstOrFail();

            if (!$appointment->canBeCancelled()) {
                return response()->json([
                    'success' => false,
                    'error' => 'This appointment cannot be cancelled because it has already ' . $appointment->status . '.',
                ], 400);
            }

            // Cancel appointment
            $appointment->cancel($validated['reason'] ?? 'Cancelled by patient');

            // Send notifications
            $this->notificationService->sendAppointmentCancellation($appointment, $validated['reason'] ?? '');

            return response()->json([
                'success' => true,
                'message' => 'Appointment cancelled successfully',
            ]);
        } catch (Exception $e) {
            Log::error('Appointment Cancellation Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to cancel appointment',
            ], 500);
        }
    }

    /**
     * Reschedule appointment
     * POST /api/appointment/reschedule
     */
    public function reschedule(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'appointment_number' => 'required|string|exists:appointments,appointment_number',
                'appointment_date' => 'required|date|after_or_equal:today',
                'start_time' => 'required',
            ]);

            $appointment = Appointment::where('appointment_number', $validated['appointment_number'])
                ->firstOrFail();

            if (!$appointment->canBeRescheduled()) {
                return response()->json([
                    'success' => false,
                    'error' => 'This appointment cannot be rescheduled.',
                ], 400);
            }

            // Check for conflicts with new time
            $conflict = Appointment::where('doctor_id', $appointment->doctor_id)
                ->where('appointment_date', $validated['appointment_date'])
                ->where('start_time', $validated['start_time'])
                ->where('id', '!=', $appointment->id)
                ->whereIn('status', ['pending', 'confirmed'])
                ->exists();

            if ($conflict) {
                return response()->json([
                    'success' => false,
                    'error' => 'The new time slot is already booked. Please choose another time.',
                ], 400);
            }

            // Calculate new end time
            $startTime = \Carbon\Carbon::parse($validated['start_time']);
            $endTime = $startTime->addMinutes($appointment->doctor->slot_duration);

            // Update appointment
            $appointment->update([
                'appointment_date' => $validated['appointment_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $endTime->format('H:i:s'),
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Appointment rescheduled successfully',
                'appointment' => [
                    'appointment_number' => $appointment->appointment_number,
                    'date' => $appointment->formatted_date,
                    'time' => $appointment->formatted_time,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Appointment Reschedule Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to reschedule appointment',
            ], 500);
        }
    }

    /**
     * Get appointment details
     * GET /api/appointment/{appointment_number}
     */
    public function show(string $appointmentNumber): JsonResponse
    {
        try {
            $appointment = Appointment::where('appointment_number', $appointmentNumber)
                ->with(['doctor.user', 'doctor.specialization', 'patient'])
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'appointment' => [
                    'appointment_number' => $appointment->appointment_number,
                    'status' => $appointment->status,
                    'date' => $appointment->formatted_date,
                    'time' => $appointment->formatted_time,
                    'type' => $appointment->type,
                    'reason' => $appointment->reason,
                    'fee' => $appointment->fee,
                    'is_paid' => $appointment->is_paid,
                    'doctor' => [
                        'name' => 'Dr. ' . $appointment->doctor->user->name,
                        'specialization' => $appointment->doctor->specialization->name,
                        'hospital' => $appointment->doctor->hospital_clinic,
                        'rating' => $appointment->doctor->rating,
                    ],
                    'patient' => [
                        'name' => $appointment->patient->name,
                        'phone' => $appointment->patient->phone,
                    ],
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Appointment not found',
            ], 404);
        }
    }

    /**
     * List appointments
     * GET /api/appointments
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Appointment::with(['doctor.user', 'doctor.specialization']);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by date range
            if ($request->has('from_date')) {
                $query->where('appointment_date', '>=', $request->from_date);
            }
            if ($request->has('to_date')) {
                $query->where('appointment_date', '<=', $request->to_date);
            }

            // Filter by patient (if authenticated)
            if (auth()->check()) {
                $query->where('patient_id', auth()->id());
            }

            $appointments = $query->orderBy('appointment_date', 'desc')
                ->orderBy('start_time', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => [
                    'appointments' => $appointments->map(function ($appointment) {
                        $doctorName = 'Doctor';
                        $specializationName = 'General';
                        
                        if ($appointment->doctor) {
                            if ($appointment->doctor->user) {
                                $doctorName = 'Dr. ' . $appointment->doctor->user->name;
                            }
                            if ($appointment->doctor->specialization) {
                                $specializationName = $appointment->doctor->specialization->name;
                            }
                        }
                        
                        return [
                            'id' => $appointment->id,
                            'appointment_number' => $appointment->appointment_number,
                            'status' => $appointment->status,
                            'date' => $appointment->formatted_date,
                            'time' => $appointment->formatted_time,
                            'doctor_name' => $doctorName,
                            'specialization' => $specializationName,
                            'can_cancel' => $appointment->canBeCancelled(),
                            'can_reschedule' => $appointment->canBeRescheduled(),
                        ];
                    }),
                    'pagination' => [
                        'current_page' => $appointments->currentPage(),
                        'last_page' => $appointments->lastPage(),
                        'total' => $appointments->total(),
                    ],
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Appointment List Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch appointments',
            ], 500);
        }
    }
}
