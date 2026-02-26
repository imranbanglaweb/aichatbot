<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\User;
use App\Models\Doctor;
use App\Models\ChatSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get patient dashboard data
     */
    public function patientDashboard(Request $request)
    {
        $user = $request->user();
        
        // Get upcoming appointments
        $upcomingAppointments = Appointment::where('patient_id', $user->id)
            ->where('appointment_date', '>=', now()->toDateString())
            ->where('status', '!=', 'cancelled')
            ->with(['doctor.specialization'])
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->limit(5)
            ->get();

        // Get past appointments
        $pastAppointments = Appointment::where('patient_id', $user->id)
            ->where(function ($query) {
                $query->where('appointment_date', '<', now()->toDateString())
                    ->orWhere('status', 'completed');
            })
            ->with(['doctor.specialization'])
            ->orderBy('appointment_date', 'desc')
            ->orderBy('appointment_time', 'desc')
            ->limit(5)
            ->get();

        // Get appointment statistics
        $stats = [
            'total_appointments' => Appointment::where('patient_id', $user->id)->count(),
            'upcoming_appointments' => Appointment::where('patient_id', $user->id)
                ->where('appointment_date', '>=', now()->toDateString())
                ->where('status', '!=', 'cancelled')
                ->count(),
            'completed_appointments' => Appointment::where('patient_id', $user->id)
                ->where('status', 'completed')
                ->count(),
            'cancelled_appointments' => Appointment::where('patient_id', $user->id)
                ->where('status', 'cancelled')
                ->count(),
        ];

        // Get recent chat sessions
        $recentChats = ChatSession::where('user_id', $user->id)
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();

        // Get recommended doctors (based on common specializations)
        $recommendedDoctors = Doctor::with('specialization')
            ->where('is_available', true)
            ->inRandomOrder()
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'profile_image' => $user->profile_image,
                ],
                'stats' => $stats,
                'upcoming_appointments' => $upcomingAppointments,
                'past_appointments' => $pastAppointments,
                'recent_chats' => $recentChats,
                'recommended_doctors' => $recommendedDoctors,
            ],
        ]);
    }

    /**
     * Get doctor dashboard data
     */
    public function doctorDashboard(Request $request)
    {
        $user = $request->user();
        
        if (!$user->is_doctor || !$user->doctor_id) {
            return response()->json([
                'success' => false,
                'message' => 'Doctor profile not found',
            ], 404);
        }

        $doctor = Doctor::with('specialization')->find($user->doctor_id);

        // Get today's appointments
        $todayAppointments = Appointment::where('doctor_id', $doctor->id)
            ->where('appointment_date', now()->toDateString())
            ->where('status', '!=', 'cancelled')
            ->with(['patient'])
            ->orderBy('appointment_time')
            ->get();

        // Get upcoming appointments
        $upcomingAppointments = Appointment::where('doctor_id', $doctor->id)
            ->where('appointment_date', '>', now()->toDateString())
            ->where('status', '!=', 'cancelled')
            ->with(['patient'])
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->limit(10)
            ->get();

        // Get appointment statistics
        $stats = [
            'total_appointments' => Appointment::where('doctor_id', $doctor->id)->count(),
            'today_appointments' => Appointment::where('doctor_id', $doctor->id)
                ->where('appointment_date', now()->toDateString())
                ->where('status', '!=', 'cancelled')
                ->count(),
            'upcoming_appointments' => Appointment::where('doctor_id', $doctor->id)
                ->where('appointment_date', '>=', now()->toDateString())
                ->where('status', '!=', 'cancelled')
                ->count(),
            'completed_appointments' => Appointment::where('doctor_id', $doctor->id)
                ->where('status', 'completed')
                ->count(),
            'cancelled_appointments' => Appointment::where('doctor_id', $doctor->id)
                ->where('status', 'cancelled')
                ->count(),
        ];

        // Get weekly appointments for chart
        $weeklyAppointments = Appointment::where('doctor_id', $doctor->id)
            ->whereBetween('appointment_date', [now()->startOfWeek(), now()->endOfWeek()])
            ->select(DB::raw('DATE(appointment_date) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->get();

        // Get patient reviews/ratings (placeholder for future implementation)
        $recentPatients = Appointment::where('doctor_id', $doctor->id)
            ->where('status', 'completed')
            ->with(['patient'])
            ->orderBy('appointment_date', 'desc')
            ->limit(10)
            ->get()
            ->unique('patient_id')
            ->take(5);

        return response()->json([
            'success' => true,
            'data' => [
                'doctor' => [
                    'id' => $doctor->id,
                    'name' => $doctor->name,
                    'specialization' => $doctor->specialization->name ?? null,
                    'profile_image' => $doctor->profile_image,
                    'consultation_fee' => $doctor->consultation_fee,
                    'rating' => $doctor->rating,
                ],
                'stats' => $stats,
                'today_appointments' => $todayAppointments,
                'upcoming_appointments' => $upcomingAppointments,
                'weekly_appointments' => $weeklyAppointments,
                'recent_patients' => $recentPatients,
            ],
        ]);
    }

    /**
     * Get admin dashboard data
     */
    public function adminDashboard(Request $request)
    {
        $user = $request->user();
        
        if (!$user->is_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin access required',
            ], 403);
        }

        // Get statistics
        $stats = [
            'total_users' => User::where('is_admin', false)->where('is_doctor', false)->count(),
            'total_doctors' => User::where('is_doctor', true)->count(),
            'total_appointments' => Appointment::count(),
            'today_appointments' => Appointment::where('appointment_date', now()->toDateString())->count(),
        ];

        // Get recent registrations
        $recentUsers = User::where('is_admin', false)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Get today's appointments
        $todayAppointments = Appointment::where('appointment_date', now()->toDateString())
            ->with(['doctor', 'patient'])
            ->orderBy('appointment_time')
            ->limit(20)
            ->get();

        // Get appointment trends (last 7 days)
        $appointmentTrends = Appointment::whereBetween('appointment_date', [now()->subDays(7), now()->toDateString()])
            ->select(DB::raw('DATE(appointment_date) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'recent_users' => $recentUsers,
                'today_appointments' => $todayAppointments,
                'appointment_trends' => $appointmentTrends,
            ],
        ]);
    }

    /**
     * Get sidebar menu based on user role
     */
    public function sidebarMenu(Request $request)
    {
        $user = $request->user();
        $role = $user->is_admin ? 'admin' : ($user->is_doctor ? 'doctor' : 'patient');

        $menus = [
            'patient' => [
                [
                    'id' => 'dashboard',
                    'title' => 'Dashboard',
                    'icon' => 'home',
                    'path' => '/patient/dashboard',
                ],
                [
                    'id' => 'appointments',
                    'title' => 'My Appointments',
                    'icon' => 'calendar',
                    'path' => '/patient/appointments',
                ],
                [
                    'id' => 'doctors',
                    'title' => 'Find Doctors',
                    'icon' => 'users',
                    'path' => '/patient/doctors',
                ],
                [
                    'id' => 'chatbot',
                    'title' => 'AI Assistant',
                    'icon' => 'message-circle',
                    'path' => '/patient/chat',
                ],
                [
                    'id' => 'profile',
                    'title' => 'My Profile',
                    'icon' => 'user',
                    'path' => '/patient/profile',
                ],
                [
                    'id' => 'settings',
                    'title' => 'Settings',
                    'icon' => 'settings',
                    'path' => '/patient/settings',
                ],
            ],
            'doctor' => [
                [
                    'id' => 'dashboard',
                    'title' => 'Dashboard',
                    'icon' => 'home',
                    'path' => '/doctor/dashboard',
                ],
                [
                    'id' => 'appointments',
                    'title' => 'Appointments',
                    'icon' => 'calendar',
                    'path' => '/doctor/appointments',
                ],
                [
                    'id' => 'schedule',
                    'title' => 'My Schedule',
                    'icon' => 'clock',
                    'path' => '/doctor/schedule',
                ],
                [
                    'id' => 'patients',
                    'title' => 'Patients',
                    'icon' => 'users',
                    'path' => '/doctor/patients',
                ],
                [
                    'id' => 'chatbot',
                    'title' => 'AI Assistant',
                    'icon' => 'message-circle',
                    'path' => '/doctor/chat',
                ],
                [
                    'id' => 'profile',
                    'title' => 'My Profile',
                    'icon' => 'user',
                    'path' => '/doctor/profile',
                ],
                [
                    'id' => 'settings',
                    'title' => 'Settings',
                    'icon' => 'settings',
                    'path' => '/doctor/settings',
                ],
            ],
            'admin' => [
                [
                    'id' => 'dashboard',
                    'title' => 'Dashboard',
                    'icon' => 'home',
                    'path' => '/admin/dashboard',
                ],
                [
                    'id' => 'users',
                    'title' => 'Users',
                    'icon' => 'users',
                    'path' => '/admin/users',
                ],
                [
                    'id' => 'doctors',
                    'title' => 'Doctors',
                    'icon' => 'user-md',
                    'path' => '/admin/doctors',
                ],
                [
                    'id' => 'appointments',
                    'title' => 'Appointments',
                    'icon' => 'calendar',
                    'path' => '/admin/appointments',
                ],
                [
                    'id' => 'reports',
                    'title' => 'Reports',
                    'icon' => 'bar-chart',
                    'path' => '/admin/reports',
                ],
                [
                    'id' => 'settings',
                    'title' => 'Settings',
                    'icon' => 'settings',
                    'path' => '/admin/settings',
                ],
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'role' => $role,
                'menus' => $menus[$role] ?? [],
            ],
        ]);
    }
}
