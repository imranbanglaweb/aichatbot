<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoiceCallEnded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $sessionId;
    public string $endedBy;
    public ?int $duration; // Call duration in seconds

    public function __construct(string $sessionId, string $endedBy, ?int $duration = null)
    {
        $this->sessionId = $sessionId;
        $this->endedBy = $endedBy;
        $this->duration = $duration;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('voice-call.' . $this->sessionId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'voice-call.ended';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->sessionId,
            'ended_by' => $this->endedBy,
            'duration' => $this->duration,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
