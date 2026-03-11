<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoiceCallOffer implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $sessionId;
    public string $callerId;
    public string $callerName;
    public string $offer; // SDP offer
    public string $callType; // 'audio' or 'video'

    public function __construct(string $sessionId, string $callerId, string $callerName, string $offer, string $callType = 'audio')
    {
        $this->sessionId = $sessionId;
        $this->callerId = $callerId;
        $this->callerName = $callerName;
        $this->offer = $offer;
        $this->callType = $callType;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('voice-call.' . $this->sessionId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'voice-call.offer';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->sessionId,
            'caller_id' => $this->callerId,
            'caller_name' => $this->callerName,
            'offer' => $this->offer,
            'call_type' => $this->callType,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
