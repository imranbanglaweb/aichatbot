<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'specialization_id',
        'external_id',
        'license_number',
        'qualification',
        'experience_years',
        'bio',
        'consultation_fee',
        'hospital_clinic',
        'address',
        'city',
        'rating',
        'total_reviews',
        'languages',
        'available_days',
        'start_time',
        'end_time',
        'slot_duration',
        'is_available',
        'is_verified',
    ];

    protected $casts = [
        'languages' => 'array',
        'available_days' => 'array',
        'consultation_fee' => 'decimal:2',
        'rating' => 'decimal:2',
        'is_available' => 'boolean',
        'is_verified' => 'boolean',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function specialization()
    {
        return $this->belongsTo(Specialization::class);
    }

    public function schedules()
    {
        return $this->hasMany(DoctorSchedule::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    // Scopes
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true)->orWhereNull('is_available');
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true)->orWhereNull('is_verified');
    }

    public function scopeBySpecialization($query, $specializationId)
    {
        return $query->where('specialization_id', $specializationId);
    }

    public function scopeInCity($query, $city)
    {
        return $query->where('city', 'LIKE', "%{$city}%");
    }

    // Helper methods
    public function getFormattedFeeAttribute(): string
    {
        return '$' . number_format($this->consultation_fee, 2);
    }

    public function getAvailableTimeSlotsForDate($date): array
    {
        $dayOfWeek = strtolower(date('l', strtotime($date)));
        
        // If the doctor has explicit available_days set, honour them.  The
        // seeder creates schedules but doesn’t populate this field, so it
        // would otherwise always return empty.  Only enforce the check when
        // available_days is non‑empty.
        if (!empty($this->available_days) && !in_array($dayOfWeek, $this->available_days)) {
            return [];
        }

        $schedule = $this->schedules()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->first();

        if (!$schedule) {
            return [];
        }

        // Get existing appointments for this date
        $existingAppointments = $this->appointments()
            ->where('appointment_date', $date)
            ->whereIn('status', ['pending', 'confirmed'])
            ->pluck('start_time')
            ->toArray();

        // Generate time slots
        $slots = [];
        $startTime = strtotime($schedule->start_time);
        $endTime = strtotime($schedule->end_time);
        $slotDuration = $this->slot_duration * 60; // Convert to seconds

        while ($startTime + $slotDuration <= $endTime) {
            $slotStart = date('H:i:s', $startTime);
            $slotEnd = date('H:i:s', $startTime + $slotDuration);

            // Skip break time
            if ($schedule->break_start && $schedule->break_end) {
                $breakStart = strtotime($schedule->break_start);
                $breakEnd = strtotime($schedule->break_end);
                if ($startTime >= $breakStart && $startTime < $breakEnd) {
                    $startTime += $slotDuration;
                    continue;
                }
            }

            // Check if slot is already booked
            if (!in_array($slotStart, $existingAppointments)) {
                $slots[] = [
                    'start_time' => $slotStart,
                    'end_time' => $slotEnd,
                    'formatted_time' => date('h:i A', $startTime) . ' - ' . date('h:i A', $startTime + $slotDuration),
                ];
            }

            $startTime += $slotDuration;
        }

        return $slots;
    }
}
