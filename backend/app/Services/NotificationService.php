<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
     * Send appointment confirmation SMS
     */
    public function sendAppointmentConfirmationSms(string $phone, array $appointmentData): array
    {
        $message = "Appointment Confirmed!\n\n";
        $message .= "Doctor: Dr. " . ($appointmentData['doctor_name'] ?? 'N/A') . "\n";
        $message .= "Date: " . ($appointmentData['date'] ?? 'N/A') . "\n";
        $message .= "Time: " . ($appointmentData['time'] ?? 'N/A') . "\n";
        $message .= "Patient: " . ($appointmentData['patient_name'] ?? 'N/A') . "\n";
        $message .= "\nThank you for choosing us!";
        
        return $this->sendSms($phone, $message);
    }

    /**
     * Send email
     */
    public function sendEmail(string $to, string $subject, string $body): array
    {
        try {
            // Using Laravel's Mail facade
            Mail::html($body, function ($message) use ($to, $subject) {
                $message->to($to)
                    ->subject($subject);
            });

            return ['success' => true, 'message' => 'Email sent successfully'];
        } catch (Exception $e) {
            Log::error('Email Error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send appointment confirmation email
     */
    public function sendAppointmentConfirmationEmail(string $email, array $appointmentData): array
    {
        $subject = 'Appointment Confirmation - Medical Chatbot';
        
        $body = "
        <html>
        <body style='font-family: Arial, sans-serif; padding: 20px;'>
            <h2 style='color: #2c3e50;'>Appointment Confirmed! ✅</h2>
            <p>Dear <strong>{$appointmentData['patient_name']}</strong>,</p>
            <p>Your appointment has been successfully booked. Here are the details:</p>
            
            <table style='border-collapse: collapse; width: 100%; max-width: 400px; margin: 20px 0;'>
                <tr>
                    <td style='padding: 10px; border: 1px solid #ddd;'><strong>Doctor</strong></td>
                    <td style='padding: 10px; border: 1px solid #ddd;'>Dr. {$appointmentData['doctor_name']}</td>
                </tr>
                <tr>
                    <td style='padding: 10px; border: 1px solid #ddd;'><strong>Date</strong></td>
                    <td style='padding: 10px; border: 1px solid #ddd;'>{$appointmentData['date']}</td>
                </tr>
                <tr>
                    <td style='padding: 10px; border: 1px solid #ddd;'><strong>Time</strong></td>
                    <td style='padding: 10px; border: 1px solid #ddd;'>{$appointmentData['time']}</td>
                </tr>
                <tr>
                    <td style='padding: 10px; border: 1px solid #ddd;'><strong>Patient Name</strong></td>
                    <td style='padding: 10px; border: 1px solid #ddd;'>{$appointmentData['patient_name']}</td>
                </tr>
            </table>
            
            <p style='margin-top: 20px;'>Please arrive 15 minutes before your scheduled time.</p>
            <p>If you need to reschedule or cancel, please contact us.</p>
            
            <p style='margin-top: 30px;'>Thank you for choosing our service!</p>
            <p><strong>Medical Appointment Team</strong></p>
        </body>
        </html>
        ";
        
        return $this->sendEmail($email, $subject, $body);
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

    /**
     * Send appointment confirmation (SMS + Email)
     */
    public function sendAppointmentConfirmation($appointment): array
    {
        $results = [];
        
        // Get patient info
        $patient = $appointment->patient;
        $doctor = $appointment->doctor;
        $patientName = $patient->name ?? 'Patient';
        $patientPhone = $patient->phone ?? '';
        $patientEmail = $patient->email ?? '';
        $doctorName = $doctor->user->name ?? 'Doctor';
        $date = $appointment->appointment_date;
        $time = $appointment->start_time;
        
        // Prepare appointment data for SMS and Email
        $appointmentData = [
            'doctor_name' => $doctorName,
            'date' => $date,
            'time' => $time,
            'patient_name' => $patientName,
        ];
        
        // Send SMS if phone is available
        if (!empty($patientPhone)) {
            $results['sms'] = $this->sendAppointmentConfirmationSms($patientPhone, $appointmentData);
        }
        
        // Send Email if email is available
        if (!empty($patientEmail)) {
            $results['email'] = $this->sendAppointmentConfirmationEmail($patientEmail, $appointmentData);
        }
        
        return $results;
    }
}
