<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Doctor;

class AIService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api-inference.huggingface.co/v1/models';
    protected string $model = 'meta-llama/Meta-Llama-3-8B-Instruct';
    protected bool $useLocalFallback = true;

    // Supported intents
    public const INTENT_GREET = 'greet';
    public const INTENT_LIST_DOCTORS = 'list_doctors';
    public const INTENT_BOOK_APPOINTMENT = 'book_appointment';
    public const INTENT_RESCHEDULE_APPOINTMENT = 'reschedule_appointment';
    public const INTENT_CANCEL_APPOINTMENT = 'cancel_appointment';
    public const INTENT_CHECK_AVAILABILITY = 'check_availability';
    public const INTENT_EMERGENCY = 'emergency';
    public const INTENT_THANKS = 'thanks';
    public const INTENT_GOODBYE = 'goodbye';
    public const INTENT_HELP = 'help';
    public const INTENT_GENERAL = 'general';

    // Emergency keywords
    protected array $emergencyKeywords = [
        'chest pain', 'severe chest pain', 'heart attack',
        'breathing problem', 'shortness of breath', 'cant breathe',
        'unconscious', 'passed out', 'fainted',
        'heavy bleeding', 'bleeding heavily', 'hemorrhage',
        'stroke', 'paralysis', 'slurred speech',
        'high fever', 'seizure', 'convulsion',
        'suicide', 'overdose', 'poisoning',
        'broken bone', 'fracture', 'severe injury',
        'emergency', 'urgent help', 'life threatening',
    ];

    // Booking keywords
    protected array $bookingKeywords = [
        'book', 'appointment', 'schedule', 'visit', 'see a doctor',
        'consult', 'make an appointment', 'schedule an appointment',
    ];

    // List doctors keywords
    protected array $listDoctorsKeywords = [
        'doctor list', 'list of doctors', 'show doctors', 'available doctors',
        'all doctors', 'browse doctors', 'find a doctor', 'search doctor',
        'which doctors', 'what doctors', 'doctors available', 'dau gcl er',
        'ডাক্তার', 'ডাক্তার তালিকা', 'কোন ডাক্তার',
    ];

    // Cancel keywords
    protected array $cancelKeywords = [
        'cancel', 'cancellation', 'delete appointment', 'remove appointment',
        'dont want', 'no longer need', 'change my mind',
    ];

    // Reschedule keywords
    protected array $rescheduleKeywords = [
        'reschedule', 'postpone', 'delay', 'change appointment',
        'move appointment', 'different time', 'different date',
    ];

    // Specializations mapping
    protected array $specializations = [
        'cardiologist' => 'Cardiology',
        'heart' => 'Cardiology',
        'cardiac' => 'Cardiology',
        'dermatologist' => 'Dermatology',
        'skin' => 'Dermatology',
        'neurologist' => 'Neurology',
        'brain' => 'Neurology',
        'nervous' => 'Neurology',
        'orthopedic' => 'Orthopedics',
        'bone' => 'Orthopedics',
        'joint' => 'Orthopedics',
        'pediatrician' => 'Pediatrics',
        'child' => 'Pediatrics',
        'kids' => 'Pediatrics',
        'psychiatrist' => 'Psychiatry',
        'mental' => 'Psychiatry',
        'anxiety' => 'Psychiatry',
        'depression' => 'Psychiatry',
        'ophthalmologist' => 'Ophthalmology',
        'eye' => 'Ophthalmology',
        'vision' => 'Ophthalmology',
        'ENT' => 'ENT',
        'ear' => 'ENT',
        'nose' => 'ENT',
        'throat' => 'ENT',
        'gastroenterologist' => 'Gastroenterology',
        'stomach' => 'Gastroenterology',
        'digestive' => 'Gastroenterology',
        'urologist' => 'Urology',
        'urinary' => 'Urology',
        'gynecologist' => 'Gynecology',
        'pregnancy' => 'Gynecology',
        'women' => 'Gynecology',
        'oncologist' => 'Oncology',
        'cancer' => 'Oncology',
        'oncology' => 'Oncology',
        'nephrologist' => 'Nephrology',
        'kidney' => 'Nephrology',
        'renal' => 'Nephrology',
        'endocrinologist' => 'Endocrinology',
        'diabetes' => 'Endocrinology',
        'hormone' => 'Endocrinology',
    ];

    public function __construct()
    {
        $this->apiKey = config('services.huggingface.api_key', env('HUGGINGFACE_API_KEY'));
        $this->model = config('services.huggingface.model', env('HUGGINGFACE_MODEL', 'meta-llama/Meta-Llama-3-8B-Instruct'));
        $this->useLocalFallback = env('AI_USE_LOCAL_FALLBACK', true);
    }

    /**
     * Process user message and return structured response
     */
    public function processMessage(string $message, array $context = []): array
    {
        try {
            // First check for emergency
            if ($this->detectEmergency($message)) {
                return $this->buildEmergencyResponse();
            }

            // Try AI API first if available
            if (!empty($this->apiKey) && $this->apiKey !== 'your_huggingface_api_key') {
                try {
                    $systemPrompt = $this->buildSystemPrompt($context);
                    
                    $response = $this->callAPI([
                        'inputs' => "<|system|>\n{$systemPrompt}\n<|user|>\n{$message}\n<|assistant|>",
                        'parameters' => [
                            'max_new_tokens' => 500,
                            'temperature' => 0.3,
                            'return_full_text' => false,
                        ],
                    ]);

                    $content = $this->extractJsonFromResponse($response);

                    if (!empty($content)) {
                        return $this->normalizeResponse($content);
                    }
                } catch (Exception $e) {
                    Log::warning('AI API failed, using local fallback: ' . $e->getMessage());
                }
            }

            // Use local fallback
            return $this->processLocally($message, $context);
        } catch (Exception $e) {
            Log::error('AI Service Error: ' . $e->getMessage());
            
            return [
                'intent' => self::INTENT_GENERAL,
                'response' => 'I apologize, I encountered an issue. Please try again.',
                'extracted_data' => [],
                'emergency_detected' => false,
            ];
        }
    }

    /**
     * Process message locally with rule-based logic
     */
    protected function processLocally(string $message, array $context): array
    {
        $lowerMessage = strtolower($message);
        $intent = $this->detectIntent($lowerMessage);
        $extractedData = $this->extractEntitiesFromMessage($message);
        
        // Build response based on intent
        $response = $this->buildResponse($intent, $extractedData, $context);

        return [
            'intent' => $intent,
            'response' => $response,
            'extracted_data' => $extractedData,
            'emergency_detected' => false,
        ];
    }

    /**
     * Detect intent from message
     */
    protected function detectIntent(string $message): string
    {
        // Check for emergency first
        if ($this->detectEmergency($message)) {
            return self::INTENT_EMERGENCY;
        }

        // Check for greeting
        if ($this->matchesAny($message, ['hello', 'hi ', 'hey', 'good morning', 'good afternoon', 'good evening'])) {
            return self::INTENT_GREET;
        }

        // Check for thanks
        if ($this->matchesAny($message, ['thank', 'thanks', 'appreciate'])) {
            return self::INTENT_THANKS;
        }

        // Check for goodbye
        if ($this->matchesAny($message, ['bye', 'goodbye', 'see you', 'talk later'])) {
            return self::INTENT_GOODBYE;
        }

        // Check for help
        if ($this->matchesAny($message, ['help', 'what can you do', 'capabilities'])) {
            return self::INTENT_HELP;
        }

        // Check for cancel
        if ($this->matchesAny($message, $this->cancelKeywords)) {
            return self::INTENT_CANCEL_APPOINTMENT;
        }

        // Check for reschedule
        if ($this->matchesAny($message, $this->rescheduleKeywords)) {
            return self::INTENT_RESCHEDULE_APPOINTMENT;
        }

        // Check for list doctors
        if ($this->matchesAny($message, $this->listDoctorsKeywords)) {
            return self::INTENT_LIST_DOCTORS;
        }

        // Check for availability
        if ($this->matchesAny($message, ['available', 'availability', 'schedule', 'when are you open', 'timings'])) {
            return self::INTENT_CHECK_AVAILABILITY;
        }

        // Check for booking
        if ($this->matchesAny($message, $this->bookingKeywords)) {
            return self::INTENT_BOOK_APPOINTMENT;
        }

        return self::INTENT_GENERAL;
    }

    /**
     * Extract entities from message
     */
    protected function extractEntitiesFromMessage(string $message): array
    {
        $lowerMessage = strtolower($message);
        $entities = [
            'specialization' => null,
            'symptoms' => [],
            'date' => null,
            'time_preference' => null,
            'patient_name' => null,
            'phone' => null,
            'email' => null,
            'location' => null,
            'appointment_number' => null,
        ];

        // Extract specialization
        foreach ($this->specializations as $keyword => $specialization) {
            if (str_contains($lowerMessage, $keyword)) {
                $entities['specialization'] = $specialization;
                break;
            }
        }

        // Extract date patterns
        if (preg_match('/(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/', $message, $matches)) {
            $entities['date'] = $this->parseDate($matches[1]);
        } elseif (preg_match('/(today|tomorrow|next week|monday|tuesday|wednesday|thursday|friday|saturday|sunday)/i', $message, $matches)) {
            $entities['date'] = $this->parseRelativeDate($matches[1]);
        }

        // Extract time preference
        if ($this->matchesAny($lowerMessage, ['morning', 'am', '10 am', '11 am', '9 am'])) {
            $entities['time_preference'] = 'morning';
        } elseif ($this->matchesAny($lowerMessage, ['afternoon', 'pm', '2 pm', '3 pm', '4 pm'])) {
            $entities['time_preference'] = 'afternoon';
        } elseif ($this->matchesAny($lowerMessage, ['evening', '6 pm', '7 pm', '8 pm'])) {
            $entities['time_preference'] = 'evening';
        }

        // Extract phone number
        if (preg_match('/(\+?\d{1,3}[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}/', $message, $matches)) {
            $entities['phone'] = $matches[0];
        }

        // Extract email
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $message, $matches)) {
            $entities['email'] = $matches[0];
        }

        // Extract appointment number
        if (preg_match('/(APT|APP)[-]?\d{6}/i', $message, $matches)) {
            $entities['appointment_number'] = strtoupper($matches[0]);
        }

        // Extract patient name (simple pattern)
        if (preg_match('/(?:my name is|i am|i\'m)\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)/i', $message, $matches)) {
            $entities['patient_name'] = $matches[1];
        }

        return $entities;
    }

    /**
     * Build response based on intent
     */
    protected function buildResponse(string $intent, array $extractedData, array $context): string
    {
        $language = $context['language'] ?? 'en';
        $hour = now()->hour;
        $timeGreeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

        return match ($intent) {
            self::INTENT_GREET => $this->getGreeting($language),
            self::INTENT_LIST_DOCTORS => $this->buildListDoctorsResponse($extractedData, $language),
            self::INTENT_EMERGENCY => $this->getEmergencyResponse($language),
            self::INTENT_BOOK_APPOINTMENT => $this->buildBookingResponse($extractedData, $language),
            self::INTENT_CANCEL_APPOINTMENT => $this->buildCancelResponse($extractedData, $language),
            self::INTENT_RESCHEDULE_APPOINTMENT => $this->buildRescheduleResponse($extractedData, $language),
            self::INTENT_CHECK_AVAILABILITY => $this->buildAvailabilityResponse($extractedData, $language),
            self::INTENT_THANKS => $this->buildThanksResponse($language),
            self::INTENT_GOODBYE => $this->buildGoodbyeResponse($language),
            self::INTENT_HELP => $this->buildHelpResponse($language),
            default => $this->buildGeneralResponse($extractedData, $language),
        };
    }

    /**
     * Build booking response
     */
    protected function buildBookingResponse(array $data, string $language): string
    {
        $specialization = $data['specialization'] ?? null;
        $date = $data['date'] ?? null;
        $time = $data['time_preference'] ?? null;

        if ($specialization && $date && $time) {
            return match($language) {
                'bn' => "চমৎকার! আমি আপনার জন্য একটি {$specialization} ডাক্তারের সাথে {$date} {$time} সময়ে অ্যাপয়েন্টমেন্ট বুক করছি। আপনার নাম এবং ফোন নম্বর কী?",
                'hi' => "बढ़िया! मैं आपके लिए {$date} को {$time} {$specialization} डॉक्टर के साथ अपॉइंटमेंट बुक कर रहा हूं। आपका नाम और फोन नंबर क्या है?",
                default => "Great! I'm booking an appointment with a {$specialization} for you on {$date} during the {$time}. What is your name and phone number?",
            };
        } elseif ($specialization) {
            return match($language) {
                'bn' => "{$specialization} ডাক্তারের অ্যাপয়েন্টমেন্ট নিতে চাইছেন। কোন তারিখ এবং সময় আপনার জন্য সুবিধাজনক?",
                'hi' => "आप {$specialization} डॉक्टर से मिलना चाहते हैं। कौन सी तारीख और समय आपके लिए सुविधाजनक है?",
                default => "I see you'd like to see a {$specialization}. What date and time works best for you?",
            };
        }

        return match($language) {
            'bn' => "আমি আপনাকে ডাক্তারের অ্যাপয়েন্টমেন্ট বুক করতে সাহায্য করতে পারি। আপনি কোন ধরনের ডাক্তার দেখাতে চান? (যেমন: হৃদরোগ বিশেষজ্ঞ, ত্বকের ডাক্তার, চোখের ডাক্তার)",
            'hi' => "मैं आपकी डॉक्टर अपॉइंटमेंट बुक करने में मदद कर सकता हूं। आप किस तरह के डॉक्टर से मिलना चाहते हैं? (जैसे: हृदय विशेषज्ञ, त्वचा विशेषज्ञ, आंखों के डॉक्टर)",
            default => "I can help you book a doctor appointment. What type of doctor would you like to see? (e.g., cardiologist, dermatologist, eye doctor)",
        };
    }

    /**
     * Build list doctors response
     */
    protected function buildListDoctorsResponse(array $data, string $language): string
    {
        $specialization = $data['specialization'] ?? null;
        
        // Fetch doctors from database
        $doctors = Doctor::available()
            ->with(['specialization'])
            ->when($specialization, function ($query) use ($specialization) {
                $query->whereHas('specialization', function ($q) use ($specialization) {
                    $q->where('name', 'LIKE', '%' . $specialization . '%');
                });
            })
            ->limit(10)
            ->get();
        
        if ($doctors->isEmpty()) {
            return match($language) {
                'bn' => "দুঃখিত, এই মুহূর্তে কোনো ডাক্তার পাওয়া যাচ্ছে না। অনুগ্রহ করে আবার চেষ্টা করুন।",
                'hi' => "क्षमा करें, इस समय कोई डॉक्टर उपलब्ध नहीं है। कृपया बाद में पुनः प्रयास करें।",
                default => "Sorry, no doctors are available at the moment. Please try again later.",
            };
        }
        
        $doctorList = "";
        foreach ($doctors as $index => $doctor) {
            $doctorList .= "\n" . ($index + 1) . ". Dr. " . $doctor->user->name;
            if ($doctor->specialization) {
                $doctorList .= " - " . $doctor->specialization->name;
            }
            if ($doctor->hospital_clinic) {
                $doctorList .= " at " . $doctor->hospital_clinic;
            }
            if ($doctor->rating > 0) {
                $doctorList .= " (" . number_format($doctor->rating, 1) . " ⭐)";
            }
        }
        
        return match($language) {
            'bn' => "নিচে আমাদের উপলব্ধ ডাক্তারদের তালিকা:{$doctorList}\n\nকোনো ডাক্তারের সাথে অ্যাপয়েন্টমেন্ট বুক করতে, ডাক্তারের নম্বর বলুন।",
            'hi' => "यहां हमारे उपलब्ध डॉक्टरों की सूची है:{$doctorList}\n\nकिसी डॉक्टर के साथ अपॉइंटमेंट बुक करने के लिए, डॉक्टर का नंबर बताएं।",
            default => "Here are our available doctors:{$doctorList}\n\nTo book an appointment with any doctor, please specify the doctor's number.",
        };
    }

    /**
     * Build cancel response
     */
    protected function buildCancelResponse(array $data, string $language): string
    {
        $aptNumber = $data['appointment_number'] ?? null;

        if ($aptNumber) {
            return match($language) {
                'bn' => "আপনি {$aptNumber} নম্বর অ্যাপয়েন্টমেন্ট বাতিল করতে চাইছেন। আমি এটি বাতিল করছি।",
                'hi' => "आप {$aptNumber} अपॉइंटमेंट रद्द करना चाहते हैं। मैं इसे रद्द कर रहा हूं।",
                default => "You want to cancel appointment {$aptNumber}. I'm processing the cancellation.",
            };
        }

        return match($language) {
            'bn' => "আপনার অ্যাপয়েন্টমেন্ট বাতিল করতে, অনুগ্রহ করে আপনার অ্যাপয়েন্টমেন্ট নম্বর (APT-XXXXXX) দিন।",
            'hi' => "अपनी अपॉइंटमेंट रद्द करने के लिए, कृपया अपना अपॉइंटमेंट नंबर (APT-XXXXXX) दें।",
            default => "To cancel your appointment, please provide your appointment number (APT-XXXXXX).",
        };
    }

    /**
     * Build reschedule response
     */
    protected function buildRescheduleResponse(array $data, string $language): string
    {
        return match($language) {
            'bn' => "আপনার অ্যাপয়েন্টমেন্ট পুনর্নির্ধারণ করতে, অনুগ্রহ করে আপনার অ্যাপয়েন্টমেন্ট নম্বর এবং নতুন তারিখ ও সময় দিন।",
            'hi' => "अपनी अपॉइंटमेंट पुनर्निर्धारित करने के लिए, कृपया अपना अपॉइंटमेंट नंबर और नई तारीख और समय दें।",
            default => "To reschedule your appointment, please provide your appointment number and the new date and time.",
        };
    }

    /**
     * Build availability response
     */
    protected function buildAvailabilityResponse(array $data, string $language): string
    {
        return match($language) {
            'bn' => "আমাদের ডাক্তাররা সাধারণত সকাল ৯টা থেকে সন্ধ্যা ৬টা পর্যন্ত কাজ করেন। আপনি কোন ডাক্তারের সময়সূচি দেখতে চান?",
            'hi' => "हमारे डॉक्टर आमतौर पर सुबह 9 बजे से शाम 6 बजे तक काम करते हैं। आप किस डॉक्टर की उपलब्धता देखना चाहेंगे?",
            default => "Our doctors typically work from 9 AM to 6 PM. Which doctor's availability would you like to check?",
        };
    }

    /**
     * Build thanks response
     */
    protected function buildThanksResponse(string $language): string
    {
        return match($language) {
            'bn' => "আপনাকে ধন্যবাদ! আপনার কোনো প্রশ্ন থাকলে জানাবেন।",
            'hi' => "धन्यवाद! अगर आपके कोई सवाल हों तो बताएं।",
            default => "You're welcome! If you have any questions, feel free to ask.",
        };
    }

    /**
     * Build goodbye response
     */
    protected function buildGoodbyeResponse(string $language): string
    {
        return match($language) {
            'bn' => "বাই! আপনার স্বাস্থ্যের দিকে মনোযোগ দিন।",
            'hi' => "अलविदा! अपनी सेहत का ध्यान रखें।",
            default => "Goodbye! Take care of your health.",
        };
    }

    /**
     * Build help response
     */
    protected function buildHelpResponse(string $language): string
    {
        return match($language) {
            'bn' => "আমি আপনাকে নিম্নলিখিত কাজগুলিতে সাহায্য করতে পারি:\n\n• ডাক্তারের অ্যাপয়েন্টমেন্ট বুক করা\n• অ্যাপয়েন্টমেন্ট বাতিল করা\n• অ্যাপয়েন্টমেন্ট পুনর্নির্ধারণ করা\n• ডাক্তারের উপলব্ধতা জানতে চাওয়া",
            'hi' => "मैं आपकी इन कामों में मदद कर सकता हूं:\n\n• डॉक्टर अपॉइंटमेंट बुक करना\n• अपॉइंटमेंट रद्द करना\n• अपॉइंटमेंट पुनर्निर्धारित करना\n• डॉक्टर की उपलब्धता जानना",
            default => "I can help you with:\n\n• Booking a doctor appointment\n• Canceling an appointment\n• Rescheduling an appointment\n• Checking doctor availability\n\nHow can I assist you today?",
        };
    }

    /**
     * Build general response
     */
    protected function buildGeneralResponse(array $data, string $language): string
    {
        // Get a few available doctors
        $doctors = Doctor::available()
            ->with(['specialization'])
            ->limit(3)
            ->get();
        
        $doctorList = "";
        if ($doctors->isNotEmpty()) {
            foreach ($doctors as $doctor) {
                $doctorList .= "\n• Dr. " . $doctor->user->name;
                if ($doctor->specialization) {
                    $doctorList .= " (" . $doctor->specialization->name . ")";
                }
            }
            $doctorList = "\n\nAvailable doctors:" . $doctorList;
        }
        
        return match($language) {
            'bn' => "আমি আপনার মেডিকেল অ্যাপয়েন্টমেন্ট অ্যাসিস্ট্যান্ট।{$doctorList}\n\nআমি আপনাকে অ্যাপয়েন্টমেন্ট বুক করতে, বাতিল করতে বা পুনর্নির্ধারণ করতে সাহায্য করতে পারি।\n\nআপনি কী চান?",
            'hi' => "मैं आपका मेडिकल अपॉइंटमेंट असिस्टेंट हूं।{$doctorList}\n\nमैं आपकी अपॉइंटमेंट बुक, रद्द या पुनर्निर्धारित करने में मदद कर सकता हूं।\n\nआप क्या चाहते हैं?",
            default => "I'm your Medical Appointment Assistant.{$doctorList}\n\nI can help you book, cancel, or reschedule appointments.\n\nWhat would you like to do?",
        };
    }

    /**
     * Build emergency response
     */
    protected function buildEmergencyResponse(): array
    {
        return [
            'intent' => self::INTENT_EMERGENCY,
            'response' => $this->getEmergencyResponse('en'),
            'extracted_data' => [],
            'emergency_detected' => true,
        ];
    }

    /**
     * Call Hugging Face Inference API
     */
    protected function callAPI(array $payload): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(60)->post($this->baseUrl . '/' . $this->model, $payload);

        if (!$response->successful()) {
            if ($response->status() === 503) {
                return $this->callWithFallbackModel($payload);
            }
            throw new Exception('HuggingFace API Error: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Try with fallback model
     */
    protected function callWithFallbackModel(array $payload): array
    {
        $fallbackModels = [
            'mistralai/Mistral-7B-Instruct-v0.2',
            'microsoft/Phi-3-mini-4k-instruct',
            'TinyLlama/TinyLlama-1.1B-Chat-v1.0',
        ];

        foreach ($fallbackModels as $model) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])->timeout(60)->post($this->baseUrl . '/' . $model, $payload);

                if ($response->successful()) {
                    return $response->json();
                }
            } catch (Exception $e) {
                Log::warning("Fallback model {$model} failed: " . $e->getMessage());
            }
        }

        throw new Exception('All models are loading or unavailable');
    }

    /**
     * Extract JSON from response
     */
    protected function extractJsonFromResponse(array $response): ?array
    {
        if (isset($response[0]['generated_text'])) {
            $text = $response[0]['generated_text'];
        } elseif (isset($response['generated_text'])) {
            $text = $response['generated_text'];
        } else {
            return null;
        }

        if (preg_match('/\{.*\}/s', $text, $matches)) {
            $json = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }

        return $this->parseTextAsIntent($text);
    }

    /**
     * Parse plain text as intent
     */
    protected function parseTextAsIntent(string $text): ?array
    {
        $lowerText = strtolower($text);

        $intent = self::INTENT_GENERAL;
        $emergency = false;

        if (str_contains($lowerText, 'emergency') || str_contains($lowerText, 'urgent')) {
            $intent = self::INTENT_EMERGENCY;
            $emergency = true;
        } elseif (str_contains($lowerText, 'book') || str_contains($lowerText, 'appointment')) {
            $intent = self::INTENT_BOOK_APPOINTMENT;
        } elseif (str_contains($lowerText, 'cancel')) {
            $intent = self::INTENT_CANCEL_APPOINTMENT;
        } elseif (str_contains($lowerText, 'reschedule') || str_contains($lowerText, 'change')) {
            $intent = self::INTENT_RESCHEDULE_APPOINTMENT;
        } elseif (str_contains($lowerText, 'hello') || str_contains($lowerText, 'hi ')) {
            $intent = self::INTENT_GREET;
        } elseif (str_contains($lowerText, 'thank')) {
            $intent = self::INTENT_THANKS;
        }

        return [
            'intent' => $intent,
            'response' => $text,
            'extracted_data' => [],
            'emergency' => $emergency,
        ];
    }

    /**
     * Detect emergency in message
     */
    public function detectEmergency(string $message): bool
    {
        $lowerMessage = strtolower($message);
        
        foreach ($this->emergencyKeywords as $keyword) {
            if (str_contains($lowerMessage, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get greeting response
     */
    public function getGreeting(string $language = 'en'): string
    {
        $hour = now()->hour;
        $timeGreeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
        
        // Get a few available doctors for the greeting
        $doctors = Doctor::available()
            ->with(['specialization'])
            ->limit(3)
            ->get();
        
        $doctorList = "";
        if ($doctors->isNotEmpty()) {
            foreach ($doctors as $doctor) {
                $doctorList .= "\n• Dr. " . $doctor->user->name;
                if ($doctor->specialization) {
                    $doctorList .= " (" . $doctor->specialization->name . ")";
                }
            }
        }
        
        return match($language) {
            'bn' => "{$timeGreeting}! 🏥\n\nআমি আপনার মেডিকেল অ্যাপয়েন্টমেন্ট অ্যাসিস্ট্যান্ট।\n\nআমাদের উপলব্ধ ডাক্তার:{$doctorList}\n\nআমি আপনাকে অ্যাপয়েন্টমেন্ট বুক করতে, বাতিল করতে বা পুনর্নির্ধারণ করতে সাহায্য করতে পারি।\n\nআপনাকে কীভাবে সাহায্য করতে পারি?",
            'hi' => "{$timeGreeting}! 🏥\n\nमैं आपका मेडिकल अपॉइंटमेंट असिस्टेंट हूं।\n\nहमारे उपलब्ध डॉक्टर:{$doctorList}\n\nमैं आपकी अपॉइंटमेंट बुक, रद्द या पुनर्निर्धारित करने में मदद कर सकता हूं।\n\nमैं आपकी कैसे मदद कर सकता हूं?",
            default => "{$timeGreeting}! 🏥\n\nI'm your Medical Appointment Assistant.\n\nOur available doctors:{$doctorList}\n\nI can help you book, cancel, or reschedule appointments.\n\nHow can I help you today?",
        };
    }

    /**
     * Get emergency response
     */
    public function getEmergencyResponse(string $language = 'en'): string
    {
        return match($language) {
            'bn' => "⚠️ **জরুরি পরিস্থিতি শনাক্ত হয়েছে!**\n\nএটি একটি জরুরি মেডিকেল পরিস্থিতি হতে পারে। অনুগ্রহ করে তাৎক্ষণিকভাবে:\n\n1. **৯৯৯** (জরুরি সেবা) কল করুন\n2. নিকটতম হাসপাতালে যান\n\nআপনার স্বাস্থ্য সবার আগে।",
            'hi' => "⚠️ **आपातकालीन स्थिति का पता चला!**\n\nकृपया तुरंत **999** पर कॉल करें या निकटतम अस्पताल जाएं।",
            default => "⚠️ **Emergency Situation Detected!**\n\nThis may be a medical emergency. Please immediately:\n\n1. Call **999** (Emergency Services)\n2. Go to the nearest hospital\n\nYour health comes first. Please seek medical attention now.",
        };
    }

    /**
     * Build system prompt
     */
    protected function buildSystemPrompt(array $context): string
    {
        return $this->getSystemPrompt();
    }

    /**
     * Get system prompt
     */
    protected function getSystemPrompt(): string
    {
        return <<<PROMPT
You are a professional Medical Appointment Assistant. You help patients book, reschedule, or cancel doctor appointments.

**IMPORTANT RULES:**
1. You MUST NOT provide medical diagnosis or treatment recommendations
2. You MUST NOT prescribe medications
3. You only assist with appointment booking
4. Reply in the same language the user is using
5. Extract structured information from user messages
6. Be empathetic and professional

**WORKFLOW FOR BOOKING:**
1. Ask for specialization type
2. Ask for preferred date and time
3. Ask for patient name and contact
4. Confirm and book

**WORKFLOW FOR CANCELLATION:**
1. Ask for appointment number
2. Verify and cancel

**EMERGENCY DETECTION:**
If user mentions: chest pain, breathing problems, unconsciousness, heavy bleeding, stroke symptoms - STOP and show emergency warning.

**RESPONSE FORMAT:**
Return JSON:
{
  "intent": "greet|book_appointment|cancel_appointment|reschedule_appointment|check_availability|emergency|help|general",
  "response": "Your reply to user",
  "extracted_data": {
    "specialization": "doctor type or null",
    "symptoms": [],
    "date": "YYYY-MM-DD or null",
    "time_preference": "morning|afternoon|evening|any or null",
    "patient_name": "name or null",
    "phone": "phone or null",
    "appointment_number": "APT-XXXXXX or null"
  },
  "emergency": true|false
}
PROMPT;
    }

    /**
     * Normalize AI response
     */
    protected function normalizeResponse(array $parsed): array
    {
        return [
            'intent' => $parsed['intent'] ?? self::INTENT_GENERAL,
            'response' => $parsed['response'] ?? 'I didn\'t understand. Please try again.',
            'extracted_data' => $parsed['extracted_data'] ?? [],
            'emergency' => $parsed['emergency'] ?? false,
        ];
    }

    /**
     * Helper: Check if message matches any keywords
     */
    protected function matchesAny(string $message, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Parse date string
     */
    protected function parseDate(string $dateStr): string
    {
        try {
            $date = \DateTime::createFromFormat('m/d/Y', $dateStr);
            if (!$date) {
                $date = \DateTime::createFromFormat('m-d-Y', $dateStr);
            }
            if (!$date) {
                $date = \DateTime::createFromFormat('d/m/Y', $dateStr);
            }
            if ($date) {
                return $date->format('Y-m-d');
            }
        } catch (Exception $e) {
            return $dateStr;
        }
        return $dateStr;
    }

    /**
     * Parse relative date
     */
    protected function parseRelativeDate(string $dateStr): string
    {
        $dateStr = strtolower($dateStr);
        $date = now();

        switch ($dateStr) {
            case 'today':
                return $date->format('Y-m-d');
            case 'tomorrow':
                return $date->addDay()->format('Y-m-d');
            case 'next week':
                return $date->addWeek()->format('Y-m-d');
            case 'monday':
                return $date->next('Monday')->format('Y-m-d');
            case 'tuesday':
                return $date->next('Tuesday')->format('Y-m-d');
            case 'wednesday':
                return $date->next('Wednesday')->format('Y-m-d');
            case 'thursday':
                return $date->next('Thursday')->format('Y-m-d');
            case 'friday':
                return $date->next('Friday')->format('Y-m-d');
            case 'saturday':
                return $date->next('Saturday')->format('Y-m-d');
            case 'sunday':
                return $date->next('Sunday')->format('Y-m-d');
            default:
                return $date->format('Y-m-d');
        }
    }
}
