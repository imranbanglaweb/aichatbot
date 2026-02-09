<?php

namespace App\Http\Controllers;

use App\Models\ChatSession;
use App\Services\AIService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    protected AIService $aiService;

    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Process chat message
     * POST /api/chat/message
     */
    public function message(Request $request): JsonResponse
    {
        try {
            $message = $request->input('message');
            $sessionId = $request->input('session_id');

            if (empty($message)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Message is required',
                ], 400);
            }

            // Get or create session
            $session = $this->getOrCreateSession($sessionId);

            // Detect emergency
            if ($this->aiService->detectEmergency($message)) {
                $responseText = $this->aiService->getEmergencyResponse($session->language);
                
                return response()->json([
                    'success' => true,
                    'response' => $responseText,
                    'intent' => 'emergency',
                    'emergency' => true,
                    'session_id' => $session->session_id,
                    'audio_url' => null,
                ]);
            }

            // Process with AI
            $result = $this->aiService->processMessage($message, [
                'language' => $session->language,
                'extracted_data' => $session->extracted_data ?? [],
                'current_intent' => $session->current_intent,
            ]);

            // Update session
            $session->update([
                'extracted_data' => $result['extracted_data'] ?? [],
                'current_intent' => $result['intent'],
                'message_count' => $session->message_count + 1,
                'last_activity_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'response' => $result['response'],
                'intent' => $result['intent'],
                'emergency' => $result['emergency'] ?? false,
                'session_id' => $session->session_id,
                'extracted_data' => $result['extracted_data'] ?? [],
                'audio_url' => null,
            ]);
        } catch (Exception $e) {
            Log::error('Chat Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to process message',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Speech-to-Text endpoint
     * POST /api/voice/stt
     */
    public function speechToText(Request $request): JsonResponse
    {
        try {
            if (!$request->hasFile('audio')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Audio file is required',
                ], 400);
            }

            // For now, just return a placeholder
            // In production, use Whisper API
            return response()->json([
                'success' => false,
                'error' => 'Voice processing not configured. Please add OpenAI or Whisper API key.',
            ], 400);
        } catch (Exception $e) {
            Log::error('STT Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to process audio',
            ], 500);
        }
    }

    /**
     * Get chat history
     * GET /api/chat/history/{sessionId}
     */
    public function history(string $sessionId): JsonResponse
    {
        try {
            $session = ChatSession::where('session_id', $sessionId)->firstOrFail();

            return response()->json([
                'success' => true,
                'session' => [
                    'id' => $session->session_id,
                    'language' => $session->language,
                    'status' => $session->status,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Session not found',
            ], 404);
        }
    }

    /**
     * End chat session
     * POST /api/chat/end/{sessionId}
     */
    public function end(string $sessionId): JsonResponse
    {
        try {
            $session = ChatSession::where('session_id', $sessionId)->firstOrFail();
            $session->update([
                'status' => 'completed',
                'ended_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Session ended',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Session not found',
            ], 404);
        }
    }

    /**
     * Get or create chat session
     */
    protected function getOrCreateSession(?string $sessionId): ChatSession
    {
        if ($sessionId) {
            $session = ChatSession::where('session_id', $sessionId)->first();
            if ($session) {
                return $session;
            }
        }

        return ChatSession::create([
            'session_id' => 'CHAT-' . strtoupper(Str::random(16)),
            'user_id' => null,
            'guest_id' => null,
            'language' => 'en',
            'status' => 'active',
            'extracted_data' => [],
            'message_count' => 0,
            'user_agent' => request()->userAgent(),
            'ip_address' => request()->ip(),
        ]);
    }
}
