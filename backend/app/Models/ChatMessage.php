<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_session_id',
        'role',
        'content',
        'audio_url',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    // Role constants
    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';
    public const ROLE_SYSTEM = 'system';

    // Relationships
    public function chatSession()
    {
        return $this->belongsTo(ChatSession::class);
    }

    // Scopes
    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    public function scopeUserMessages($query)
    {
        return $query->where('role', self::ROLE_USER);
    }

    public function scopeAssistantMessages($query)
    {
        return $query->where('role', self::ROLE_ASSISTANT);
    }

    // Helper methods
    public function isUserMessage(): bool
    {
        return $this->role === self::ROLE_USER;
    }

    public function isAssistantMessage(): bool
    {
        return $this->role === self::ROLE_ASSISTANT;
    }

    public function hasAudio(): bool
    {
        return !empty($this->audio_url);
    }
}
