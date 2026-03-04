<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\Specialization;
use App\Services\DoctorSyncService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DoctorController extends Controller
{
    /**
     * Get available doctors
     * GET /api/doctors/available
     */
    public function available(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'specialization' => 'nullable|string',
                'specialization_id' => 'nullable|integer|exists:specializations,id',
                'date' => 'nullable|date|after_or_equal:today',
                'city' => 'nullable|string',
                'min_rating' => 'nullable|numeric|min:0|max:5',
                'max_fee' => 'nullable|numeric|min:0',
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1|max:50',
            ]);

            // Show all doctors without filtering by available/verified
            $query = Doctor::query()
                ->with(['user', 'specialization']);

            // Filter by specialization
            if (!empty($validated['specialization'])) {
                $query->whereHas('specialization', function ($q) use ($validated) {
                    $q->where('name', 'LIKE', '%' . $validated['specialization'] . '%')
                      ->orWhere('slug', strtolower(str_replace(' ', '-', $validated['specialization'])));
                });
            }

            if (!empty($validated['specialization_id'])) {
                $query->where('specialization_id', $validated['specialization_id']);
            }

            // Filter by city
            if (!empty($validated['city'])) {
                $query->where('city', 'LIKE', '%' . $validated['city'] . '%');
            }

            // Filter by rating
            if (!empty($validated['min_rating'])) {
                $query->where('rating', '>=', $validated['min_rating']);
            }

            // Filter by fee
            if (!empty($validated['max_fee'])) {
                $query->where('consultation_fee', '<=', $validated['max_fee']);
            }

            $page = $validated['page'] ?? 1;
            $perPage = $validated['per_page'] ?? 10;

            $doctors = $query->orderBy('rating', 'desc')
                ->orderBy('total_reviews', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'doctors' => $doctors->map(function ($doctor) {
                    return $this->formatDoctor($doctor);
                }),
                'pagination' => [
                    'current_page' => $doctors->currentPage(),
                    'last_page' => $doctors->lastPage(),
                    'total' => $doctors->total(),
                    'per_page' => $doctors->perPage(),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Get Available Doctors Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch doctors',
            ], 500);
        }
    }

    /**
     * Get doctor details
     * GET /api/doctors/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $doctor = Doctor::available()
                ->with(['user', 'specialization', 'schedules'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'doctor' => $this->formatDoctor($doctor, true),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Doctor not found',
            ], 404);
        }
    }

    /**
     * Get available slots for a doctor
     * GET /api/doctors/{id}/slots
     */
    public function slots(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'date' => 'required|date|after_or_equal:today',
            ]);

            $doctor = Doctor::available()->findOrFail($id);
            $slots = $doctor->getAvailableTimeSlotsForDate($validated['date']);

            return response()->json([
                'success' => true,
                'doctor' => [
                    'id' => $doctor->id,
                    'name' => 'Dr. ' . $doctor->user->name,
                ],
                'date' => $validated['date'],
                'slots' => $slots,
            ]);
        } catch (Exception $e) {
            Log::error('Get Doctor Slots Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch slots',
            ], 500);
        }
    }

    /**
     * Get all specializations
     * GET /api/specializations
     */
    public function specializations(): JsonResponse
    {
        try {
            $specializations = Specialization::active()
                ->withCount('doctors')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'specializations' => $specializations->map(function ($spec) {
                    return [
                        'id' => $spec->id,
                        'name' => $spec->name,
                        'slug' => $spec->slug,
                        'description' => $spec->description,
                        'icon' => $spec->icon,
                        'doctors_count' => $spec->doctors_count,
                        'common_symptoms' => $spec->common_symptoms,
                    ];
                }),
            ]);
        } catch (Exception $e) {
            Log::error('Get Specializations Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch specializations',
            ], 500);
        }
    }

    /**
     * Format doctor data
     */
    protected function formatDoctor(Doctor $doctor, bool $includeSchedules = false): array
    {
        // Get doctor name - try user relationship first, fallback to stored name
        $doctorName = 'Unknown Doctor';
        if ($doctor->user) {
            $rawName = $doctor->user->name;
            // Check if name already starts with Dr., Prof., or similar title
            if (preg_match('/^(Prof|Dr|Mr|Mrs|Ms)\./i', $rawName)) {
                $doctorName = $rawName; // Name already has title
            } else {
                $doctorName = 'Dr. ' . $rawName; // Add Dr. prefix
            }
        } elseif (!empty($doctor->name)) {
            $doctorName = $doctor->name;
        }
        
        $data = [
            'id' => $doctor->id,
            'name' => $doctorName,
            'specialization' => $doctor->specialization ? [
                'id' => $doctor->specialization->id,
                'name' => $doctor->specialization->name,
            ] : null,
            'qualification' => $doctor->qualification ?? 'N/A',
            'experience_years' => $doctor->experience_years ?? 0,
            'bio' => $doctor->bio ?? 'Experienced medical professional',
            'consultation_fee' => $doctor->consultation_fee ?? 0,
            'formatted_fee' => $doctor->formatted_fee ?? '৳0',
            'hospital_clinic' => $doctor->hospital_clinic ?? 'N/A',
            'address' => $doctor->address ?? 'N/A',
            'city' => $doctor->city ?? 'Dhaka',
            'rating' => $doctor->rating ?? 0,
            'total_reviews' => $doctor->total_reviews ?? 0,
            'languages' => $doctor->languages ?? ['en', 'bn'],
            'available_days' => $doctor->available_days ?? [],
            'working_hours' => [
                'start' => $doctor->start_time ?? '09:00:00',
                'end' => $doctor->end_time ?? '17:00:00',
            ],
            'slot_duration' => $doctor->slot_duration ?? 30,
            'is_available' => $doctor->is_available ?? true,
            'is_verified' => $doctor->is_verified ?? true,
            'license_number' => $doctor->license_number ?? 'N/A',
        ];

        if ($includeSchedules) {
            $data['schedules'] = $doctor->schedules->map(function ($schedule) {
                return [
                    'day' => $schedule->day_of_week,
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                    'break_start' => $schedule->break_start,
                    'break_end' => $schedule->break_end,
                    'max_appointments' => $schedule->max_appointments,
                ];
            });
        }

        return $data;
    }

    /**
     * Sync doctors from external Medicare API
     * POST /api/doctors/sync
     */
    public function syncFromMedicare(): JsonResponse
    {
        try {
            $syncService = new DoctorSyncService();
            $results = $syncService->syncAllDoctors();

            return response()->json([
                'success' => true,
                'message' => 'Doctor sync completed',
                'results' => $results,
            ]);
        } catch (Exception $e) {
            Log::error('Doctor Sync Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to sync doctors: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync doctors from specific endpoint
     * POST /api/doctors/sync/endpoint
     */
    public function syncFromEndpoint(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'endpoint' => 'required|string',
            ]);

            $syncService = new DoctorSyncService();
            $results = $syncService->syncFromEndpoint($validated['endpoint']);

            return response()->json([
                'success' => true,
                'message' => 'Doctor sync from endpoint completed',
                'results' => $results,
            ]);
        } catch (Exception $e) {
            Log::error('Doctor Endpoint Sync Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to sync doctors from endpoint: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test connection to Medicare API
     * GET /api/doctors/sync/test
     */
    public function testConnection(): JsonResponse
    {
        try {
            $syncService = new DoctorSyncService();
            $result = $syncService->testConnection();

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'] ?? $result['error'],
                'status_code' => $result['status_code'] ?? null,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync specializations from Medicare API
     * POST /api/specializations/sync
     */
    public function syncSpecializations(): JsonResponse
    {
        try {
            $syncService = new DoctorSyncService();
            $results = $syncService->syncSpecializations();

            return response()->json([
                'success' => true,
                'message' => 'Specializations sync completed',
                'results' => $results,
            ]);
        } catch (Exception $e) {
            Log::error('Specializations Sync Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to sync specializations: ' . $e->getMessage(),
            ], 500);
        }
    }
}
