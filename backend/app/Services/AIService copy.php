<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Doctor;

class AIService
{
    protected string $apiKey;
    protected string $model = 'gemini-2.5-flash';
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
    // New intents for enhanced training
    public const INTENT_DOCTOR_INFO = 'doctor_info';
    public const INTENT_CLINIC_INFO = 'clinic_info';
    public const INTENT_SYMPTOMS = 'symptoms';
    public const INTENT_APPOINTMENT_INFO = 'appointment_info';

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
        'want to see a doctor', 'need a doctor', 'see doctor',
        'get appointment', 'fix appointment', 'take appointment',
        // Bangla
        'অ্যাপয়েন্টমেন্ট', 'বুক', 'সাক্ষাৎ', 'ডাক্তার দেখাতে', 'সময় নিতে',
        'আমাকে একটি অ্যাপয়েন্টমেন্ট দিন', 'আমি অ্যাপয়েন্টমেন্ট নিতে চাই',
    ];

    // List doctors keywords with OPD/IPD and Bangla support
    protected array $listDoctorsKeywords = [
        // English
        'doctor list', 'list of doctors', 'show doctors', 'available doctors',
        'all doctors', 'browse doctors', 'find a doctor', 'search doctor',
        'which doctors', 'what doctors', 'doctors available', 'dau gcl er',
        // OPD/IPD terms
        'opd', 'outdoor patient', 'outdoor', 'general opd',
        'ipd', 'indoor patient', 'indoor', 'admitted patient',
        'emergency', '24 hours', '24/7',
        // Common conditions
        'fever', 'cold', 'cough', 'flu', 'headache', 'stomach pain',
        'diabetes', 'blood pressure', 'bp', 'sugar',
        // Bangla
        'ডাক্তার', 'ডাক্তার তালিকা', 'কোন ডাক্তার', 'ডাক্তার দেখাতে চাই',
        'ডাক্তারের তালিকা', 'ডাক্তার কোথায়', 'সব ডাক্তার',
        // Bangla OPD/IPD
        'ওপিডি', 'আউটডোর', 'ইনডোর', 'আইপিডি', 'জরুরি',
        // Bangla common conditions
        'জ্বর', 'সর্দি', 'কাশি', 'মাথাব্যথা', 'পেটে ব্যথা',
        'ডায়াবেটিস', 'উচ্চ রক্তচাপ', 'চিনি',
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

    // Specializations mapping with OPD/IPD and Bangla terms
    protected array $specializations = [
        // OPD / General Medicine terms
        'opd' => 'General Medicine',
        'outdoor' => 'General Medicine',
        'general' => 'General Medicine',
        'medicine' => 'General Medicine',
        'physician' => 'General Medicine',
        'md' => 'General Medicine',
        'fcps part 1' => 'General Medicine',
        // IPD terms
        'ipd' => 'General Medicine',
        'indoor' => 'General Medicine',
        'admitted' => 'General Medicine',
        // Cardiology
        'cardiologist' => 'Cardiology',
        'heart' => 'Cardiology',
        'cardiac' => 'Cardiology',
        'chest pain' => 'Cardiology',
        'bp' => 'Cardiology',
        'blood pressure' => 'Cardiology',
        'heart disease' => 'Cardiology',
        // Dermatology
        'dermatologist' => 'Dermatology',
        'skin' => 'Dermatology',
        'skin disease' => 'Dermatology',
        'dermatology' => 'Dermatology',
        // Neurology
        'neurologist' => 'Neurology',
        'brain' => 'Neurology',
        'nervous' => 'Neurology',
        'neuro' => 'Neurology',
        'headache' => 'Neurology',
        'migraine' => 'Neurology',
        // Orthopedics
        'orthopedic' => 'Orthopedics',
        'orthopaedics' => 'Orthopedics',
        'bone' => 'Orthopedics',
        'joint' => 'Orthopedics',
        'fracture' => 'Orthopedics',
        'back pain' => 'Orthopedics',
        'arthritis' => 'Orthopedics',
        // Pediatrics
        'pediatrician' => 'Pediatrics',
        'pediatric' => 'Pediatrics',
        'child' => 'Pediatrics',
        'children' => 'Pediatrics',
        'kids' => 'Pediatrics',
        'baby' => 'Pediatrics',
        'infant' => 'Pediatrics',
        // Psychiatry
        'psychiatrist' => 'Psychiatry',
        'psychiatric' => 'Psychiatry',
        'mental' => 'Psychiatry',
        'anxiety' => 'Psychiatry',
        'depression' => 'Psychiatry',
        'stress' => 'Psychiatry',
        'mental health' => 'Psychiatry',
        // Ophthalmology
        'ophthalmologist' => 'Ophthalmology',
        'ophthalmology' => 'Ophthalmology',
        'eye' => 'Ophthalmology',
        'eyes' => 'Ophthalmology',
        'vision' => 'Ophthalmology',
        'eye disease' => 'Ophthalmology',
        // ENT
        'ent' => 'ENT',
        'ear' => 'ENT',
        'nose' => 'ENT',
        'throat' => 'ENT',
        'ear nose throat' => 'ENT',
        'hearing' => 'ENT',
        'sinus' => 'ENT',
        // Gastroenterology
        'gastroenterologist' => 'Gastroenterology',
        'gastroenterology' => 'Gastroenterology',
        'gastro' => 'Gastroenterology',
        'stomach' => 'Gastroenterology',
        'digestive' => 'Gastroenterology',
        'liver' => 'Gastroenterology',
        'intestine' => 'Gastroenterology',
        // Urology
        'urologist' => 'Urology',
        'urology' => 'Urology',
        'urinary' => 'Urology',
        'kidney' => 'Urology',
        'urine' => 'Urology',
        // Gynecology
        'gynecologist' => 'Gynecology',
        'gynecology' => 'Gynecology',
        'gynae' => 'Gynecology',
        'pregnancy' => 'Gynecology',
        'women' => 'Gynecology',
        'female' => 'Gynecology',
        'maternity' => 'Gynecology',
        'delivery' => 'Gynecology',
        'childbirth' => 'Gynecology',
        // Oncology
        'oncologist' => 'Oncology',
        'oncology' => 'Oncology',
        'cancer' => 'Oncology',
        'tumor' => 'Oncology',
        // Nephrology
        'nephrologist' => 'Nephrology',
        'nephrology' => 'Nephrology',
        'kidney disease' => 'Nephrology',
        'renal' => 'Nephrology',
        // Endocrinology
        'endocrinologist' => 'Endocrinology',
        'endocrinology' => 'Endocrinology',
        'diabetes' => 'Endocrinology',
        'sugar' => 'Endocrinology',
        'thyroid' => 'Endocrinology',
        'hormone' => 'Endocrinology',

        // Bangla terms - General
        'সাধারণ' => 'General Medicine',
        'মেডিসিন' => 'General Medicine',
        'ডাক্তার' => 'General Medicine',
        'চিকিৎসক' => 'General Medicine',
        
        // Bangla terms - Heart/Cardiology
        'হৃদরোগ' => 'Cardiology',
        'হৃদস্পন্দন' => 'Cardiology',
        'বুকে ব্যথা' => 'Cardiology',
        'উচ্চ রক্তচাপ' => 'Cardiology',
        'উচ্চ রক্তচাপ' => 'Cardiology',
        
        // Bangla terms - Skin/Dermatology
        'চর্ম' => 'Dermatology',
        'চামড়া' => 'Dermatology',
        'ত্বক' => 'Dermatology',
        'চর্মরোগ' => 'Dermatology',
        
        // Bangla terms - Neurology
        'স্নায়ু' => 'Neurology',
        'মস্তিষ্ক' => 'Neurology',
        'মাথাব্যথা' => 'Neurology',
        
        // Bangla terms - Orthopedics
        'হাড়' => 'Orthopedics',
        'জয়েন্ট' => 'Orthopedics',
        'পেশি' => 'Orthopedics',
        'ফ্র্যাকচার' => 'Orthopedics',
        'হাড় ভাঙা' => 'Orthopedics',
        
        // Bangla terms - Pediatrics
        'শিশু' => 'Pediatrics',
        'বাচ্চা' => 'Pediatrics',
        'ছোট বাচ্চা' => 'Pediatrics',
        
        // Bangla terms - Mental Health
        'মানসিক' => 'Psychiatry',
        'দুঃচিন্তা' => 'Psychiatry',
        'বিষণ্নতা' => 'Psychiatry',
        'অবসাদ' => 'Psychiatry',
        
        // Bangla terms - Eye
        'চোখ' => 'Ophthalmology',
        'চোখের' => 'Ophthalmology',
        'দৃষ্টি' => 'Ophthalmology',
        
        // Bangla terms - ENT
        'কান' => 'ENT',
        'নাক' => 'ENT',
        'গলা' => 'ENT',
        
        // Bangla terms - Stomach/Gastroenterology
        'পাকস্থলী' => 'Gastroenterology',
        'পেট' => 'Gastroenterology',
        'আমাশয়' => 'Gastroenterology',
        'কলি' => 'Gastroenterology',
        'লিভার' => 'Gastroenterology',
        
        // Bangla terms - Kidney/Urology
        'কিডনি' => 'Nephrology',
        ' প্রস্রাব' => 'Urology',
        
        // Bangla terms - Gynecology
        'গাইনি' => 'Gynecology',
        'মা' => 'Gynecology',
        'সন্তান' => 'Gynecology',
        'প্রসব' => 'Gynecology',
        'গর্ভবতী' => 'Gynecology',
        
        // Bangla terms - Cancer
        'ক্যান্সার' => 'Oncology',
        'টিউমার' => 'Oncology',
        
        // Bangla terms - Diabetes/Thyroid
        'ডায়াবেটিস' => 'Endocrinology',
        'চিনি' => 'Endocrinology',
        'থাইরয়েড' => 'Endocrinology',
    ];

    /**
     * Training Data: 50 Real Patient Questions (English & Bengali)
     * This data helps train the AI to recognize patient query patterns
     */
    protected array $trainingData = [
        // ====== 1️⃣ APPOINTMENT BOOKING QUESTIONS ======
        // English
        'how can i book an appointment with a doctor' => 'book_appointment',
        'is dr. ahmed available today' => 'check_availability',
        'i want to see a cardiologist tomorrow' => 'book_appointment',
        'can i book an appointment for my mother' => 'book_appointment',
        'what time is the earliest appointment available' => 'check_availability',
        'can i book an appointment online' => 'book_appointment',
        'do i need to create an account to book an appointment' => 'general',
        'can i reschedule my appointment' => 'reschedule_appointment',
        'how can i cancel my appointment' => 'cancel_appointment',
        'can i see the list of available doctors' => 'list_doctors',
        // Bengali
        'আমি কিভাবে ডাক্তারের অ্যাপয়েন্টমেন্ট বুক করব' => 'book_appointment',
        'ডা. আহমেদ আজকে আছেন কি' => 'check_availability',
        'আমি কাল কার্ডিওলজিস্টের কাছে যেতে চাই' => 'book_appointment',
        'আমার মায়ের জন্য অ্যাপয়েন্টমেন্ট নিতে পারি কি' => 'book_appointment',
        'সবার আগের অ্যাপয়েন্টমেন্ট কখন' => 'check_availability',
        'আমি কি অনলাইনে অ্যাপয়েন্টমেন্ট বুক করতে পারি' => 'book_appointment',
        'অ্যাপয়েন্টমেন্ট বুক করতে কি অ্যাকাউন্ট তৈরি করতে হবে' => 'general',
        'আমি কি আমার অ্যাপয়েন্টমেন্ট পরিবর্তন করতে পারি' => 'reschedule_appointment',
        'কিভাবে আমার অ্যাপয়েন্টমেন্ট বাতিল করব' => 'cancel_appointment',
        'ডাক্তারদের তালিকা দেখতে পারি কি' => 'list_doctors',

        // ====== 2️⃣ DOCTOR INFORMATION QUESTIONS ======
        // English
        'which doctor is best for heart problems' => 'doctor_info',
        'do you have a female gynecologist' => 'doctor_info',
        'what are dr. rahman visiting hours' => 'doctor_info',
        'how many years of experience does this doctor have' => 'doctor_info',
        'which doctor treats diabetes' => 'doctor_info',
        'do you have a pediatric specialist' => 'doctor_info',
        'which doctor is available on friday' => 'check_availability',
        'can i see the doctor profile' => 'doctor_info',
        'where did the doctor complete their education' => 'doctor_info',
        'what languages does the doctor speak' => 'doctor_info',
        // Bengali
        'হৃদরোগের জন্য কোন ডাক্তার ভালো' => 'doctor_info',
        'আপনাদের কি মহিলা গাইনোকোলজিস্ট আছে' => 'doctor_info',
        'ডা. রহমানের সময়সূচি কখন' => 'doctor_info',
        'এই ডাক্তারের কত বছর অভিজ্ঞতা' => 'doctor_info',
        'কোন ডাক্তার ডায়াবেটিস চিকিৎসা করেন' => 'doctor_info',
        'আপনাদের কি শিশু বিশেষজ্ঞ আছে' => 'doctor_info',
        'কোন ডাক্তার শুক্রবারে আছেন' => 'check_availability',
        'ডাক্তারের প্রোফাইল দেখতে পারি কি' => 'doctor_info',
        'ডাক্তার কোথায় পড়াশোনা করেছেন' => 'doctor_info',
        'ডাক্তার কি বাংলা বলতে পারেন' => 'doctor_info',

        // ====== 3️⃣ CLINIC / HOSPITAL INFORMATION ======
        // English
        'what are your clinic opening hours' => 'clinic_info',
        'are you open on weekends' => 'clinic_info',
        'where is your clinic located' => 'clinic_info',
        'do you have parking facilities' => 'clinic_info',
        'is emergency service available' => 'clinic_info',
        'do you offer online consultation' => 'clinic_info',
        'do you accept health insurance' => 'clinic_info',
        'what is the consultation fee' => 'clinic_info',
        'do you have a laboratory' => 'clinic_info',
        'do you offer home visit services' => 'clinic_info',
        // Bengali
        'আপনাদের ক্লিনিক কখন খোলে' => 'clinic_info',
        'আপনারা কি সপ্তাহান্তে খোলা থাকেন' => 'clinic_info',
        'আপনাদের ক্লিনিক কোথায়' => 'clinic_info',
        'পার্কিং সুবিধা আছে কি' => 'clinic_info',
        'জরুরি সেবা আছে কি' => 'clinic_info',
        'আপনারা কি অনলাইনে পরামর্শ দেন' => 'clinic_info',
        'স্বাস্থ্য বীমা গ্রহণ করেন কি' => 'clinic_info',
        'পরামর্শ ফি কত' => 'clinic_info',
        'ল্যাবরেটরি আছে কি' => 'clinic_info',
        'বাড়িতে ডাক্তার পাঠানোর সুবিধা আছে কি' => 'clinic_info',

        // ====== 4️⃣ SYMPTOM BASED QUESTIONS ======
        // English
        'i have chest pain which doctor should i see' => 'symptoms',
        'i have fever and cough what should i do' => 'symptoms',
        'which doctor treats stomach pain' => 'symptoms',
        'my child has a high fever what should i do' => 'symptoms',
        'i have severe headache which department should i visit' => 'symptoms',
        'i feel dizzy and weak which doctor should i consult' => 'symptoms',
        'i have skin allergy which doctor should i see' => 'symptoms',
        'my blood pressure is high which doctor can help' => 'symptoms',
        'i have back pain for a long time' => 'symptoms',
        'i have breathing problems' => 'symptoms',
        // Bengali
        'আমার বুকে ব্যথা কোন ডাক্তার দেখাব' => 'symptoms',
        'আমার জ্বর ও কাশি কি করব' => 'symptoms',
        'পেটে ব্যথার জন্য কোন ডাক্তার' => 'symptoms',
        'আমার বাচ্চার উচ্চ জ্বর কি করব' => 'symptoms',
        'আমার মাথা যন্ত্রণান্ধ কোন বিভাগে যাব' => 'symptoms',
        'আমি দুর্বল ও মাথা ঘুরছি কোন ডাক্তার' => 'symptoms',
        'আমার ত্বকে অ্যালার্জি কোন ডাক্তার' => 'symptoms',
        'আমার উচ্চ রক্তচাপ কোন ডাক্তার' => 'symptoms',
        'আমার দীর্ঘদিন ধরে পিঠে ব্যথা' => 'symptoms',
        'আমার শ্বাস নিতে সমস্যা' => 'symptoms',

        // ====== 5️⃣ APPOINTMENT MANAGEMENT ======
        // English
        'can you remind me about my appointment' => 'appointment_info',
        'what documents should i bring to the appointment' => 'appointment_info',
        'how early should i arrive before my appointment' => 'appointment_info',
        'can i change my doctor after booking' => 'appointment_info',
        'can i book multiple appointments' => 'appointment_info',
        'can i see my previous appointments' => 'appointment_info',
        'can i download my prescription' => 'appointment_info',
        'will i get an appointment confirmation message' => 'appointment_info',
        'can i pay consultation fees online' => 'appointment_info',
        'how can i contact the clinic directly' => 'appointment_info',
        // Bengali
        'আমাকে কি অ্যাপয়েন্টমেন্টের কথা মনে করিয়ে দেবেন' => 'appointment_info',
        'অ্যাপয়েন্টমেন্টে কি কি কাগজপত্র নিতে হবে' => 'appointment_info',
        'অ্যাপয়েন্টমেন্টের আগে কত তাড়াতাড়ি আসব' => 'appointment_info',
        'বুকিংয়ের পরে কি ডাক্তার পরিবর্তন করতে পারি' => 'appointment_info',
        'একাধিক অ্যাপয়েন্টমেন্ট নিতে পারি কি' => 'appointment_info',
        'আমার আগের অ্যাপয়েন্টমেন্টগুলো দেখতে পারি কি' => 'appointment_info',
        'আমার প্রেসক্রিপশন ডাউনলোড করতে পারি কি' => 'appointment_info',
        'আমি কি অ্যাপয়েন্টমেন্ট কনফার্মেশন মেসেজ পাব' => 'appointment_info',
        'অনলাইনে পরামর্শ ফি দিতে পারি কি' => 'appointment_info',
        'ক্লিনিকে সরাসরি যোগাযোগ করব কিভাবে' => 'appointment_info',
    ];

    // Keyword mappings for new intents
    protected array $doctorInfoKeywords = [
        // English
        'best doctor', 'good doctor', 'experience', 'visiting hours', 'schedule',
        'profile', 'education', 'qualification', 'languages', 'female doctor',
        'woman doctor', 'male doctor', 'specialist', 'expert',
        // Bengali
        'ভালো ডাক্তার', 'অভিজ্ঞতা', 'সময়সূচি', 'প্রোফাইল', 'শিক্ষা',
        'যোগ্যতা', 'ভাষা', 'মহিলা ডাক্তার', 'পুরুষ ডাক্তার', 'বিশেষজ্ঞ',
    ];

    protected array $clinicInfoKeywords = [
        // English
        'opening hours', 'open hours', 'hours', 'location', 'address', 'parking',
        'emergency', '24 hours', 'online consultation', 'insurance', 'fee',
        'consultation fee', 'price', 'cost', 'laboratory', 'lab', 'home visit',
        // Bengali
        'খোলার সময়', 'খোলা', 'সময়', 'ঠিকানা', 'লোকেশন', 'পার্কিং',
        'জরুরি', '২৪ ঘণ্টা', 'অনলাইন', 'বীমা', 'ফি', 'পরামর্শ ফি',
        'ল্যাব', 'ল্যাবরেটরি', 'বাড়িতে', 'হোম ভিজিট',
    ];

    protected array $symptomKeywords = [
        // English
        'chest pain', 'heart pain', 'fever', 'cough', 'cold', 'flu',
        'stomach pain', 'belly pain', 'headache', 'migraine', 'dizzy',
        'dizziness', 'weak', 'weakness', 'tired', 'fatigue', 'allergy',
        'skin problem', 'rash', 'itching', 'blood pressure', 'bp', 'high bp',
        'diabetes', 'sugar', 'back pain', 'neck pain', 'joint pain',
        'breathing', 'shortness of breath', 'asthma', 'cough', 'vomiting',
        'nausea', 'diarrhea', 'constipation', 'pregnancy', 'baby', 'child',
        // Bengali
        'বুকে ব্যথা', 'হৃদযন্ত্র', 'জ্বর', 'সর্দি', 'কাশি', 'ফ্লু',
        'পেটে ব্যথা', 'মাথাব্যথা', 'মাইগ্রেইন', 'মাথা ঘুরা', 'দুর্বল',
        'ক্লান্ত', 'অ্যালার্জি', 'ত্বক', 'র‌্যাশ', 'চুলকানি',
        'রক্তচাপ', 'উচ্চ রক্তচাপ', 'ডায়াবেটিস', 'চিনি', 'পিঠে ব্যথা',
        'গলায় ব্যথা', 'শ্বাস নিতে সমস্যা', 'আসমা', 'বমি', 'ডায়রিয়া',
        'কোষ্ঠকাঠিন্য', 'গর্ভবতী', 'শিশু', 'বাচ্চা',
    ];

    protected array $appointmentInfoKeywords = [
        // English
        'remind', 'reminder', 'document', 'documents', 'bring', 'arrive',
        'early', 'change doctor', 'switch doctor', 'multiple', 'previous',
        'past', 'history', 'download', 'prescription', 'prescription download',
        'confirmation', 'confirm', 'sms', 'message', 'email', 'pay',
        'payment', 'online payment', 'contact', 'phone', 'call', 'whatsapp',
        // Bengali
        'মনে করিয়ে', 'রিমাইন্ডার', 'কাগজপত্র', 'নিতে হবে', 'আসা',
        'তাড়াতাড়ি', 'ডাক্তার পরিবর্তন', 'একাধিক', 'আগের', 'ইতিহাস',
        'ডাউনলোড', 'প্রেসক্রিপশন', 'কনফার্মেশন', 'মেসেজ', 'এসএমএস',
        'ইমেইল', 'পেমেন্ট', 'অনলাইন পেমেন্ট', 'যোগাযোগ', 'ফোন', 'কল',
    ];

    public function __construct()
    {
        // Use Gemini API (configured in .env)
        $this->apiKey = config('services.gemini.api_key', env('GEMINI_API_KEY'));
        $this->model = config('services.gemini.model', env('GEMINI_MODEL', 'gemini-1.5-flash'));
        
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

            // Try Gemini API (configured in .env)
            if (!empty($this->apiKey) && strpos($this->apiKey, 'gen-lang-client-') === 0) {
                try {
                    $systemPrompt = $this->buildSystemPrompt($context);
                    
                    $response = $this->callGeminiAPI($message, $systemPrompt);
                    
                    $content = $this->extractJsonFromResponse($response);

                    if (!empty($content)) {
                        return $this->normalizeResponse($content);
                    }
                } catch (Exception $e) {
                    Log::warning('Gemini API failed, using local fallback: ' . $e->getMessage());
                }
            }

            // Use local fallback
            return $this->processLocally($message, $context);
        } catch (Exception $e) {
            Log::error('AI Service Error: ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
            
            // Try to return a greeting response as fallback
            $language = $context['language'] ?? 'en';
            try {
                $greetingResponse = $this->getGreeting($language);
                return [
                    'intent' => self::INTENT_GREET,
                    'response' => $greetingResponse,
                    'extracted_data' => [],
                    'emergency_detected' => false,
                ];
            } catch (Exception $e2) {
                // If even greeting fails, return a simple hardcoded response
                return [
                    'intent' => self::INTENT_GENERAL,
                    'response' => 'Hello! I can help you book, cancel, or reschedule medical appointments. How can I assist you today?',
                    'extracted_data' => [],
                    'emergency_detected' => false,
                ];
            }
        }
    }

    /**
     * Process message locally with rule-based logic
     */
    protected function processLocally(string $message, array $context): array
    {
        $detectedIntent = self::INTENT_GENERAL;
        $detectedSpecialization = null;
        try {
            $lowerMessage = strtolower($message);
            $detectedIntent = $this->detectIntent($lowerMessage, $context);
            
            // Try to extract specialization from the message
            foreach ($this->specializations as $keyword => $specialization) {
                if (str_contains($lowerMessage, $keyword)) {
                    $detectedSpecialization = $specialization;
                    Log::debug('Detected specialization in message: ' . $keyword . ' => ' . $specialization);
                    break;
                }
            }
            
            // Try-catch just for entity extraction to prevent crashes
            $extractedData = [];
            try {
                $extractedData = $this->extractEntitiesFromMessage($message);
                Log::debug('Extracted entities from message: ' . json_encode($extractedData));
            } catch (Exception $e) {
                Log::warning('extractEntitiesFromMessage error: ' . $e->getMessage());
            }
            
            // If we detected a specialization, add it to extracted data
            if ($detectedSpecialization !== null) {
                $extractedData['specialization'] = $detectedSpecialization;
            }
            
            // If doctor number is detected, add to extracted data
            $doctorNumber = null;
            try {
                $doctorNumber = $this->extractDoctorNumber($message);
                if ($doctorNumber !== null) {
                    $extractedData['doctor_number'] = $doctorNumber;
                }
            } catch (Exception $e) {
                Log::warning('extractDoctorNumber error: ' . $e->getMessage());
            }
            
            // If doctor name is mentioned, search for the doctor
            $doctorNameFromMessage = $this->extractDoctorName($message);
            if ($doctorNameFromMessage) {
                $extractedData['doctor_name'] = $doctorNameFromMessage;
                Log::debug('Doctor name extracted from message: ' . $doctorNameFromMessage);
            }
            
            // Merge with existing extracted data from context
            if (!empty($context['extracted_data'])) {
                $extractedData = array_merge($context['extracted_data'], $extractedData);
                Log::debug('Merged extracted_data: context=' . json_encode($context['extracted_data']) . ', new=' . json_encode($extractedData));
            }
            
            // Build response based on intent
            $response = $this->buildResponse($detectedIntent, $extractedData, $context);

            return [
                'intent' => $detectedIntent,
                'response' => $response,
                'extracted_data' => $extractedData,
                'emergency_detected' => false,
            ];
        } catch (Exception $e) {
            Log::error('processLocally Error: ' . $e->getMessage());
            
            // Return a simple fallback response but keep the detected intent
            return [
                'intent' => $detectedIntent,
                'response' => "I can help you book a doctor appointment. What type of doctor would you like to see? (e.g., cardiologist, dermatologist, eye doctor)",
                'extracted_data' => [],
                'emergency_detected' => false,
            ];
        }
    }

    /**
     * Detect intent from message
     */
    protected function detectIntent(string $message, array $context = []): string
    {
        $lowerMessage = strtolower($message);
        $currentIntent = $context['current_intent'] ?? null;
        $extractedData = $context['extracted_data'] ?? [];
        
        // Debug log
        Log::debug('detectIntent called with message: ' . $message . ' (lower: ' . $lowerMessage . '), currentIntent: ' . ($currentIntent ?? 'null'));
        
        // ULTRA QUICK CHECK for book/appointment - check exact word match first
        $trimmedMessage = trim($lowerMessage);
        if ($trimmedMessage === 'book' || $trimmedMessage === 'appointment' || 
            $trimmedMessage === 'book appointment' || $trimmedMessage === 'i want to book' ||
            str_starts_with($trimmedMessage, 'book ') || str_starts_with($trimmedMessage, 'appointment')) {
            Log::debug('Detected INTENT_BOOK_APPOINTMENT from quick check: ' . $trimmedMessage);
            return self::INTENT_BOOK_APPOINTMENT;
        }
        
        // Check if message contains a specialization keyword (even without previous booking context)
        $detectedSpecialization = null;
        foreach ($this->specializations as $keyword => $specialization) {
            if (str_contains($lowerMessage, $keyword)) {
                Log::debug('Detected specialization: ' . $keyword . ' => ' . $specialization);
                $detectedSpecialization = $specialization;
                break;
            }
        }
        if ($detectedSpecialization !== null) {
            return self::INTENT_BOOK_APPOINTMENT;
        }
        
        // Check if message is a number 1-10 (doctor selection)
        if (is_numeric($trimmedMessage) && intval($trimmedMessage) >= 1 && intval($trimmedMessage) <= 10) {
            Log::debug('Detected doctor number, assuming booking intent: ' . $trimmedMessage);
            return self::INTENT_BOOK_APPOINTMENT;
        }
        
        // Check if previous intent was booking and user is providing specialization/doctor
        if (!empty($currentIntent) && $currentIntent === 'book_appointment') {
            Log::debug('Previous intent was booking, checking for specialization or doctor number');
            // Check if message contains a specialization
            foreach ($this->specializations as $keyword => $specialization) {
                if (str_contains($lowerMessage, $keyword)) {
                    Log::debug('Detected specialization: ' . $keyword . ' => ' . $specialization);
                    return self::INTENT_BOOK_APPOINTMENT;
                }
            }
            // Check if message is a number (doctor selection)
            if (is_numeric($trimmedMessage) && intval($trimmedMessage) >= 1 && intval($trimmedMessage) <= 10) {
                Log::debug('Detected doctor number in context: ' . $trimmedMessage);
                return self::INTENT_BOOK_APPOINTMENT;
            }
        }
        
        // FIRST check for booking keywords (before training data)
        if ($this->matchesAny($message, $this->bookingKeywords)) {
            Log::debug('Detected INTENT_BOOK_APPOINTMENT from booking keywords');
            return self::INTENT_BOOK_APPOINTMENT;
        }
        
        // Check if message contains a doctor name (direct booking)
        $doctorNameInMessage = $this->extractDoctorName($message);
        if ($doctorNameInMessage) {
            Log::debug('Detected doctor name in message: ' . $doctorNameInMessage);
            return self::INTENT_BOOK_APPOINTMENT;
        }
        
        // Check if user is selecting a doctor number (for booking flow)
        if ($this->isDoctorSelection($message)) {
            Log::debug('Detected INTENT_BOOK_APPOINTMENT from doctor selection');
            return self::INTENT_BOOK_APPOINTMENT;
        }
        
        // Check if user is providing date/time (continue booking flow)
        if ($this->isDateTimeInput($message)) {
            // Extract date from the current message first
            $tempExtracted = [];
            try {
                $tempExtracted = $this->extractEntitiesFromMessage($message);
            } catch (Exception $e) {
                Log::warning('extractEntitiesFromMessage error in date check: ' . $e->getMessage());
            }
            
            // Merge with context data to check for doctor/date
            $checkData = array_merge($extractedData, $tempExtracted);
            
            // If we already have a doctor or date selected in context, continue booking
            if (!empty($checkData['doctor_number']) || 
                !empty($checkData['selected_doctor_id']) ||
                !empty($checkData['date'])) {
                return self::INTENT_BOOK_APPOINTMENT;
            }
        }
        
        // Check if user is providing name/phone (continue booking flow)
        if ($this->isContactInfoInput($message)) {
            // Extract contact info from the current message first
            $tempExtracted = [];
            try {
                $tempExtracted = $this->extractEntitiesFromMessage($message);
            } catch (Exception $e) {
                Log::warning('extractEntitiesFromMessage error in contact check: ' . $e->getMessage());
            }
            
            // Merge with context data to check for doctor/date/time
            $checkData = array_merge($extractedData, $tempExtracted);
            $contextData = $context['extracted_data'] ?? [];
            $mergedCheckData = array_merge($contextData, $checkData);
            
            // If we already have doctor, date, time in context, continue booking
            if ((!empty($mergedCheckData['doctor_number']) || !empty($mergedCheckData['selected_doctor_id'])) &&
                (!empty($mergedCheckData['date'])) &&
                (!empty($mergedCheckData['time_preference']))) {
                return self::INTENT_BOOK_APPOINTMENT;
            }
        }

        // Check for emergency first
        if ($this->detectEmergency($message)) {
            return self::INTENT_EMERGENCY;
        }

        // Check training data first for exact matches
        $intent = $this->matchTrainingData($message);
        Log::debug('matchTrainingData returned: ' . $intent);
        if ($intent !== self::INTENT_GENERAL) {
            return $intent;
        }

        // Check for greeting
        if ($this->matchesAny($message, ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening'])) {
            Log::debug('Detected INTENT_GREET');
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

        // Check for doctor info
        if ($this->matchesAny($message, $this->doctorInfoKeywords)) {
            return self::INTENT_DOCTOR_INFO;
        }

        // Check for clinic info
        if ($this->matchesAny($message, $this->clinicInfoKeywords)) {
            return self::INTENT_CLINIC_INFO;
        }

        // Check for symptoms
        if ($this->matchesAny($message, $this->symptomKeywords)) {
            return self::INTENT_SYMPTOMS;
        }

        // Check for appointment info
        if ($this->matchesAny($message, $this->appointmentInfoKeywords)) {
            return self::INTENT_APPOINTMENT_INFO;
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
            Log::debug('Detected INTENT_BOOK_APPOINTMENT from keywords: ' . implode(', ', $this->bookingKeywords));
            return self::INTENT_BOOK_APPOINTMENT;
        }

        Log::debug('No intent detected, returning INTENT_GENERAL');
        return self::INTENT_GENERAL;
    }

    /**
     * Check if message contains date/time input
     */
    protected function isDateTimeInput(string $message): bool
    {
        $lowerMessage = strtolower(trim($message));
        
        // English date keywords
        $dateKeywords = ['today', 'tomorrow', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday', 'next week'];
        
        // English time keywords
        $timeKeywords = ['morning', 'afternoon', 'evening', 'noon', 'night', 'am', 'pm', '12 pm', '10 am', '11 am', '2 pm', '3 pm', '4 pm', '5 pm', '6 pm', '9 am'];
        
        // Bengali date keywords
        $bnDateKeywords = ['আজকে', 'কাল', 'পরের সপ্তাহ', 'সোমবার', 'মঙ্গলবার', 'বুধবার', 'বৃহস্পতিবার', 'শুক্রবার', 'শনিবার', 'রবিবার'];
        
        // Bengali time keywords
        $bnTimeKeywords = ['সকাল', 'দুপুর', 'বিকেল', 'সন্ধ্যা', 'রাত', 'সকালে', 'দুপুরে', 'বিকেলে'];
        
        // Check for English date patterns
        foreach ($dateKeywords as $keyword) {
            if (str_contains($lowerMessage, $keyword)) {
                return true;
            }
        }
        
        // Check for English time patterns
        foreach ($timeKeywords as $keyword) {
            if (str_contains($lowerMessage, $keyword)) {
                return true;
            }
        }
        
        // Check for Bengali date patterns
        foreach ($bnDateKeywords as $keyword) {
            if (str_contains($lowerMessage, $keyword)) {
                return true;
            }
        }
        
        // Check for Bengali time patterns
        foreach ($bnTimeKeywords as $keyword) {
            if (str_contains($lowerMessage, $keyword)) {
                return true;
            }
        }
        
        // Check for date format (e.g., 15/03/2026, 03-15-2026)
        if (preg_match('/\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}/', $message)) {
            return true;
        }
        
        return false;
    }

    /**
     * Check if message is a doctor selection (number 1-10)
     */
    protected function isDoctorSelection(string $message): bool
    {
        // Check for simple number 1-10
        if (preg_match('/^(1|2|3|4|5|6|7|8|9|10)$/', trim($message))) {
            return true;
        }
        // Check for "number 1", "option 1", "1st", etc.
        if (preg_match('/^(number|option|no\.)?\s*(1|2|3|4|5|6|7|8|9|10)\s*(st|nd|rd|th)?$/i', trim($message))) {
            return true;
        }
        return false;
    }

    /**
     * Check if message contains contact info (name or phone)
     */
    protected function isContactInfoInput(string $message): bool
    {
        $lowerMessage = strtolower($message);
        
        // Check for phone number patterns (various formats)
        $phonePatterns = [
            '/\+?\d{1,3}[-.\s]?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}/',  // International format
            '/01[3-9]\d{8}/',  // Bangladesh mobile (11 digits starting with 01)
            '/\d{10,11}/',  // Generic 10-11 digit number
            '/\d{4}[-.\s]?\d{4}[-.\s]?\d{4}/',  // Grouped 12 digits
        ];
        
        foreach ($phonePatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }
        
        // Check for name patterns in English
        $enNamePatterns = [
            'my name is', 'i am', 'name is', 'this is', 'i\'m',
            'my name\'s', 'call me', 'name:', 'name -'
        ];
        
        foreach ($enNamePatterns as $pattern) {
            if (str_contains($lowerMessage, $pattern)) {
                return true;
            }
        }
        
        // Check for name patterns in Bengali
        $bnNamePatterns = [
            'আমার নাম', 'নাম', 'ফোন', 'মোবাইল', 'নম্বর', 
            'ফোন নম্বর', 'মোবাইল নম্বর', 'যোগাযোগ', 'হ্যাঁ',
            'আমি', 'নামটা', 'নামের', 'নামটি'
        ];
        
        foreach ($bnNamePatterns as $pattern) {
            if (str_contains($lowerMessage, $pattern)) {
                return true;
            }
        }
        
        // Also check if message contains Bengali text (might be a name)
        if (preg_match('/[\u0980-\u09FF]/u', $message)) {
            // If it contains Bengali characters and is short, likely a name or response
            return true;
        }
        
        return false;
    }

    /**
     * Extract doctor number from message
     */
    protected function extractDoctorNumber(string $message): ?int
    {
        $message = trim($message);
        
        // Direct number
        if (preg_match('/^(1|2|3|4|5|6|7|8|9|10)$/', $message)) {
            return (int) $message;
        }
        // Number with prefix
        if (preg_match('/(?:number|option|no\.)\s*(1|2|3|4|5|6|7|8|9|10)/i', $message, $matches)) {
            return (int) $matches[1];
        }
        // Ordinal numbers
        if (preg_match('/(1st|2nd|3rd|4th|5th|6th|7th|8th|9th|10th)/i', $message, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    /**
     * Extract doctor name from message and search in database
     */
    protected function extractDoctorName(string $message): ?string
    {
        $lowerMessage = strtolower($message);
        
        // Check for patterns like "Dr. Shahidullah", "doctor Shahidullah", "with Dr. Name"
        // Patterns to match doctor names in message
        $patterns = [
            '/(?:dr\.?\s+)([a-z]+(?:\s+[a-z]+)*)/i',
            '/(?:doctor\s+)([a-z]+(?:\s+[a-z]+)*)/i',
            '/(?:book\s+.*\s+with\s+)([a-z]+(?:\s+[a-z]+)*)/i',
            '/(?:appointment\s+with\s+)([a-z]+(?:\s+[a-z]+)*)/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                $doctorName = trim($matches[1]);
                // Search for doctor in database
                $doctor = Doctor::query()
                    ->with(['user', 'specialization'])
                    ->whereHas('user', function ($q) use ($doctorName) {
                        $q->where('name', 'LIKE', '%' . $doctorName . '%');
                    })
                    ->first();
                
                if ($doctor && $doctor->user) {
                    Log::debug('Found doctor by name: ' . $doctor->user->name);
                    return $doctor->user->name;
                }
            }
        }
        
        // Also try direct search if message contains any doctor name
        // Get all doctors and check if their names are in the message
        $doctors = Doctor::query()
            ->with(['user'])
            ->limit(20)
            ->get();
        
        foreach ($doctors as $doctor) {
            if ($doctor->user && str_contains($lowerMessage, strtolower($doctor->user->name))) {
                Log::debug('Found doctor by direct name match: ' . $doctor->user->name);
                return $doctor->user->name;
            }
        }
        
        return null;
    }

    /**
     * Match user message against training data
     */
    protected function matchTrainingData(string $message): string
    {
        $lowerMessage = strtolower($message);
        
        // Check exact matches first
        if (isset($this->trainingData[$lowerMessage])) {
            return $this->trainingData[$lowerMessage];
        }

        // Check partial matches
        foreach ($this->trainingData as $key => $intent) {
            if (str_contains($lowerMessage, $key)) {
                return $intent;
            }
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

        Log::debug('extractEntitiesFromMessage called with: ' . $message);
        
        try {
            // Extract specialization
            foreach ($this->specializations as $keyword => $specialization) {
                if (str_contains($lowerMessage, $keyword)) {
                    $entities['specialization'] = $specialization;
                    break;
                }
            }

            // Extract date patterns
            Log::debug('Checking for date patterns in message: ' . $message);
        
        // Check for numeric day selection (1-7 for days, 8=tomorrow, 9=today)
        // Only apply numeric mapping if message is NOT a simple number (doctor number)
        $isSimpleNumber = preg_match('/^(1|2|3|4|5|6|7|8|9|10)$/', trim($message));
        
        if (!$isSimpleNumber) {
            $dayNumberMap = [
                '1' => 'monday', '2' => 'tuesday', '3' => 'wednesday', 
                '4' => 'thursday', '5' => 'friday', '6' => 'saturday', '7' => 'sunday',
                '8' => 'tomorrow', '9' => 'today'
            ];
            if (is_numeric($message) && isset($dayNumberMap[$message])) {
                Log::debug('Numeric day selected: ' . $message . ' => ' . $dayNumberMap[$message]);
                $entities['date'] = $this->parseRelativeDate($dayNumberMap[$message]);
            } elseif (preg_match('/(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/', $message, $matches)) {
                Log::debug('Date pattern matched: ' . $matches[1]);
                $entities['date'] = $this->parseDate($matches[1]);
            } elseif (preg_match('/(today|tomorrow|next week|monday|tuesday|wednesday|thursday|friday|saturday|sunday)/i', $message, $matches)) {
                Log::debug('Relative date pattern matched: ' . $matches[1]);
                $entities['date'] = $this->parseRelativeDate($matches[1]);
                Log::debug('Parsed date: ' . ($entities['date'] ?? 'null'));
            }
        } else {
            // Simple number like 1-10 - don't interpret as date, it might be doctor number
            if (preg_match('/(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/', $message, $matches)) {
                Log::debug('Date pattern matched: ' . $matches[1]);
                $entities['date'] = $this->parseDate($matches[1]);
            } elseif (preg_match('/(today|tomorrow|next week|monday|tuesday|wednesday|thursday|friday|saturday|sunday)/i', $message, $matches)) {
                Log::debug('Relative date pattern matched: ' . $matches[1]);
                $entities['date'] = $this->parseRelativeDate($matches[1]);
                Log::debug('Parsed date: ' . ($entities['date'] ?? 'null'));
            }
        }
        
        if (empty($entities['date'])) {
            Log::debug('No date pattern matched for message: ' . $message);
            // Check for Bengali dates
            $bnDates = [
                'আজকে' => 'today',
                'কাল' => 'tomorrow',
                'পরের সপ্তাহ' => 'next week',
                'সোমবার' => 'monday',
                'মঙ্গলবার' => 'tuesday',
                'বুধবার' => 'wednesday',
                'বৃহস্পতিবার' => 'thursday',
                'শুক্রবার' => 'friday',
                'শনিবার' => 'saturday',
                'রবিবার' => 'sunday',
            ];
            foreach ($bnDates as $bn => $en) {
                if (str_contains($lowerMessage, $bn)) {
                    $entities['date'] = $this->parseRelativeDate($en);
                    break;
                }
            }
        }

        // Extract time preference - English - also check for numeric slot selection
        if (is_numeric($message) && intval($message) >= 1 && intval($message) <= 20) {
            // User selected a time slot number
            $entities['time_slot_number'] = intval($message);
        } elseif ($this->matchesAny($lowerMessage, ['morning', 'am', '10 am', '11 am', '9 am'])) {
            $entities['time_preference'] = 'morning';
        } elseif ($this->matchesAny($lowerMessage, ['afternoon', 'pm', '2 pm', '3 pm', '4 pm'])) {
            $entities['time_preference'] = 'afternoon';
        } elseif ($this->matchesAny($lowerMessage, ['evening', '6 pm', '7 pm', '8 pm'])) {
            $entities['time_preference'] = 'evening';
        } else {
            // Check for Bengali times
            if (str_contains($lowerMessage, 'সকাল') || str_contains($lowerMessage, 'সকালে')) {
                $entities['time_preference'] = 'morning';
            } elseif (str_contains($lowerMessage, 'দুপুর') || str_contains($lowerMessage, 'দুপুরে')) {
                $entities['time_preference'] = 'afternoon';
            } elseif (str_contains($lowerMessage, 'বিকেল') || str_contains($lowerMessage, 'বিকেলে')) {
                $entities['time_preference'] = 'afternoon';
            } elseif (str_contains($lowerMessage, 'সন্ধ্যা') || str_contains($lowerMessage, 'সন্ধ্যায়')) {
                $entities['time_preference'] = 'evening';
            } elseif (str_contains($lowerMessage, 'রাত') || str_contains($lowerMessage, 'রাতে')) {
                $entities['time_preference'] = 'evening';
            }
        }

        // Extract phone number - enhanced patterns
        $phonePatterns = [
            '/\+?\d{1,3}[-.\s]?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}/',  // International format
            '/01[3-9]\d{8}/',  // Bangladesh mobile (11 digits starting with 01)
            '/\d{10,11}/',  // Generic 10-11 digit number
        ];
        foreach ($phonePatterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                $entities['phone'] = $matches[0];
                break;
            }
        }

        // Extract email
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $message, $matches)) {
            $entities['email'] = $matches[0];
        }

        // Extract appointment number
        if (preg_match('/(APT|APP)[-]?\d{6}/i', $message, $matches)) {
            $entities['appointment_number'] = strtoupper($matches[0]);
        }

        // Extract patient name - English patterns
        if (preg_match('/(?:my name is|i am|i\'m)\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)/i', $message, $matches)) {
            $entities['patient_name'] = $matches[1];
        }
        
        // Extract patient name - Bengali patterns (Unicode range for Bengali)
        // Match patterns like "আমার নাম রাহিম", "নাম মোহাম্মদ", etc.
        if (preg_match('/(?:আমার\s*নাম|নাম\s*[ঃ:]?\s*)([\u0980-\u09FF]+)/u', $message, $matches)) {
            $entities['patient_name'] = $matches[1];
        }
        // Also check for just Bengali text names after common introducing words
        if (empty($entities['patient_name']) && preg_match('/(?:এটি|এই|আমি|হ্যাঁ)\s+([\u0980-\u09FF]{2,})/u', $message, $matches)) {
            // Filter out common non-name words
            $notName = ['নাম', 'ফোন', 'মোবাইল', 'নম্বর', 'হ্যাঁ', 'না'];
            if (!in_array($matches[1], $notName)) {
                $entities['patient_name'] = $matches[1];
            }
        }
        // Additional Bengali patterns - handle cases like "আমি রাহিম" or "রাহিম নাম"
        if (empty($entities['patient_name'])) {
            $bnNamePatterns = [
                '/^([\u0980-\u09FF]{2,})$/u',  // Just a name
                '/\s+([\u0980-\u09FF]{2,})$/u',  // Name at end
            ];
            foreach ($bnNamePatterns as $pattern) {
                if (preg_match($pattern, trim($message), $matches)) {
                    $notName = ['নাম', 'ফোন', 'মোবাইল', 'নম্বর', 'হ্যাঁ', 'না', 'সত্য', 'ঠিক', 'আমি'];
                    if (!in_array($matches[1], $notName) && strlen($matches[1]) > 2) {
                        $entities['patient_name'] = $matches[1];
                        break;
                    }
                }
            }
        }
        
        } catch (Exception $e) {
            Log::warning('extractEntitiesFromMessage error (preserving data so far): ' . $e->getMessage());
        }

        Log::debug('extractEntitiesFromMessage returning: ' . json_encode($entities));
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
        
        Log::debug('buildResponse called with intent: ' . $intent . ', language: ' . $language);
        
        return match ($intent) {
            self::INTENT_GREET => $this->getGreeting($language),
            self::INTENT_LIST_DOCTORS => $this->buildListDoctorsResponse($extractedData, $language),
            self::INTENT_EMERGENCY => $this->getEmergencyResponse($language),
            self::INTENT_BOOK_APPOINTMENT => $this->buildBookingResponse($extractedData, $language, $context),
            self::INTENT_CANCEL_APPOINTMENT => $this->buildCancelResponse($extractedData, $language),
            self::INTENT_RESCHEDULE_APPOINTMENT => $this->buildRescheduleResponse($extractedData, $language),
            self::INTENT_CHECK_AVAILABILITY => $this->buildAvailabilityResponse($extractedData, $language),
            self::INTENT_THANKS => $this->buildThanksResponse($language),
            self::INTENT_GOODBYE => $this->buildGoodbyeResponse($language),
            self::INTENT_HELP => $this->buildHelpResponse($language),
            self::INTENT_DOCTOR_INFO => $this->buildDoctorInfoResponse($extractedData, $language),
            self::INTENT_CLINIC_INFO => $this->buildClinicInfoResponse($extractedData, $language),
            self::INTENT_SYMPTOMS => $this->buildSymptomsResponse($extractedData, $language),
            self::INTENT_APPOINTMENT_INFO => $this->buildAppointmentInfoResponse($extractedData, $language),
            default => $this->buildGeneralResponse($extractedData, $language),
        };
    }

    /**
     * Build booking response
     */
    protected function buildBookingResponse(array $data, string $language, array $context = []): string
    {
        try {
        // Get data from both extracted data and context
        Log::debug('buildBookingResponse called with data: ' . json_encode($data) . ', context extracted_data: ' . json_encode($context['extracted_data'] ?? []));
        
        // Create a fully merged data array that includes both current extracted data and context data
        $contextData = $context['extracted_data'] ?? [];
        $mergedData = array_merge($contextData, $data);
        Log::debug('Fully merged data: ' . json_encode($mergedData));
        Log::debug('Context extracted_data: ' . json_encode($contextData));
        Log::debug('Current data: ' . json_encode($data));
        
        $specialization = $mergedData['specialization'] ?? null;
        $date = $mergedData['date'] ?? null;
        $time = $mergedData['time_preference'] ?? null;
        $doctorNumber = $mergedData['doctor_number'] ?? null;
        $selectedDoctorId = $mergedData['selected_doctor_id'] ?? null;
        $doctorName = $mergedData['doctor_name'] ?? null;
        
        Log::debug('Using merged values - doctorNumber: ' . ($doctorNumber ?? 'null') . ', date: ' . ($date ?? 'null') . ', time: ' . ($time ?? 'null'));

        Log::debug('buildBookingResponse - doctorNumber: ' . ($doctorNumber ?? 'null') . ', date: ' . ($date ?? 'null') . ', time: ' . ($time ?? 'null'));

        // Case 1: Doctor is selected (by number or name), need date/time
        if (($doctorNumber || $selectedDoctorId || $doctorName) && (!$date || !$time)) {
            Log::debug('Entering Case 1: Doctor selected, need date/time');
            
            // Get doctor from database if we have doctor name (direct search)
            if ($doctorName && !$doctorNumber && !$selectedDoctorId) {
                $doctor = Doctor::query()
                    ->with(['specialization', 'user', 'schedules'])
                    ->whereHas('user', function ($q) use ($doctorName) {
                        $q->where('name', 'LIKE', '%' . $doctorName . '%');
                    })
                    ->first();
                
                if ($doctor && $doctor->user) {
                    $doctorName = $doctor->user->name;
                    $specialization = $doctor->specialization ? $doctor->specialization->name : $specialization;
                    Log::debug('Found doctor by name: ' . $doctorName);
                }
            }
            
            // Get doctor name if we have doctor number
            if ($doctorNumber && !$doctorName) {
                // Build query with same filters as when showing doctor list
                $doctorQuery = Doctor::query()
                    ->with(['specialization', 'user']);
                
                // If we have specialization, filter by it (same as when showing list)
                if ($specialization) {
                    $doctorQuery->whereHas('specialization', function ($q) use ($specialization) {
                        $q->where('name', 'LIKE', '%' . $specialization . '%');
                    });
                }
                
                $doctor = $doctorQuery
                    ->skip($doctorNumber - 1)
                    ->first();
                
                if ($doctor) {
                    $doctorName = $doctor->user->name;
                    $specialization = $doctor->specialization ? $doctor->specialization->name : $specialization;
                } else {
                    // Fallback: get any doctor
                    $doctor = Doctor::query()
                        ->with(['specialization', 'user'])
                        ->skip($doctorNumber - 1)
                        ->first();
                    if ($doctor) {
                        $doctorName = $doctor->user->name;
                    }
                }
            }
            
            // Get doctor object for available slots
            $doctor = null;
            if ($doctorNumber) {
                $doctorQuery = Doctor::query()
                    ->with(['specialization', 'user', 'schedules'])
                    ->orderBy('rating', 'desc')
                    ->orderBy('total_reviews', 'desc');
                if ($specialization) {
                    $doctorQuery->whereHas('specialization', function ($q) use ($specialization) {
                        $q->where('name', 'LIKE', '%' . $specialization . '%');
                    });
                }
                $doctor = $doctorQuery->skip($doctorNumber - 1)->first();
            }
            
            // Check if the message contains a date/time
            $hasDate = !empty($date);
            $hasTime = !empty($time);
            $timeSlotNumber = $mergedData['time_slot_number'] ?? null;
            
            Log::debug('Case 1 check: hasDate=' . ($hasDate ? 'true' : 'false') . ', hasTime=' . ($hasTime ? 'true' : 'false') . ', timeSlotNumber=' . ($timeSlotNumber ?? 'null'));
            
            // If user provided time slot number, use it
            if ($hasDate && $timeSlotNumber && $doctor) {
                $slots = $doctor->getAvailableTimeSlotsForDate($date);
                $slotIndex = $timeSlotNumber - 1;
                
                if (isset($slots[$slotIndex])) {
                    $selectedSlot = $slots[$slotIndex];
                    $selectedTime = date('h:i A', $selectedSlot['start_time']);
                    
                    // Now ask for patient info
                    return match($language) {
                        'bn' => "চমৎকার! আমি আপনার জন্য Dr. {$doctorName}-এর সাথে {$date} তারিখে {$selectedTime} সময়ে অ্যাপয়েন্টমেন্ট বুক করছি।\n\nআপনার নাম এবং ফোন নম্বর কী?",
                        'hi' => "बढ़िया! मैं आपके लिए Dr. {$doctorName} के साथ {$date} को {$selectedTime} अपॉइंटमेंट बुक कर रहा हूं।\n\nआपका नाम और फोन नंबर क्या है?",
                        default => "Great! I'm booking an appointment with Dr. {$doctorName} on {$date} at {$selectedTime}.\n\nWhat is your name and phone number?",
                    };
                }
            }
            
            if ($hasDate && !$hasTime && $doctor) {
                // User provided date, show available time slots
                $slots = $doctor->getAvailableTimeSlotsForDate($date);
                
                if (!empty($slots)) {
                    $slotList = "";
                    foreach ($slots as $index => $slot) {
                        $slotList .= "\n" . ($index + 1) . ". " . $slot['formatted_time'];
                    }
                    
                    return match($language) {
                        'bn' => "তারিখ {$date} তে Dr. {$doctorName}-এর উপলব্ধ সময়:\n{$slotList}\n\nসময়ের নম্বর বলুন (1-" . count($slots) . ")।",
                        'hi' => "Dr. {$doctorName} की {$date} को उपलब्ध समय:\n{$slotList}\n\nसमय का नंबर बताएं (1-" . count($slots) . ")।",
                        default => "Available times for Dr. {$doctorName} on {$date}:\n{$slotList}\n\nPlease specify the time number (1-" . count($slots) . ").",
                    };
                } else {
                    return match($language) {
                        'bn' => "দুঃখিত, {$date} তারিখে Dr. {$doctorName}-এর কোনো স্লট উপলব্ধ নেই। অন্য তারিখ বলুন।",
                        'hi' => "माफ करें, {$date} को Dr. {$doctorName} के लिए कोई स्लॉट उपलब्ध नहीं है। कृपया कोई और तारीख बताएं।",
                        default => "Sorry, no slots available for Dr. {$doctorName} on {$date}. Please choose another date.",
                    };
                }
            }
            
            if ($hasDate && !$hasTime) {
                // User provided date, ask for time
                Log::debug('Asking for time, date=' . $date);
                return match($language) {
                    'bn' => "তারিখ {$date} নেওয়া হয়েছে। এখন কোন সময় আপনার জন্য সুবিধাজনক? (সকাল, দুপুর, বিকেল বা নির্দিষ্ট সময়)",
                    'hi' => "तारीख {$date} ली गई। अब कौन सा समय आपके लिए सुविधाजनक है? (सुबह, दोपहर, शाम)",
                    default => "Date {$date} received. What time works best for you? (morning, afternoon, evening or specific time)",
                };
            }
            
            // Show available days and ask for date selection
            $availableDaysInfo = "";
            $doctorForSchedule = null;
            
            // Get doctor from database using the same query as API /api/doctors/available
            $doctorForSchedule = null;
            if ($doctorNumber) {
                $doctorQuery = Doctor::query()
                    ->with(['specialization', 'user', 'schedules'])
                    ->orderBy('rating', 'desc')
                    ->orderBy('total_reviews', 'desc');
                
                if ($specialization) {
                    $doctorQuery->whereHas('specialization', function ($q) use ($specialization) {
                        $q->where('name', 'LIKE', '%' . $specialization . '%');
                    });
                }
                $doctorForSchedule = $doctorQuery->skip($doctorNumber - 1)->first();
            }
            
            // Get available days from either Doctor.available_days or DoctorSchedule (same as API)
            $availableDays = [];
            if ($doctorForSchedule) {
                // First try from available_days field (same as API formatDoctor)
                if (!empty($doctorForSchedule->available_days)) {
                    $availableDays = array_map('strtolower', $doctorForSchedule->available_days);
                } elseif ($doctorForSchedule->schedules) {
                    // Fallback to schedules
                    foreach ($doctorForSchedule->schedules as $schedule) {
                        if ($schedule->is_active) {
                            $availableDays[] = strtolower($schedule->day_of_week);
                        }
                    }
                }
            }
            
            if (!empty($availableDays)) {
                $dayNamesEn = ['monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday', 'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday'];
                $dayNamesBn = ['monday' => 'সোমবার', 'tuesday' => 'মঙ্গলবার', 'wednesday' => 'বুধবার', 'thursday' => 'বৃহস্পতিবার', 'friday' => 'শুক্রবার', 'saturday' => 'শনিবার', 'sunday' => 'রবিবার'];
                $dayNamesHi = ['monday' => 'सोमवार', 'tuesday' => 'मंगलवार', 'wednesday' => 'बुधवार', 'thursday' => 'गुरुवार', 'friday' => 'शुक्रवार', 'saturday' => 'शनिवार', 'sunday' => 'रविवार'];
                
                $dayIndex = 1;
                foreach ($availableDays as $day) {
                    $dayName = $dayNamesEn[$day] ?? ucfirst($day);
                    $availableDaysInfo .= "\n" . $dayIndex . ". " . $dayName;
                    $dayIndex++;
                }
            }
            
            return match($language) {
                'bn' => "আপনি Dr. " . ($doctorName ?? "এই ডাক্তার") . "-কে বেছে নিয়েছেন।" . (!empty($availableDaysInfo) ? "\n\nউপলব্ধ দিন:" . $availableDaysInfo : "") . "\n\nকোন তারিখ আপনার জন্য সুবিধাজনক? (যেমন: আজকে, কাল, সোমবার অথবা উপরের দিনের নম্বর)",
                'hi' => "आपने Dr. " . ($doctorName ?? "इस डॉक्टर") . " को चुना है।" . (!empty($availableDaysInfo) ? "\n\nउपलब्ध दिन:" . $availableDaysInfo : "") . "\n\nकौन सी तारीख आपके लिए सुविधाजनक है? (जैसे: आज, कल, सोमवार या ऊपर से दिन का नंबर)",
                default => "You've selected Dr. " . ($doctorName ?? "") . "." . (!empty($availableDaysInfo) ? "\n\nAvailable days:" . $availableDaysInfo : "") . "\n\nWhat date works best for you? (e.g., today, tomorrow, Monday or the day number above)",
            };
        }

        // Case 2: Have doctor and date/time, need patient info
        if (($doctorNumber || $selectedDoctorId || $doctorName) && $date && $time) {
            // Get doctor name if we have doctor number
            if ($doctorNumber && !$doctorName) {
                // Build query with same filters as when showing doctor list
                $doctorQuery = Doctor::query()
                    ->with(['specialization', 'user']);
                
                // If we have specialization, filter by it
                if ($specialization) {
                    $doctorQuery->whereHas('specialization', function ($q) use ($specialization) {
                        $q->where('name', 'LIKE', '%' . $specialization . '%');
                    });
                }
                
                $doctor = $doctorQuery
                    ->orderBy('rating', 'desc')
                    ->orderBy('experience_years', 'desc')
                    ->skip($doctorNumber - 1)
                    ->first();
                
                if ($doctor) {
                    $doctorName = $doctor->user->name;
                } else {
                    // Fallback: get any doctor
                    $doctor = Doctor::query()
                        ->with(['specialization', 'user'])
                        ->orderBy('rating', 'desc')
                        ->orderBy('experience_years', 'desc')
                        ->skip($doctorNumber - 1)
                        ->first();
                    if ($doctor) {
                        $doctorName = $doctor->user->name;
                    }
                }
            }
            
            // Check if we also have patient name and phone (from context or data)
            $patientName = $data['patient_name'] ?? null;
            $phone = $data['phone'] ?? null;
            
            // Also check context for patient info
            if (!$patientName && !empty($contextData['patient_name'])) {
                $patientName = $contextData['patient_name'];
            }
            if (!$phone && !empty($contextData['phone'])) {
                $phone = $contextData['phone'];
            }
            
            // If we have all info, confirm the booking
            if ($patientName && $phone) {
                return match($language) {
                    'bn' => "✅ *অ্যাপয়েন্টমেন্ট নিশ্চিত করা হয়েছে!*\n\n" .
                        "👨‍⚕️ ডাক্তার: {$doctorName}\n" .
                        "📅 তারিখ: {$date}\n" .
                        "⏰ সময়: {$time}\n" .
                        "👤 রোগী: {$patientName}\n" .
                        "📱 ফোন: {$phone}\n\n" .
                        "ধন্যবাদ! আমরা আপনার সাথে যোগাযোগ করব।",
                    'hi' => "✅ *अपॉइंटमेंट पुष्टि की गई!*\n\n" .
                        "👨‍⚕️ डॉक्टर: {$doctorName}\n" .
                        "📅 तारीख: {$date}\n" .
                        "⏰ समय: {$time}\n" .
                        "👤 रोगी: {$patientName}\n" .
                        "📱 फोन: {$phone}\n\n" .
                        "धन्यवाद! हम आपसे संपर्क करेंगे।",
                    default => "✅ *Appointment Confirmed!*\n\n" .
                        "👨‍⚕️ Doctor: {$doctorName}\n" .
                        "📅 Date: {$date}\n" .
                        "⏰ Time: {$time}\n" .
                        "👤 Patient: {$patientName}\n" .
                        "📱 Phone: {$phone}\n\n" .
                        "Thank you! We will contact you shortly.",
                };
            }
            
            // Otherwise ask for patient info
            return match($language) {
                'bn' => "চমৎকার! আমি আপনার জন্য " . ($doctorName ?? "ডাক্তার") . "-এর সাথে {$date} তারিখে {$time} সময়ে অ্যাপয়েন্টমেন্ট বুক করছি।\n\nআপনার নাম এবং ফোন নম্বর কী?",
                'hi' => "बढ़िया! मैं आपके लिए " . ($doctorName ?? "डॉक्टर") . " के साथ {$date} को {$time} अपॉइंटमेंट बुक कर रहा हूं।\n\nआपका नाम और फोन नंबर क्या है?",
                default => "Great! I'm booking an appointment with Dr. " . ($doctorName ?? "") . " on {$date} at {$time}.\n\nWhat is your name and phone number?",
            };
        }

        // Case 3: Have specialization, need doctor selection
        if ($specialization && !$doctorNumber) {
            $doctors = Doctor::query()
                ->with(['specialization', 'user'])
                ->whereHas('specialization', function ($q) use ($specialization) {
                    $q->where('name', 'LIKE', '%' . $specialization . '%');
                })
                ->orderBy('rating', 'desc')
                ->orderBy('experience_years', 'desc')
                ->limit(5)
                ->get();
            
            if ($doctors->isNotEmpty()) {
                $doctorList = "";
                foreach ($doctors as $index => $doctor) {
                    $spec = $doctor->specialization ? $doctor->specialization->name : 'General';
                    $doctorList .= "\n" . ($index + 1) . ". Dr. " . $doctor->user->name . " - {$spec}";
                    if ($doctor->consultation_fee > 0) {
                        $doctorList .= " (Taka " . number_format($doctor->consultation_fee) . ")";
                    }
                    if ($doctor->experience_years) {
                        $doctorList .= " [" . $doctor->experience_years . " years experience]";
                    }
                }
                
                return match($language) {
                    'bn' => "নিচের {$specialization} ডাক্তারদের মধ্যে আপনার পছন্দের ডাক্তার নির্বাচন করুন:{$doctorList}\n\nডাক্তারের নম্বর বলুন (১-৫)।",
                    'hi' => "नीचे {$specialization} डॉक्टरों में से अपना डॉक्टर चुनें:{$doctorList}\n\nडॉक्टर का नंबर बताएं (1-5)।",
                    default => "Choose a {$specialization} doctor from the list below:{$doctorList}\n\nPlease specify the doctor's number (1-5).",
                };
            }
        }

        // Case 4: No specialization, show all doctors
        $doctors = Doctor::query()
            ->with(['specialization', 'user'])
            ->orderBy('rating', 'desc')
            ->orderBy('experience_years', 'desc')
            ->limit(5)
            ->get();
        
        if ($doctors->isNotEmpty()) {
            $doctorList = "";
            foreach ($doctors as $index => $doctor) {
                $spec = $doctor->specialization ? $doctor->specialization->name : 'General';
                $doctorList .= "\n" . ($index + 1) . ". Dr. " . $doctor->user->name . " - {$spec}";
                if ($doctor->consultation_fee > 0) {
                    $doctorList .= " (Taka " . number_format($doctor->consultation_fee) . ")";
                }
                if ($doctor->experience_years) {
                    $doctorList .= " [" . $doctor->experience_years . " years experience]";
                }
            }
            
            return match($language) {
                'bn' => "নিচের ডাক্তারদের মধ্যে আপনার পছন্দের ডাক্তার নির্বাচন করুন:{$doctorList}\n\nডাক্তারের নম্বর বলুন (১-৫)।",
                'hi' => "नीचे दिए गए डॉक्टरों में से अपना डॉक्टर चुनें:{$doctorList}\n\nडॉक्टर का नंबर बताएं (1-5)।",
                default => "Choose a doctor from the list below:{$doctorList}\n\nPlease specify the doctor's number (1-5).",
            };
        }

        return match($language) {
            'bn' => "আমি আপনাকে ডাক্তারের অ্যাপয়েন্টমেন্ট বুক করতে সাহায্য করতে পারি। আপনি কোন ধরনের ডাক্তার দেখাতে চান? (যেমন: হৃদরোগ বিশেষজ্ঞ, ত্বকের ডাক্তার, চোখের ডাক্তার)",
            'hi' => "मैं आपकी डॉक्टर अपॉइंटमेंट बुक करने में मदद कर सकता हूं। आप किस तरह के डॉक्टर से मिलना चाहते हैं? (जैसे: हृदय विशेषज्ञ, त्वचा विशेषज्ञ, आंखों के डॉक्टर)",
            default => "I can help you book a doctor appointment. What type of doctor would you like to see? (e.g., cardiologist, dermatologist, eye doctor)",
        };
        } catch (Exception $e) {
            Log::error('buildBookingResponse error: ' . $e->getMessage());
            return match($language) {
                'bn' => "আমি আপনাকে ডাক্তারের অ্যাপয়েন্টমেন্ট বুক করতে সাহায্য করতে পারি। আপনি কোন ধরনের ডাক্তার দেখাতে চান? (যেমন: হৃদরোগ বিশেষজ্ঞ, ত্বকের ডাক্তার, চোখের ডাক্তার)",
                'hi' => "मैं आपकी डॉक्टर अपॉइंटमेंट बुक करने में मदद कर सकता हूं। आप किस तरह के डॉक्टर से मिलना चाहते हैं? (जैसे: हृदय विशेषज्ञ, त्वचा विशेषज्ञ, आंखों के डॉक्टर)",
                default => "I can help you book a doctor appointment. What type of doctor would you like to see? (e.g., cardiologist, dermatologist, eye doctor)",
            };
        }
    }

    /**
     * Build list doctors response
     */
    protected function buildListDoctorsResponse(array $data, string $language): string
    {
        try {
            $specialization = $data['specialization'] ?? null;
            
            // Fetch all doctors from database (not just available ones)
            $doctors = Doctor::query()
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
            $doctorList .= "\n👨‍⚕️ " . ($index + 1) . ". Dr. " . $doctor->user->name;
            if ($doctor->specialization) {
                $doctorList .= "\n   📋 " . $doctor->specialization->name;
            }
            if ($doctor->qualification) {
                $doctorList .= "\n   🎓 " . $doctor->qualification;
            }
            if ($doctor->experience_years) {
                $doctorList .= "\n   ⏱️ " . $doctor->experience_years . " years experience";
            }
            if ($doctor->hospital_clinic) {
                $doctorList .= "\n   🏥 " . $doctor->hospital_clinic;
            }
            if ($doctor->consultation_fee > 0) {
                $doctorList .= "\n   💰 Taka " . number_format($doctor->consultation_fee);
            }
            if ($doctor->rating > 0) {
                $doctorList .= "\n   ⭐ " . number_format($doctor->rating, 1) . "/5";
            }
            $doctorList .= "\n";
        }
        
        return match($language) {
            'bn' => "নিচে আমাদের ডাক্তারদের তালিকা:{$doctorList}\n\nঅ্যাপয়েন্টমেন্ট বুক করতে ডাক্তারের নম্বর বলুন (১-৫)। উদাহরণ: ১ লিখুন।",
            'hi' => "यहां हमारे उपलब्ध डॉक्टरों की सूची है:{$doctorList}\n\nअपॉइंटमेंट बुक करने के लिए डॉक्टर का नंबर बताएं (1-5)। उदाहरण: 1 लिखें।",
            default => "Here are our available doctors:{$doctorList}\n\nTo book an appointment, please specify the doctor's number (1-5). Example: Type 1",
        };
        } catch (Exception $e) {
            Log::error('buildListDoctorsResponse error: ' . $e->getMessage());
            return match($language) {
                'bn' => "ডাক্তারদের তালিকা দেখতে পারছি না। অনুগ্রহ করে পরে চেষ্টা করুন।",
                'hi' => "डॉक्टरों की सूची नहीं देख पा रहे हैं। कृपया बाद में पुनः प्रयास करें।",
                default => "Sorry, I couldn't retrieve the doctor list. Please try again later.",
            };
        }
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
        // Get all doctors
        $doctors = Doctor::query()
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
            'hi' => "মैं आपका मेडिकल अपॉइंटमेंट असिस्टेंट हूं।{$doctorList}\n\nमैं आपकी अपॉइंटमेंट बुक, रद्द या पुनर्निर्धारित करने में मदद कर सकता हूं।\n\nआप क्या चाहते हैं?",
            default => "I'm your Medical Appointment Assistant.{$doctorList}\n\nI can help you book, cancel, or reschedule appointments.\n\nWhat would you like to do?",
        };
    }

    /**
     * Build doctor info response
     */
    protected function buildDoctorInfoResponse(array $data, string $language): string
    {
        $specialization = $data['specialization'] ?? null;
        
        $doctors = Doctor::query()
            ->with(['specialization'])
            ->when($specialization, function ($query) use ($specialization) {
                $query->whereHas('specialization', function ($q) use ($specialization) {
                    $q->where('name', 'LIKE', '%' . $specialization . '%');
                });
            })
            ->limit(5)
            ->get();

        if ($doctors->isEmpty()) {
            // If no specific doctor found, show all doctors
            $doctors = Doctor::query()
                ->with(['specialization'])
                ->limit(5)
                ->get();
        }
        
        if ($doctors->isNotEmpty()) {
            $doctorList = "";
            foreach ($doctors as $index => $doctor) {
                $doctorList .= "\n👨‍⚕️ **" . ($index + 1) . ". Dr. " . $doctor->user->name . "**";
                if ($doctor->specialization) {
                    $doctorList .= "\n   📋 Specialization: " . $doctor->specialization->name;
                }
                if ($doctor->qualification) {
                    $doctorList .= "\n   🎓 Qualification: " . $doctor->qualification;
                }
                if ($doctor->experience_years) {
                    $doctorList .= "\n   ⏱️ Experience: " . $doctor->experience_years . " years";
                }
                if ($doctor->hospital_clinic) {
                    $doctorList .= "\n   🏥 Hospital: " . $doctor->hospital_clinic;
                }
                if ($doctor->consultation_fee > 0) {
                    $doctorList .= "\n   💰 Fee: Taka " . number_format($doctor->consultation_fee);
                }
                if (!empty($doctor->languages)) {
                    $langs = is_array($doctor->languages) ? implode(', ', $doctor->languages) : $doctor->languages;
                    $doctorList .= "\n   🗣️ Languages: " . $langs;
                }
                if ($doctor->available_days) {
                    $days = is_array($doctor->available_days) ? implode(', ', $doctor->available_days) : $doctor->available_days;
                    $doctorList .= "\n   📅 Available: " . ucwords($days);
                }
                if ($doctor->start_time && $doctor->end_time) {
                    $doctorList .= " (" . date('h:i A', strtotime($doctor->start_time)) . " - " . date('h:i A', strtotime($doctor->end_time)) . ")";
                }
                $doctorList .= "\n";
            }
            
            return match($language) {
                'bn' => "ডাক্তার সম্পর্কে তথ্য:{$doctorList}\n\nঅ্যাপয়েন্টমেন্ট বুক করতে ডাক্তারের নম্বর বলুন (১-৫)।\nউদাহরণ: ১ লিখুন।",
                'hi' => "डॉक्टर की जानकारी:{$doctorList}\n\nअपॉइंटमेंट बुक करने के लिए डॉक्टर का नंबर बताएं (1-5)।\nउदाहरण: 1 लिखें।",
                default => "Doctor Information:{$doctorList}\n\nTo book an appointment, please specify the doctor's number (1-5).\nExample: Type 1",
            };
        }

        return match($language) {
            'bn' => "ডাক্তার সম্পর্কে আরও তথ্যের জন্য, অনুগ্রহ করে নির্দিষ্ট ডাক্তার বা বিভাগ জানান। উদাহরণ: হৃদরোগ বিশেষজ্ঞ বা কার্ডিওলজিস্ট লিখুন।",
            'hi' => "डॉक्टर के बारे में अधिक जानकारी के लिए, कृपया विशिष्ट डॉक्टर या विभाग बताएं। उदाहरण: हृदय विशेषज्ञ लिखें।",
            default => "For more doctor information, please specify the specific doctor or department. Example: Type cardiologist or heart specialist",
        };
    }

    /**
     * Build clinic info response
     */
    protected function buildClinicInfoResponse(array $data, string $language): string
    {
        return match($language) {
            'bn' => "🏥 **ক্লিনিক তথ্য:**\n\n" .
                "• **খোলার সময়:** সোম-শুক্র: সকাল ৯টা - সন্ধ্যা ৬টা\n" .
                "• **অবস্থান:** প্রধান সড়ক, শহরের কেন্দ্রে\n" .
                "• **পার্কিং:** বিনামূল্যে পার্কিং সুবিধা\n" .
                "• **জরুরি সেবা:** ২৪/৭ জরুরি বিভাগ\n" .
                "• **অনলাইন পরামর্শ:** হ্যাঁ, উপলব্ধ\n" .
                "• **স্বাস্থ্য বীমা:** প্রধান বীমা কোম্পানি গ্রহণযোগ্য\n" .
                "• **পরামর্শ ফি:** ৫০০-২০০০ টাকা (ডাক্তারের উপর নির্ভরশীল)\n" .
                "• **ল্যাবরেটরি:** সম্পূর্ণ ল্যাব সুবিধা\n" .
                "• **বাড়িতে সেবা:** হ্যাঁ, নির্দিষ্ট এলাকায় উপলব্ধ\n\n" .
                "আপনার কি কোনো প্রশ্ন আছে?",
            'hi' => "🏥 **क्लिनिक जानकारी:**\n\n" .
                "• **खुलने का समय:** सोम-शुक्र: सुबह 9 बजे - शाम 6 बजे\n" .
                "• **स्थान:** मुख्य सड़क, शहर के केंद्र में\n" .
                "• **पार्किंग:** मुफ्त पार्किंग सुविधा\n" .
                "• **आपातकालीन सेवा:** 24/7 आपातकालीन विभाग\n" .
                "• **ऑनलाइन परामर्श:** हां, उपलब्ध\n" .
                "• **स्वास्थ्य बीमा:** प्रमुख बीमा कंपनियां स्वीकार\n" .
                "• **परामर्श शुल्क:** 500-2000 रुपये (डॉक्टर पर निर्भर)\n" .
                "• **लैबोरेटरी:** पूर्ण लैब सुविधा\n" .
                "• **घर सेवा:** हां, विशिष्ट क्षेत्रों में उपलब्ध\n\n" .
                "आपके कोई सवाल हैं?",
            default => "🏥 **Clinic Information:**\n\n" .
                "• **Opening Hours:** Mon-Fri: 9 AM - 6 PM\n" .
                "• **Location:** Main Road, City Center\n" .
                "• **Parking:** Free parking available\n" .
                "• **Emergency Service:** 24/7 Emergency Department\n" .
                "• **Online Consultation:** Yes, available\n" .
                "• **Health Insurance:** Major insurance companies accepted\n" .
                "• **Consultation Fee:** Taka 500-2000 (depends on doctor)\n" .
                "• **Laboratory:** Full lab facility\n" .
                "• **Home Visit:** Yes, available in specific areas\n\n" .
                "Do you have any questions?",
        };
    }

    /**
     * Build symptoms response - recommend doctor based on symptoms
     */
    protected function buildSymptomsResponse(array $data, string $language): string
    {
        $symptoms = $data['symptoms'] ?? [];
        $specialization = $data['specialization'] ?? null;

        // Map symptoms to specialization
        $symptomToSpec = [
            'chest pain' => 'Cardiology',
            'heart' => 'Cardiology',
            'blood pressure' => 'Cardiology',
            'bp' => 'Cardiology',
            'fever' => 'General Medicine',
            'cough' => 'General Medicine',
            'cold' => 'General Medicine',
            'headache' => 'Neurology',
            'migraine' => 'Neurology',
            'skin' => 'Dermatology',
            'allergy' => 'Dermatology',
            'rash' => 'Dermatology',
            'stomach' => 'Gastroenterology',
            'digestive' => 'Gastroenterology',
            'child' => 'Pediatrics',
            'baby' => 'Pediatrics',
            'breathing' => 'Pulmonology',
            'asthma' => 'Pulmonology',
            'diabetes' => 'Endocrinology',
            'sugar' => 'Endocrinology',
            'back pain' => 'Orthopedics',
            'joint' => 'Orthopedics',
            'bone' => 'Orthopedics',
            'pregnancy' => 'Gynecology',
            'women' => 'Gynecology',
            'dizzy' => 'General Medicine',
            'weak' => 'General Medicine',
            // Bengali
            'বুকে ব্যথা' => 'Cardiology',
            'হৃদ' => 'Cardiology',
            'রক্তচাপ' => 'Cardiology',
            'জ্বর' => 'General Medicine',
            'কাশি' => 'General Medicine',
            'সর্দি' => 'General Medicine',
            'মাথাব্যথা' => 'Neurology',
            'মাইগ্রেইন' => 'Neurology',
            'ত্বক' => 'Dermatology',
            'অ্যালার্জি' => 'Dermatology',
            'পেট' => 'Gastroenterology',
            'শিশু' => 'Pediatrics',
            'বাচ্চা' => 'Pediatrics',
            'শ্বাস' => 'Pulmonology',
            'ডায়াবেটিস' => 'Endocrinology',
            'চিনি' => 'Endocrinology',
            'পিঠে ব্যথা' => 'Orthopedics',
            'গর্ভবতী' => 'Gynecology',
            'মাথা ঘুরা' => 'General Medicine',
            'দুর্বল' => 'General Medicine',
        ];

        $matchedSpec = $specialization;
        
        // Try to find matching specialization from symptoms
        if (!$matchedSpec && !empty($symptoms)) {
            foreach ($symptoms as $symptom) {
                $lowerSymptom = strtolower($symptom);
                foreach ($symptomToSpec as $key => $spec) {
                    if (str_contains($lowerSymptom, $key)) {
                        $matchedSpec = $spec;
                        break 2;
                    }
                }
            }
        }

        // If still no match, look in extracted data
        if (!$matchedSpec && isset($data['extracted_symptom'])) {
            foreach ($symptomToSpec as $key => $spec) {
                if (str_contains(strtolower($data['extracted_symptom']), $key)) {
                    $matchedSpec = $spec;
                    break;
                }
            }
        }

        if ($matchedSpec) {
            $doctors = Doctor::query()
                ->with(['specialization'])
                ->whereHas('specialization', function ($q) use ($matchedSpec) {
                    $q->where('name', 'LIKE', '%' . $matchedSpec . '%');
                })
                ->limit(3)
                ->get();

            if ($doctors->isNotEmpty()) {
                $doctorList = "";
                foreach ($doctors as $index => $doctor) {
                    $doctorList .= "\n" . ($index + 1) . ". Dr. " . $doctor->user->name;
                    if ($doctor->specialization) {
                        $doctorList .= " - " . $doctor->specialization->name;
                    }
                }
                
                return match($language) {
                    'bn' => "আপনার লক্ষণগুলির জন্য আমি {$matchedSpec} বিশেষজ্ঞ ডাক্তার দেখাচ্ছি:{$doctorList}\n\nঅ্যাপয়েন্টমেন্ট বুক করতে ডাক্তারের নম্বর বলুন।",
                    'hi' => "आपके लक्षणों के लिए मैं {$matchedSpec} विशेषज्ञ डॉक्टर दिखा रहा हूं:{$doctorList}\n\nअपॉइंटमेंट बुक करने के लिए डॉक्टर का नंबर बताएं।",
                    default => "Based on your symptoms, I found {$matchedSpec} specialists:{$doctorList}\n\nTo book an appointment, please specify the doctor's number.",
                };
            }
        }

        // Default response with general doctors
        return match($language) {
            'bn' => "আপনার লক্ষণগুলির জন্য, আমি একজন সাধারণ চিকিৎসক বা বিশেষজ্ঞের সাথে পরামর্শ করার পরামর্শ দিচ্ছি।\n\nনিচের ডাক্তারদের মধ্যে একজনকে বেছে নিন:\n" .
                $this->getDefaultDoctorList($language) . "\n\nঅ্যাপয়েন্টমেন্ট বুক করতে ডাক্তারের নম্বর বলুন।",
            'hi' => "आपके लक्षणों के लिए, मैं सामान्य चिकित्सक या विशेषज्ञ से परामर्श की सलाह दे रहा हूं।\n\nनीचे दिए गए डॉक्टरों में से एक चुनें:\n" .
                $this->getDefaultDoctorList($language) . "\n\nअपॉइंटमेंट बुक करने के लिए डॉक्टर का नंबर बताएं।",
            default => "For your symptoms, I recommend consulting a general physician or specialist.\n\nPlease choose from our doctors:\n" .
                $this->getDefaultDoctorList($language) . "\n\nTo book an appointment, please specify the doctor's number.",
        };
    }

    /**
     * Get default doctor list for symptoms
     */
    protected function getDefaultDoctorList(string $language): string
    {
        $doctors = Doctor::query()
            ->with(['specialization'])
            ->limit(3)
            ->get();

        $doctorList = "";
        foreach ($doctors as $index => $doctor) {
            $doctorList .= "\n" . ($index + 1) . ". Dr. " . $doctor->user->name;
            if ($doctor->specialization) {
                $doctorList .= " - " . $doctor->specialization->name;
            }
        }
        return $doctorList;
    }

    /**
     * Build appointment info response
     */
    protected function buildAppointmentInfoResponse(array $data, string $language): string
    {
        return match($language) {
            'bn' => "📋 **অ্যাপয়েন্টমেন্ট সংক্রান্ত তথ্য:**\n\n" .
                "• **অ্যাপয়েন্টমেন্ট রিমাইন্ডার:** হ্যাঁ, আমরা SMS এবং ইমেইল করি\n" .
                "• **প্রয়োজনীয় কাগজপত্র:** ভোটার আইডি/পাসপোর্ট, পুরনো রিপোর্ট (যদি থাকে)\n" .
                "• **আগমনের সময়:** অ্যাপয়েন্টমেন্টের ১৫-৩০ মিনিট আগে\n" .
                "• **ডাক্তার পরিবর্তন:** হ্যাঁ, অ্যাপয়েন্টমেন্ট বাতিল করে নতুন বুক করতে হবে\n" .
                "• **একাধিক অ্যাপয়েন্টমেন্ট:** হ্যাঁ, একাধিক বুক করতে পারেন\n" .
                "• **আগের অ্যাপয়েন্টমেন্ট:** আপনার প্রোফাইল থেকে দেখতে পারেন\n" .
                "• **প্রেসক্রিপশন ডাউনলোড:** হ্যাঁ, অ্যাপয়েন্টমেন্টের পরে\n" .
                "• **কনফার্মেশন মেসেজ:** হ্যাঁ, SMS এবং ইমেইলে পাবেন\n" .
                "• **অনলাইন পেমেন্ট:** হ্যাঁ, বিকাশ/নগদ/কার্ড দিয়ে\n" .
                "• **যোগাযোগ:** ফোন: ০১৭১২৩৪৫৬৭৮, ইমেইল: info@clinic.com\n\n" .
                "আরো কোনো প্রশ্ন?",
            'hi' => "📋 **अपॉइंटमेंट जानकारी:**\n\n" .
                "• **अपॉइंटमेंट रिमाइंडर:** हां, हम SMS और ईमेल करते हैं\n" .
                "• **आवश्यक दस्तावेज:** वोटर आईडी/पासपोर्ट, पुरानी रिपोर्ट (यदि कोई हो)\n" .
                "• **आने का समय:** अपॉइंटमेंट से 15-30 मिनट पहले\n" .
                "• **डॉक्टर बदलना:** हां, अपॉइंटमेंट रद्द करके नई बुक करनी होगी\n" .
                "• **एकाधिक अपॉइंटमेंट:** हां, कई बुक कर सकते हैं\n" .
                "• **पिछली अपॉइंटमेंट:** अपनी प्रोफाइल से देख सकते हैं\n" .
                "• **प्रिसक्रिप्शन डाउनलोड:** हां, अपॉइंटमेंट के बाद\n" .
                "• **कन्फर्मेशन मैसेज:** हां, SMS और ईमेल में मिलेगा\n" .
                "• **ऑनलाइन भुगतान:** हां, बिकास/নগদ/कार्ड से\n" .
                "• **संपर्क:** फोन: 01712345678, ईमेल: info@clinic.com\n\n" .
                "और कोई सवाल?",
            default => "📋 **Appointment Information:**\n\n" .
                "• **Appointment Reminder:** Yes, we send SMS and email reminders\n" .
                "• **Documents Required:** Voter ID/Passport, previous reports (if any)\n" .
                "• **Arrival Time:** 15-30 minutes before appointment\n" .
                "• **Change Doctor:** Yes, cancel existing and book new\n" .
                "• **Multiple Appointments:** Yes, you can book multiple\n" .
                "• **Previous Appointments:** View from your profile\n" .
                "• **Prescription Download:** Yes, available after appointment\n" .
                "• **Confirmation Message:** Yes, via SMS and email\n" .
                "• **Online Payment:** Yes, via bKash/Nagad/Card\n" .
                "• **Contact:** Phone: 01712345678, Email: info@clinic.com\n\n" .
                "Any other questions?",
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
     * Call Google Gemini API
     */
    protected function callGeminiAPI(string $message, string $systemPrompt): array
    {
        $geminiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->model . ':generateContent?key=' . $this->apiKey;
        
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $systemPrompt . "\n\nUser message: " . $message]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.3,
                'maxOutputTokens' => 500,
                'topP' => 0.8,
                'topK' => 40,
            ]
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->timeout(60)->post($geminiUrl, $payload);

        if (!$response->successful()) {
            throw new Exception('Gemini API Error: ' . $response->body());
        }

        $result = $response->json();
        
        // Extract text from Gemini response
        $text = '';
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $text = $result['candidates'][0]['content']['parts'][0]['text'];
        }

        return ['generated_text' => $text];
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
        
        // Try to get doctors for greeting, but handle errors gracefully
        $doctorList = "";
        try {
            $doctors = Doctor::query()
                ->with(['specialization'])
                ->limit(3)
                ->get();
            
            if ($doctors->isNotEmpty()) {
                foreach ($doctors as $doctor) {
                    $doctorList .= "\n• Dr. " . $doctor->user->name;
                    if ($doctor->specialization) {
                        $doctorList .= " (" . $doctor->specialization->name . ")";
                    }
                }
            }
        } catch (Exception $e) {
            // If database query fails, just continue with empty doctor list
            Log::warning('Failed to fetch doctors for greeting: ' . $e->getMessage());
        }
        
        return match($language) {
            'bn' => "{$timeGreeting}! 🏥\n\nআমি আপনার মেডিকেল অ্যাপয়েন্টমেন্ট অ্যাসিস্ট্যান্ট।\n\nআমাদের ডাক্তার:{$doctorList}\n\nআমি আপনাকে অ্যাপয়েন্টমেন্ট বুক করতে, বাতিল করতে বা পুনর্নির্ধারণ করতে সাহায্য করতে পারি।\n\nআপনাকে কীভাবে সাহায্য করতে পারি?",
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

**SUPPORTED LANGUAGES:**
- English (en)
- Bengali/Bangla (bn) - Common phrases: অ্যাপয়েন্টমেন্ট, ডাক্তার, বুক, জ্বর, ব্যথা
- Hindi (hi)

**TRAINING DATA - Patient Query Patterns:**

1️⃣ APPOINTMENT BOOKING:
- "How can I book an appointment with a doctor?"
- "Is Dr. Ahmed available today?"
- "I want to see a cardiologist tomorrow."
- "Can I book an appointment for my mother?"
- "What time is the earliest appointment available?"
- "Can I book an appointment online?"
- Bengali: "আমি কিভাবে ডাক্তারের অ্যাপয়েন্টমেন্ট বুক করব"

2️⃣ DOCTOR INFORMATION:
- "Which doctor is best for heart problems?"
- "Do you have a female gynecologist?"
- "What are Dr. Rahman's visiting hours?"
- "How many years of experience does this doctor have?"
- "Which doctor treats diabetes?"
- Bengali: "হৃদরোগের জন্য কোন ডাক্তার ভালো"

3️⃣ CLINIC/HOSPITAL INFO:
- "What are your clinic opening hours?"
- "Are you open on weekends?"
- "Where is your clinic located?"
- "Do you have parking facilities?"
- "Is emergency service available?"
- Bengali: "আপনাদের ক্লিনিক কখন খোলে"

4️⃣ SYMPTOM BASED:
- "I have chest pain, which doctor should I see?"
- "I have fever and cough, what should I do?"
- "My child has a high fever, what should I do?"
- "I feel dizzy and weak, which doctor should I consult?"
- Bengali: "আমার বুকে ব্যথা কোন ডাক্তার দেখাব"

5️⃣ APPOINTMENT MANAGEMENT:
- "Can you remind me about my appointment?"
- "What documents should I bring to the appointment?"
- "Can I see my previous appointments?"
- "Will I get an appointment confirmation message?"
- Bengali: "আমাকে কি অ্যাপয়েন্টমেন্টের কথা মনে করিয়ে দেবেন"

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
  "intent": "greet|book_appointment|cancel_appointment|reschedule_appointment|check_availability|emergency|help|general|doctor_info|clinic_info|symptoms|appointment_info",
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
