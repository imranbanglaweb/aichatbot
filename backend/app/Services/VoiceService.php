<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VoiceService
{
    protected string $openAiApiKey;
    protected string $googleApiKey;
    protected string $openAiBaseUrl = 'https://api.openai.com/v1';
    protected string $googleBaseUrl = 'https://texttospeech.googleapis.com/v1';
    protected string $storagePath = 'public/audio';

    public function __construct()
    {
        $this->openAiApiKey = config('services.openai.api_key');
        $this->googleApiKey = config('services.google.api_key');
        
        if (!Storage::exists($this->storagePath)) {
            Storage::makeDirectory($this->storagePath);
        }
    }

    /**
     * Convert speech to text using OpenAI Whisper
     */
    public function speechToText(string $audioFilePath): array
    {
        try {
            if (!file_exists($audioFilePath)) {
                throw new Exception('Audio file not found');
            }

            $response = Http::attach(
                'file',
                file_get_contents($audioFilePath),
                basename($audioFilePath)
            )->withHeaders([
                'Authorization' => 'Bearer ' . $this->openAiApiKey,
            ])->timeout(60)->post($this->openAiBaseUrl . '/audio/transcriptions', [
                'model' => 'whisper-1',
                'language' => null, // Auto-detect language
                'response_format' => 'verbose_json',
            ]);

            if (!$response->successful()) {
                throw new Exception('Whisper API Error: ' . $response->body());
            }

            $result = $response->json();
            
            // Clean up audio file
            $this->cleanupAudioFile($audioFilePath);

            return [
                'success' => true,
                'text' => $result['text'],
                'language' => $result['language'] ?? 'unknown',
                'duration' => $result['duration'] ?? 0,
            ];
        } catch (Exception $e) {
            Log::error('Speech-to-Text Error: ' . $e->getMessage());
            $this->cleanupAudioFile($audioFilePath);
            
            return [
                'success' => false,
                'text' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Convert text to speech using Google TTS
     */
    public function textToSpeech(string $text, string $language = 'en-US', string $voiceType = 'female'): array
    {
        try {
            $voiceConfig = $this->getVoiceConfig($language, $voiceType);

            $response = Http::post($this->googleBaseUrl . '/text:synthesize?key=' . $this->googleApiKey, [
                'input' => [
                    'text' => $text,
                ],
                'voice' => [
                    'languageCode' => $voiceConfig['language_code'],
                    'name' => $voiceConfig['name'],
                    'ssmlGender' => $voiceConfig['gender'],
                ],
                'audioConfig' => [
                    'audioEncoding' => 'MP3',
                    'speakingRate' => 0.9,
                    'pitch' => 0,
                    'volumeGainDb' => 0,
                ],
            ]);

            if (!$response->successful()) {
                throw new Exception('Google TTS API Error: ' . $response->body());
            }

            $result = $response->json();
            
            // Save audio file
            $audioContent = base64_decode($result['audioContent']);
            $fileName = 'tts_' . time() . '_' . md5($text) . '.mp3';
            $filePath = storage_path('app/' . $this->storagePath . '/' . $fileName);
            
            file_put_contents($filePath, $audioContent);
            $audioUrl = Storage::url($this->storagePath . '/' . $fileName);

            return [
                'success' => true,
                'audio_url' => $audioUrl,
                'audio_content' => $result['audioContent'],
            ];
        } catch (Exception $e) {
            Log::error('Text-to-Speech Error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'audio_url' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get voice configuration for language
     */
    protected function getVoiceConfig(string $language, string $voiceType): array
    {
        $gender = $voiceType === 'female' ? 'FEMALE' : 'MALE';
        
        $voices = [
            'en-US' => [
                'name' => 'en-US-Neural2-F',
                'language_code' => 'en-US',
                'gender' => $gender,
            ],
            'en-GB' => [
                'name' => 'en-GB-Neural2-F',
                'language_code' => 'en-GB',
                'gender' => $gender,
            ],
            'bn-BD' => [
                'name' => 'bn-BD-Standard-A',
                'language_code' => 'bn-BD',
                'gender' => $gender,
            ],
            'hi-IN' => [
                'name' => 'hi-IN-Standard-A',
                'language_code' => 'hi-IN',
                'gender' => $gender,
            ],
            'es-ES' => [
                'name' => 'es-ES-Standard-A',
                'language_code' => 'es-ES',
                'gender' => $gender,
            ],
            'fr-FR' => [
                'name' => 'fr-FR-Standard-A',
                'language_code' => 'fr-FR',
                'gender' => $gender,
            ],
            'de-DE' => [
                'name' => 'de-DE-Standard-A',
                'language_code' => 'de-DE',
                'gender' => $gender,
            ],
            'zh-CN' => [
                'name' => 'zh-CN-Standard-A',
                'language_code' => 'zh-CN',
                'gender' => $gender,
            ],
            'ar-SA' => [
                'name' => 'ar-SA-Standard-A',
                'language_code' => 'ar-SA',
                'gender' => $gender,
            ],
            'ja-JP' => [
                'name' => 'ja-JP-Standard-A',
                'language_code' => 'ja-JP',
                'gender' => $gender,
            ],
        ];

        return $voices[$language] ?? $voices['en-US'];
    }

    /**
     * Detect language from audio (basic implementation)
     */
    public function detectLanguageFromAudio(string $audioFilePath): string
    {
        // Whisper returns language in transcription response
        $sttResult = $this->speechToText($audioFilePath);
        
        return $sttResult['language'] ?? 'en';
    }

    /**
     * Get supported languages
     */
    public function getSupportedLanguages(): array
    {
        return [
            'en-US' => 'English (US)',
            'en-GB' => 'English (UK)',
            'bn-BD' => 'Bengali (Bangladesh)',
            'hi-IN' => 'Hindi (India)',
            'es-ES' => 'Spanish (Spain)',
            'fr-FR' => 'French (France)',
            'de-DE' => 'German (Germany)',
            'zh-CN' => 'Chinese (Simplified)',
            'ar-SA' => 'Arabic (Saudi Arabia)',
            'ja-JP' => 'Japanese (Japan)',
        ];
    }

    /**
     * Generate SSML for advanced TTS control
     */
    public function generateSsml(string $text, string $language = 'en-US'): string
    {
        $breakTime = $language === 'en-US' ? '500ms' : '400ms';
        
        return <<<SSML
<speak>
    <prosody rate="medium" pitch="0st">
        <break time="{$breakTime}"/>
        {$text}
    </prosody>
</speak>
SSML;
    }

    /**
     * Clean up temporary audio files
     */
    protected function cleanupAudioFile(string $filePath): void
    {
        try {
            if (file_exists($filePath) && str_contains($filePath, 'tmp_')) {
                unlink($filePath);
            }
        } catch (Exception $e) {
            Log::warning('Failed to cleanup audio file: ' . $e->getMessage());
        }
    }

    /**
     * Save uploaded audio file
     */
    public function saveUploadedAudio($file): string
    {
        $extension = $file->getClientOriginalExtension() ?? 'webm';
        $fileName = 'voice_' . time() . '_' . md5($file->getClientOriginalName()) . '.' . $extension;
        $filePath = storage_path('app/' . $this->storagePath . '/' . $fileName);
        
        $file->move(dirname($filePath), basename($filePath));
        
        return $filePath;
    }
}
