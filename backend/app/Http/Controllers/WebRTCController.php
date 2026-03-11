<?php

namespace App\Http\Controllers;

use App\Events\VoiceCallEnded;
use App\Events\VoiceCallIceCandidate;
use App\Events\VoiceCallOffer;
use App\Events\VoiceCallRinging;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebRTCController extends Controller
{
    /**
     * Generate Pusher token for client authentication
     */
    public function authenticatePusher(Request $request)
    {
        $request->validate([
            'channel_name' => 'required|string',
            'socket_id' => 'required|string',
        ]);

        $channelName = $request->input('channel_name');
        $socketId = $request->input('socket_id');

        // For private channels, authorize the user
        // In production, you would verify the user's session and permissions
        
        $user = $request->user();
        
        if ($user) {
            $auth = auth()->user();
            $authCode = base64_encode(config('app.key') . ':' . $socketId);
            
            return response()->json([
                'auth' => $authCode,
                'channel_data' => json_encode([
                    'user_id' => (string) $auth->id,
                    'user_info' => [
                        'name' => $auth->name,
                        'email' => $auth->email,
                    ],
                ]),
            ]);
        }

        // For testing, allow without auth
        $authCode = base64_encode(config('app.key') . ':' . $socketId);
        
        return response()->json([
            'auth' => $authCode,
            'channel_data' => json_encode([
                'user_id' => 'guest-' . Str::random(8),
                'user_info' => [
                    'name' => 'Guest User',
                ],
            ]),
        ]);
    }

    /**
     * Initialize a voice call - create session and send offer
     */
    public function initiateCall(Request $request)
    {
        $request->validate([
            'callee_id' => 'required|string',
            'call_type' => 'nullable|in:audio,video',
        ]);

        $caller = $request->user();
        $sessionId = 'call-' . Str::uuid();
        $callType = $request->input('call_type', 'audio');

        Log::info('Voice call initiated', [
            'session_id' => $sessionId,
            'caller_id' => $caller?->id,
            'callee_id' => $request->input('callee_id'),
            'call_type' => $callType,
        ]);

        return response()->json([
            'success' => true,
            'session_id' => $sessionId,
            'caller_id' => $caller?->id,
            'caller_name' => $caller?->name ?? 'Unknown',
            'callee_id' => $request->input('callee_id'),
            'call_type' => $callType,
            'ice_servers' => $this->getIceServers(),
        ]);
    }

    /**
     * Send voice call offer to the callee via Pusher
     */
    public function sendOffer(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
            'offer' => 'required|string',
            'call_type' => 'nullable|in:audio,video',
        ]);

        $user = $request->user();
        $callType = $request->input('call_type', 'audio');

        // Broadcast the offer event
        event(new VoiceCallOffer(
            $request->input('session_id'),
            $user?->id ?? 'guest',
            $user?->name ?? 'Guest',
            $request->input('offer'),
            $callType
        ));

        Log::info('Voice call offer sent', [
            'session_id' => $request->input('session_id'),
            'caller_id' => $user?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Offer sent successfully',
        ]);
    }

    /**
     * Send voice call answer to the caller via Pusher
     */
    public function sendAnswer(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
            'answer' => 'required|string',
        ]);

        $user = $request->user();

        // Broadcast the answer event
        event(new VoiceCallAnswer(
            $request->input('session_id'),
            $user?->id ?? 'guest',
            $request->input('answer')
        ));

        Log::info('Voice call answer sent', [
            'session_id' => $request->input('session_id'),
            'callee_id' => $user?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Answer sent successfully',
        ]);
    }

    /**
     * Send ICE candidate to the other peer via Pusher
     */
    public function sendIceCandidate(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
            'candidate' => 'required|array',
        ]);

        $user = $request->user();

        // Broadcast the ICE candidate event
        event(new VoiceCallIceCandidate(
            $request->input('session_id'),
            $user?->id ?? 'guest',
            $request->input('candidate')
        ));

        Log::debug('ICE candidate sent', [
            'session_id' => $request->input('session_id'),
            'user_id' => $user?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'ICE candidate sent successfully',
        ]);
    }

    /**
     * Send ringing status to the caller
     */
    public function sendRinging(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
            'caller_id' => 'required|string',
        ]);

        $user = $request->user();

        // Broadcast the ringing event
        event(new VoiceCallRinging(
            $request->input('session_id'),
            $request->input('caller_id'),
            $user?->id ?? 'guest'
        ));

        return response()->json([
            'success' => true,
            'message' => 'Ringing status sent',
        ]);
    }

    /**
     * End a voice call
     */
    public function endCall(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
            'duration' => 'nullable|integer',
        ]);

        $user = $request->user();
        $duration = $request->input('duration');

        // Broadcast the call ended event
        event(new VoiceCallEnded(
            $request->input('session_id'),
            $user?->id ?? 'guest',
            $duration
        ));

        Log::info('Voice call ended', [
            'session_id' => $request->input('session_id'),
            'ended_by' => $user?->id,
            'duration' => $duration,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Call ended successfully',
        ]);
    }

    /**
     * Get ICE servers configuration for WebRTC
     */
    public function getIceServers()
    {
        return [
            [
                'urls' => 'stun:stun.l.google.com:19302',
            ],
            [
                'urls' => 'stun:stun1.l.google.com:19302',
            ],
            // Add TURN server configuration here if needed
            // [
            //     'urls' => 'turn:your-turn-server.com:3478',
            //     'username' => 'user',
            //     'credential' => 'password',
            // ],
        ];
    }

    /**
     * Get Pusher configuration for frontend
     */
    public function getPusherConfig()
    {
        return response()->json([
            'pusher_key' => config('broadcasting.connections.pusher.key'),
            'pusher_cluster' => config('broadcasting.connections.pusher.options.cluster'),
            'pusher_host' => config('broadcasting.connections.pusher.options.host'),
            'pusher_port' => config('broadcasting.connections.pusher.options.port'),
            'pusher_scheme' => config('broadcasting.connections.pusher.options.scheme'),
            'ice_servers' => $this->getIceServers(),
        ]);
    }
}
