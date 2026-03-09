<?php

namespace App\Http\Controllers;

use App\Models\ChatSession;
use App\Services\AIService;
use App\Services\VoiceService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    protected AIService $aiService;
    protected VoiceService $voiceService;

    public function __construct(AIService $aiService, VoiceService $voiceService)
    {
        $this->aiService = $aiService;
        $this->voiceService = $voiceService;
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
            $language = $request->input('language', 'en');

            if (empty($message)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Message is required',
                ], 400);
            }

            // Get or create session with language
            $session = $this->getOrCreateSession($sessionId, $language);

            // Detect emergency
            if ($this->aiService->detectEmergency($message)) {
                $responseText = $this->aiService->getEmergencyResponse($session->language);
                
                return response()->json([
                    'success' => true,
                    'response' => $responseText,
                    'intent' => 'emergency',
                    'emergency' => true,
                    'session_id' => $session->session_id,
                    'language' => $session->language,
                    'audio_url' => null,
                ]);
            }

            // Process with AI
            $result = $this->aiService->processMessage($message, [
                'language' => $session->language,
                'extracted_data' => $session->extracted_data ?? [],
                'current_intent' => $session->current_intent,
            ]);

            // Update session - merge extracted data with existing data
            $existingExtractedData = is_array($session->extracted_data) ? $session->extracted_data : (is_string($session->extracted_data) ? json_decode($session->extracted_data, true) : []);
            $newExtractedData = $result['extracted_data'] ?? [];
            $mergedExtractedData = array_merge($existingExtractedData, $newExtractedData);
            
            $session->update([
                'extracted_data' => $mergedExtractedData,
                'current_intent' => $result['intent'],
                'message_count' => $session->message_count + 1,
                'last_activity_at' => now(),
            ]);

            // Generate TTS audio if response text is provided
            $audioUrl = null;
            $audioContent = null;
            if (!empty($result['response'])) {
                $ttsLanguage = $this->mapLanguageForTTS($session->language);
                $ttsResult = $this->voiceService->textToSpeech($result['response'], $ttsLanguage);
                if ($ttsResult['success']) {
                    $audioUrl = $ttsResult['audio_url'];
                    $audioContent = $ttsResult['audio_content'] ?? null;
                }
            }

            return response()->json([
                'success' => true,
                'response' => $result['response'],
                'intent' => $result['intent'],
                'emergency' => $result['emergency'] ?? false,
                'session_id' => $session->session_id,
                'language' => $session->language,
                'extracted_data' => $result['extracted_data'] ?? [],
                'audio_url' => $audioUrl,
                'audio_content' => $audioContent,
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
     * POST /api/chat/voice/stt
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

            $sessionId = $request->input('session_id');
            $language = $request->input('language', 'en');
            
            // Get or create session
            $session = $this->getOrCreateSession($sessionId, $language);

            // Save uploaded audio
            $audioFile = $request->file('audio');
            $audioPath = $this->voiceService->saveUploadedAudio($audioFile);

            // Convert speech to text with language hint
            $sttLanguage = $this->mapLanguageForSTT($language);
            
            // Use Google STT or OpenAI Whisper based on configuration
            $useGoogleStt = config('services.google.use_stt', false);
            
            if ($useGoogleStt) {
                $sttResult = $this->voiceService->speechToTextGoogle($audioPath, $sttLanguage);
            } else {
                $sttResult = $this->voiceService->speechToText($audioPath, $sttLanguage);
            }

            if (!$sttResult['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $sttResult['error'] ?? 'Failed to transcribe audio',
                ], 500);
            }

            $transcribedText = $sttResult['text'];

            // Process the transcribed text with AI
            $result = $this->aiService->processMessage($transcribedText, [
                'language' => $session->language,
                'extracted_data' => $session->extracted_data ?? [],
                'current_intent' => $session->current_intent,
            ]);

            // Update session - merge extracted data with existing data
            $existingExtractedData = is_array($session->extracted_data) ? $session->extracted_data : (is_string($session->extracted_data) ? json_decode($session->extracted_data, true) : []);
            $newExtractedData = $result['extracted_data'] ?? [];
            $mergedExtractedData = array_merge($existingExtractedData, $newExtractedData);
            
            $session->update([
                'extracted_data' => $mergedExtractedData,
                'current_intent' => $result['intent'],
                'message_count' => $session->message_count + 1,
                'last_activity_at' => now(),
            ]);

            // Generate TTS audio for response
            $audioUrl = null;
            if (!empty($result['response'])) {
                $ttsLanguage = $this->mapLanguageForTTS($session->language);
                $ttsResult = $this->voiceService->textToSpeech($result['response'], $ttsLanguage);
                if ($ttsResult['success']) {
                    $audioUrl = $ttsResult['audio_url'];
                }
            }

            return response()->json([
                'success' => true,
                'text' => $transcribedText,
                'response' => $result['response'],
                'intent' => $result['intent'],
                'emergency' => $result['emergency'] ?? false,
                'session_id' => $session->session_id,
                'language' => $session->language,
                'audio_url' => $audioUrl,
                'extracted_data' => $result['extracted_data'] ?? [],
            ]);
        } catch (Exception $e) {
            Log::error('STT Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to process audio: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Map frontend language code to TTS language code
     */
    protected function mapLanguageForTTS(string $language): string
    {
        $mapping = [
            'en' => 'en-US',
            'bn' => 'bn-IN',
            'hi' => 'hi-IN',
            'es' => 'es-ES',
            'fr' => 'fr-FR',
            'de' => 'de-DE',
            'zh' => 'zh-CN',
            'ar' => 'ar-SA',
            'ja' => 'ja-JP',
        ];

        return $mapping[$language] ?? 'en-US';
    }

    /**
     * Map frontend language code to STT language code
     */
    protected function mapLanguageForSTT(string $language): string
    {
        $mapping = [
            'en' => 'en',
            'bn' => 'bn',
            'hi' => 'hi',
            'es' => 'es',
            'fr' => 'fr',
            'de' => 'de',
            'zh' => 'zh',
            'ar' => 'ar',
            'ja' => 'ja',
        ];

        return $mapping[$language] ?? 'en';
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
    protected function getOrCreateSession(?string $sessionId, string $language = 'en'): ChatSession
    {
        if ($sessionId) {
            $session = ChatSession::where('session_id', $sessionId)->first();
            if ($session) {
                // Update language if provided and different
                if ($language !== 'en' && $session->language !== $language) {
                    $session->update(['language' => $language]);
                }
                return $session;
            }
        }

        return ChatSession::create([
            'session_id' => 'CHAT-' . strtoupper(Str::random(16)),
            'user_id' => null,
            'guest_id' => null,
            'language' => $language,
            'status' => 'active',
            'extracted_data' => [],
            'message_count' => 0,
            'user_agent' => request()->userAgent(),
            'ip_address' => request()->ip(),
        ]);
    }
}
