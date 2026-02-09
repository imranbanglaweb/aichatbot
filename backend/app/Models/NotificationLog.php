<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'user_id',
        'type',
        'status',
        'recipient',
        'subject',
        'message',
        'metadata',
        'error_message',
        'retry_count',
        'scheduled_at',
        'sent_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    // Type constants
    public const TYPE_SMS = 'sms';
    public const TYPE_WHATSAPP = 'whatsapp';
    public const TYPE_EMAIL = 'email';
    public const TYPE_PUSH = 'push';

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    // Relationships
    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeSent($query)
    {
        return $query->whereIn('status', [self::STATUS_SENT, self::STATUS_DELIVERED]);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForAppointment($query, $appointmentId)
    {
        return $query->where('appointment_id', $appointmentId);
    }

    // Helper methods
    public function markAsSent(array $metadata = []): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'metadata' => $metadata,
            'sent_at' => now(),
        ]);
    }

    public function markAsDelivered(array $metadata = []): void
    {
        $this->update([
            'status' => self::STATUS_DELIVERED,
            'metadata' => array_merge($this->metadata ?? [], $metadata),
        ]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    public function schedule(\Carbon\Carbon $scheduledAt): void
    {
        $this->update([
            'status' => self::STATUS_PENDING,
            'scheduled_at' => $scheduledAt,
        ]);
    }

    public function cancel(): void
    {
        $this->update(['status' => self::STATUS_CANCELLED]);
    }

    public function shouldRetry(): bool
    {
        return $this->retry_count < 3;
    }
}
