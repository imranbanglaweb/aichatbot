<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DoctorController;
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

// Simple POST test
Route::post('/test-post', function() {
    return ['status' => 'ok', 'message' => 'POST test works'];
});

// Chat routes
Route::prefix('chat')->group(function () {
    Route::post('/message', [ChatController::class, 'message']);
    Route::post('/voice/stt', [ChatController::class, 'speechToText']);
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

// Appointment routes
Route::prefix('appointment')->group(function () {
    Route::post('/book', [AppointmentController::class, 'book']);
    Route::post('/cancel', [AppointmentController::class, 'cancel']);
    Route::post('/reschedule', [AppointmentController::class, 'reschedule']);
    Route::get('/{appointmentNumber}', [AppointmentController::class, 'show']);
});

// Appointments list
Route::get('/appointments', [AppointmentController::class, 'index']);

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

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::put('/password', [AuthController::class, 'changePassword']);
    });

    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/patient', [DashboardController::class, 'patientDashboard']);
        Route::get('/doctor', [DashboardController::class, 'doctorDashboard']);
        Route::get('/admin', [DashboardController::class, 'adminDashboard']);
        Route::get('/sidebar', [DashboardController::class, 'sidebarMenu']);
    });
});
