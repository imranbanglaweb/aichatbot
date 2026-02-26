<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'date_of_birth',
        'gender',
        'profile_image',
        'is_active',
        'is_admin',
        'is_doctor',
        'doctor_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'date_of_birth' => 'date',
        'is_active' => 'boolean',
        'is_admin' => 'boolean',
        'is_doctor' => 'boolean',
    ];

    // Relationships
    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function patientAppointments()
    {
        return $this->hasMany(Appointment::class, 'patient_id');
    }

    public function chatSessions()
    {
        return $this->hasMany(ChatSession::class);
    }

    public function notificationLogs()
    {
        return $this->hasMany(NotificationLog::class);
    }

    // Helper methods
    public function isPatient(): bool
    {
        return !$this->is_admin && !$this->is_doctor;
    }

    public function isDoctor(): bool
    {
        return $this->is_doctor;
    }

    public function isAdmin(): bool
    {
        return $this->is_admin;
    }

    public function getFullNameAttribute(): string
    {
        return $this->name;
    }
}
