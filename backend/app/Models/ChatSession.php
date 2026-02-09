<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Str;

class ChatSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'user_id',
        'guest_id',
        'language',
        'status',
        'extracted_data',
        'current_intent',
        'message_count',
        'user_agent',
        'ip_address',
        'started_at',
        'last_activity_at',
        'ended_at',
    ];

    protected $casts = [
        'extracted_data' => 'array',
        'started_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ABANDONED = 'abandoned';

    // Boot method to auto-generate session ID
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($session) {
            if (empty($session->session_id)) {
                $session->session_id = 'CHAT-' . strtoupper(Str::random(16));
            }
        });
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function notificationLogs()
    {
        return $this->hasMany(NotificationLog::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeBySessionId($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    // Helper methods
    public function getSessionId(): string
    {
        return $this->session_id;
    }

    public function updateActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }

    public function incrementMessageCount(): void
    {
        $this->increment('message_count');
    }

    public function setExtractedData(array $data): void
    {
        $this->update(['extracted_data' => array_merge($this->extracted_data ?? [], $data)]);
    }

    public function setCurrentIntent(string $intent): void
    {
        $this->update(['current_intent' => $intent]);
    }

    public function setLanguage(string $language): void
    {
        $this->update(['language' => $language]);
    }

    public function complete(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'ended_at' => now(),
        ]);
    }

    public function abandon(): void
    {
        $this->update([
            'status' => self::STATUS_ABANDONED,
            'ended_at' => now(),
        ]);
    }
}
