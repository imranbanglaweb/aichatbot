<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoiceCallIceCandidate implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $sessionId;
    public string $userId;
    public array $candidate;

    public function __construct(string $sessionId, string $userId, array $candidate)
    {
        $this->sessionId = $sessionId;
        $this->userId = $userId;
        $this->candidate = $candidate;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('voice-call.' . $this->sessionId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'voice-call.ice-candidate';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->sessionId,
            'user_id' => $this->userId,
            'candidate' => $this->candidate,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
