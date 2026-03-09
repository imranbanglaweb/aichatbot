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
    protected string $googleSpeechBaseUrl = 'https://speech.googleapis.com/v1';
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
    public function speechToText(string $audioFilePath, string $language = null): array
    {
        try {
            if (!file_exists($audioFilePath)) {
                throw new Exception('Audio file not found');
            }

            $requestData = [
                'model' => 'whisper-1',
                'response_format' => 'verbose_json',
            ];

            // Add language if specified (helps with accuracy)
            if ($language && in_array($language, ['en', 'bn', 'hi', 'es', 'fr', 'de', 'zh', 'ar', 'ja'])) {
                $requestData['language'] = $language;
            }

            $response = Http::attach(
                'file',
                file_get_contents($audioFilePath),
                basename($audioFilePath)
            )->withHeaders([
                'Authorization' => 'Bearer ' . $this->openAiApiKey,
            ])->timeout(60)->post($this->openAiBaseUrl . '/audio/transcriptions', $requestData);

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
     * Convert speech to text using Google Cloud Speech-to-Text
     */
    public function speechToTextGoogle(string $audioFilePath, string $language = 'en-US'): array
    {
        try {
            if (!file_exists($audioFilePath)) {
                throw new Exception('Audio file not found');
            }

            // Read and encode audio file to base64
            $audioContent = file_get_contents($audioFilePath);
            $audioContentBase64 = base64_encode($audioContent);

            // Detect audio format from file extension
            $extension = strtolower(pathinfo($audioFilePath, PATHINFO_EXTENSION));
            $encoding = $this->getAudioEncoding($extension);

            $response = Http::post($this->googleSpeechBaseUrl . '/speech:recognize?key=' . $this->googleApiKey, [
                'config' => [
                    'encoding' => $encoding,
                    'sampleRateHertz' => 16000,
                    'languageCode' => $language,
                    'enableAutomaticLanguageDetection' => false,
                    'model' => 'default',
                ],
                'audio' => [
                    'content' => $audioContentBase64,
                ],
            ]);

            if (!$response->successful()) {
                throw new Exception('Google Speech API Error: ' . $response->body());
            }

            $result = $response->json();
            
            // Clean up audio file
            $this->cleanupAudioFile($audioFilePath);

            // Extract transcription from result
            $transcription = '';
            $confidence = 0;
            
            if (isset($result['results']) && !empty($result['results'])) {
                foreach ($result['results'] as $resultItem) {
                    if (isset($resultItem['alternatives']) && !empty($resultItem['alternatives'])) {
                        $transcription .= $resultItem['alternatives'][0]['transcript'] . ' ';
                        $confidence = $resultItem['alternatives'][0]['confidence'] ?? 0;
                    }
                }
            }

            return [
                'success' => true,
                'text' => trim($transcription),
                'language' => $language,
                'confidence' => $confidence,
            ];
        } catch (Exception $e) {
            Log::error('Google Speech-to-Text Error: ' . $e->getMessage());
            $this->cleanupAudioFile($audioFilePath);
            
            return [
                'success' => false,
                'text' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get audio encoding based on file extension
     */
    protected function getAudioEncoding(string $extension): string
    {
        $encodings = [
            'mp3' => 'MP3',
            'wav' => 'LINEAR16',
            'webm' => 'WEBM_OPUS',
            'ogg' => 'OGG_OPUS',
            'flac' => 'FLAC',
            'aac' => 'AAC',
            'm4a' => 'AAC',
        ];

        return $encodings[$extension] ?? 'LINEAR16';
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
                    'speakingRate' => 1.0,
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
            
            // Convert relative URL to full URL
            $fullAudioUrl = config('app.url') . $audioUrl;

            return [
                'success' => true,
                'audio_url' => $fullAudioUrl,
                'audio_content' => $result['audioContent'],
            ];
        } catch (Exception $e) {
            Log::error('Text-to-Speech Error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'audio_url' => null,
                'audio_content' => null,
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
                'name' => 'en-US-WaveNet-F',
                'language_code' => 'en-US',
                'gender' => $gender,
            ],
            'en-GB' => [
                'name' => 'en-GB-WaveNet-F',
                'language_code' => 'en-GB',
                'gender' => $gender,
            ],
            'bn-IN' => [
                'name' => 'bn-IN-WaveNet-A',
                'language_code' => 'bn-IN',
                'gender' => 'FEMALE',
            ],
            'hi-IN' => [
                'name' => 'hi-IN-WaveNet-A',
                'language_code' => 'hi-IN',
                'gender' => 'FEMALE',
            ],
            'es-ES' => [
                'name' => 'es-ES-WaveNet-A',
                'language_code' => 'es-ES',
                'gender' => $gender,
            ],
            'fr-FR' => [
                'name' => 'fr-FR-WaveNet-A',
                'language_code' => 'fr-FR',
                'gender' => $gender,
            ],
            'de-DE' => [
                'name' => 'de-DE-WaveNet-A',
                'language_code' => 'de-DE',
                'gender' => $gender,
            ],
            'zh-CN' => [
                'name' => 'zh-CN-WaveNet-A',
                'language_code' => 'zh-CN',
                'gender' => $gender,
            ],
            'ar-SA' => [
                'name' => 'ar-SA-WaveNet-A',
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
