<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected ?string $twilioSid;
    protected ?string $twilioToken;
    protected ?string $twilioPhone;
    protected ?string $whatsappPhone;
    protected ?string $whatsappBusinessId;
    protected ?string $whatsappToken;

    public function __construct()
    {
        $this->twilioSid = config('services.twilio.sid');
        $this->twilioToken = config('services.twilio.token');
        $this->twilioPhone = config('services.twilio.phone');
        $this->whatsappPhone = config('services.twilio.whatsapp_phone');
        $this->whatsappBusinessId = config('services.whatsapp.business_id');
        $this->whatsappToken = config('services.whatsapp.token');
    }

    /**
     * Send SMS via Twilio
     */
    public function sendSms(string $to, string $message): array
    {
        if (!$this->twilioSid || !$this->twilioToken) {
            return ['success' => false, 'error' => 'Twilio not configured'];
        }

        try {
            $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->twilioSid}/Messages.json";
            
            $response = Http::withBasicAuth($this->twilioSid, $this->twilioToken)
                ->asForm()
                ->post($url, [
                    'To' => $to,
                    'From' => $this->twilioPhone,
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                return ['success' => true, 'message_sid' => $response['sid']];
            }

            return ['success' => false, 'error' => $response['message'] ?? 'Failed'];
        } catch (Exception $e) {
            Log::error('SMS Error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send WhatsApp message
     */
    public function sendWhatsapp(string $to, string $message): array
    {
        if (!$this->twilioSid || !$this->twilioToken) {
            return ['success' => false, 'error' => 'Twilio not configured'];
        }

        try {
            $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->twilioSid}/Messages.json";
            
            $response = Http::withBasicAuth($this->twilioSid, $this->twilioToken)
                ->asForm()
                ->post($url, [
                    'To' => 'whatsapp:' . $to,
                    'From' => 'whatsapp:' . $this->whatsappPhone,
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                return ['success' => true, 'message_sid' => $response['sid']];
            }

            return ['success' => false, 'error' => $response['message'] ?? 'Failed'];
        } catch (Exception $e) {
            Log::error('WhatsApp Error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
