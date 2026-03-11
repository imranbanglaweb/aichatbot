<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoiceCallRinging implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $sessionId;
    public string $callerId;
    public string $calleeId;

    public function __construct(string $sessionId, string $callerId, string $calleeId)
    {
        $this->sessionId = $sessionId;
        $this->callerId = $callerId;
        $this->calleeId = $calleeId;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('voice-call.' . $this->sessionId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'voice-call.ringing';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->sessionId,
            'caller_id' => $this->callerId,
            'callee_id' => $this->calleeId,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
