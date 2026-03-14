<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\WebRTCController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Test route
Route::get('/test', function() {
    return ['status' => 'ok', 'message' => 'API is working'];
});

// Debug route - list all appointments (for testing only)
Route::get('/debug/appointments', function() {
    $appointments = \App\Models\Appointment::with(['doctor.user', 'doctor.specialization', 'patient'])
        ->latest()
        ->take(10)
        ->get();
    return [
        'total' => \App\Models\Appointment::count(),
        'appointments' => $appointments->map(function($a) {
            return [
                'id' => $a->id,
                'patient_id' => $a->patient_id,
                'doctor_id' => $a->doctor_id,
                'date' => $a->appointment_date,
                'status' => $a->status,
                'patient_name' => $a->patient?->name,
                'doctor_name' => $a->doctor?->user?->name,
            ];
        })
    ];
});

// Debug - get current user info (requires auth)
Route::middleware('auth:sanctum')->get('/debug/current-user', function() {
    $user = request()->user();
    return [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'is_admin' => $user->is_admin,
        'is_doctor' => $user->is_doctor,
    ];
});

// Simple POST test
Route::post('/test-post', function() {
    return ['status' => 'ok', 'message' => 'POST test works'];
});

// Chat routes
Route::prefix('chat')->group(function () {
    Route::post('/message', [ChatController::class, 'message']);
    Route::post('/voice/stt', [ChatController::class, 'speechToText']);
    Route::post('/transcribe', [ChatController::class, 'transcribe']);
    Route::get('/history/{sessionId}', [ChatController::class, 'history']);
    Route::post('/end/{sessionId}', [ChatController::class, 'end']);
});

// Doctor routes
Route::prefix('doctors')->group(function () {
    Route::get('/available', [DoctorController::class, 'available']);
    Route::get('/{id}', [DoctorController::class, 'show']);
    Route::get('/{id}/slots', [DoctorController::class, 'slots']);
    
    // Sync routes
    Route::post('/sync', [DoctorController::class, 'syncFromMedicare']);
    Route::post('/sync/endpoint', [DoctorController::class, 'syncFromEndpoint']);
    Route::get('/sync/test', [DoctorController::class, 'testConnection']);
});

// Specializations
Route::get('/specializations', [DoctorController::class, 'specializations']);
Route::post('/specializations/sync', [DoctorController::class, 'syncSpecializations']);

// Appointment routes - protected, requires authentication
Route::middleware('auth:sanctum')->prefix('appointment')->group(function () {
    Route::post('/book', [AppointmentController::class, 'book']);
    Route::post('/cancel', [AppointmentController::class, 'cancel']);
    Route::post('/reschedule', [AppointmentController::class, 'reschedule']);
    Route::get('/{appointmentNumber}', [AppointmentController::class, 'show']);
});

// Appointments list - protected route
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/appointments', [AppointmentController::class, 'index']);
});

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Authentication routes (public)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

// WebRTC Voice Call routes
Route::prefix('webrtc')->group(function () {
    // Pusher authentication for private channels
    Route::post('/auth/pusher', [WebRTCController::class, 'authenticatePusher']);
    
    // Get Pusher configuration
    Route::get('/config', [WebRTCController::class, 'getPusherConfig']);
    
    // Call management
    Route::post('/call/initiate', [WebRTCController::class, 'initiateCall']);
    Route::post('/call/offer', [WebRTCController::class, 'sendOffer']);
    Route::post('/call/answer', [WebRTCController::class, 'sendAnswer']);
    Route::post('/call/ice-candidate', [WebRTCController::class, 'sendIceCandidate']);
    Route::post('/call/ringing', [WebRTCController::class, 'sendRinging']);
    Route::post('/call/end', [WebRTCController::class, 'endCall']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::put('/password', [AuthController::class, 'changePassword']);
    });

    // Debug - current user's appointments
    Route::get('/debug/my-appointments', function() {
        $user = request()->user();
        
        if (!$user) {
            return [
                'error' => 'Not authenticated',
                'message' => 'Please login first'
            ];
        }
        
        $appointments = \App\Models\Appointment::with(['doctor.user', 'doctor.specialization'])
            ->where('patient_id', $user->id)
            ->latest()
            ->get();
        return [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'total' => $appointments->count(),
            'appointments' => $appointments->map(function($a) {
                return [
                    'id' => $a->id,
                    'patient_id' => $a->patient_id,
                    'date' => $a->appointment_date,
                    'status' => $a->status,
                    'doctor_name' => $a->doctor?->user?->name,
                ];
            })
        ];
    });

    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/patient', [DashboardController::class, 'patientDashboard']);
        Route::get('/doctor', [DashboardController::class, 'doctorDashboard']);
        Route::get('/admin', [DashboardController::class, 'adminDashboard']);
        Route::get('/sidebar', [DashboardController::class, 'sidebarMenu']);
    });

    // Doctor management (admin)
    Route::prefix('doctors')->group(function () {
        Route::put('/{id}', [DoctorController::class, 'update']);
    });
});
