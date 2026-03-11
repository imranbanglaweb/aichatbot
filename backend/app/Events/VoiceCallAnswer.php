<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoiceCallAnswer implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $sessionId;
    public string $calleeId;
    public string $answer; // SDP answer

    public function __construct(string $sessionId, string $calleeId, string $answer)
    {
        $this->sessionId = $sessionId;
        $this->calleeId = $calleeId;
        $this->answer = $answer;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('voice-call.' . $this->sessionId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'voice-call.answer';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->sessionId,
            'callee_id' => $this->calleeId,
            'answer' => $this->answer,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
