<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Doctor;
use Carbon\Carbon;

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
    public const INTENT_DOCTOR_INFO = 'doctor_info';
    public const INTENT_CLINIC_INFO = 'clinic_info';
    public const INTENT_SYMPTOMS = 'symptoms';
    public const INTENT_APPOINTMENT_INFO = 'appointment_info';

    // Emergency keywords
    protected array $emergencyKeywords = [
        'chest pain', 'severe chest pain', 'heart attack',
        'chest hurts', 'pain in chest', 'hurt in chest',
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
        'need dentist', 'want dentist', 'see dentist', 'dentist appointment',
        'dental appointment', 'tooth doctor', 'see a dentist',
        // Bangla
        'অ্যাপয়েন্টমেন্ট', 'বুক', 'সাক্ষাৎ', 'ডাক্তার দেখাতে', 'সময় নিতে',
        'আমাকে একটি অ্যাপয়েন্টমেন্ট দিন', 'আমি অ্যাপয়েন্টমেন্ট নিতে চাই',
        'দাঁতের ডাক্তার', 'ডেন্টিস্ট',
    ];

    // List doctors keywords
    protected array $listDoctorsKeywords = [
        'doctor list', 'list of doctors', 'show doctors', 'available doctors',
        'all doctors', 'browse doctors', 'find a doctor', 'search doctor',
        'which doctors', 'what doctors', 'doctors available', 'dau gcl er',
        'opd', 'outdoor patient', 'outdoor', 'general opd',
        'ipd', 'indoor patient', 'indoor', 'admitted patient',
        'emergency', '24 hours', '24/7',
        'fever', 'cold', 'cough', 'flu', 'headache', 'stomach pain',
        'diabetes', 'blood pressure', 'bp', 'sugar',
        'dentist', 'dental', 'dentistry', 'tooth doctor', 'need dentist',
        // Bangla
        'ডাক্তার', 'ডাক্তার তালিকা', 'কোন ডাক্তার', 'ডাক্তার দেখাতে চাই',
        'ডাক্তারের তালিকা', 'ডাক্তার কোথায়', 'সব ডাক্তার',
        'ওপিডি', 'আউটডোর', 'ইনডোর', 'আইপিডি', 'জরুরি',
        'জ্বর', 'সর্দি', 'কাশি', 'মাথাব্যথা', 'পেটে ব্যথা',
        'ডায়াবেটিস', 'উচ্চ রক্তচাপ', 'চিনি',
        'দাঁত', 'দাঁতের ডাক্তার', 'মাড়ি', 'ডেন্টিস্ট',
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

    // last doctor list returned to user (used for mapping numbers back to IDs)
    protected array $lastDoctorIds = [];

    // Specializations mapping
    protected array $specializations = [
        'opd' => 'General Medicine',
        'outdoor' => 'General Medicine',
        'general' => 'General Medicine',
        'medicine' => 'General Medicine',
        'physician' => 'General Medicine',
        'md' => 'General Medicine',
        'fcps part 1' => 'General Medicine',
        'ipd' => 'General Medicine',
        'indoor' => 'General Medicine',
        'admitted' => 'General Medicine',
        'cardiologist' => 'Cardiology',
        'heart' => 'Cardiology',
        'cardiac' => 'Cardiology',
        'chest pain' => 'Cardiology',
        'bp' => 'Cardiology',
        'blood pressure' => 'Cardiology',
        'heart disease' => 'Cardiology',
        'dermatologist' => 'Dermatology',
        'skin' => 'Dermatology',
        'skin disease' => 'Dermatology',
        'dermatology' => 'Dermatology',
        'neurologist' => 'Neurology',
        'brain' => 'Neurology',
        'nervous' => 'Neurology',
        'neuro' => 'Neurology',
        'headache' => 'Neurology',
        'migraine' => 'Neurology',
        'orthopedic' => 'Orthopedics',
        'orthopaedics' => 'Orthopedics',
        'bone' => 'Orthopedics',
        'joint' => 'Orthopedics',
        'fracture' => 'Orthopedics',
        'back pain' => 'Orthopedics',
        'arthritis' => 'Orthopedics',
        'pediatrician' => 'Pediatrics',
        'pediatric' => 'Pediatrics',
        'child' => 'Pediatrics',
        'children' => 'Pediatrics',
        'kids' => 'Pediatrics',
        'baby' => 'Pediatrics',
        'infant' => 'Pediatrics',
        'psychiatrist' => 'Psychiatry',
        'psychiatric' => 'Psychiatry',
        'mental' => 'Psychiatry',
        'anxiety' => 'Psychiatry',
        'depression' => 'Psychiatry',
        'stress' => 'Psychiatry',
        'mental health' => 'Psychiatry',
        // use the actual specialization name stored in the database so the later
        // query ("where('name','LIKE',...)" ) will match correctly.  the
        // seeder creates "Ophthalmologist" not "Ophthalmology", so return that
        // term here.
        'ophthalmologist' => 'Ophthalmologist',
        'ophthalmology' => 'Ophthalmologist',
        'eye' => 'Ophthalmologist',
        'eyes' => 'Ophthalmologist',
        'vision' => 'Ophthalmologist',
        'eye disease' => 'Ophthalmologist',
        // Dentist must come before ENT to avoid "dentist" matching "ent"
        'dentist' => 'Dentist',
        'dental' => 'Dentist',
        'dentistry' => 'Dentist',
        'tooth' => 'Dentist',
        'teeth' => 'Dentist',
        'oral' => 'Dentist',
        'ent' => 'ENT',
        'ear' => 'ENT',
        'nose' => 'ENT',
        'throat' => 'ENT',
        'ear nose throat' => 'ENT',
        'hearing' => 'ENT',
        'sinus' => 'ENT',
        'gastroenterologist' => 'Gastroenterology',
        'gastroenterology' => 'Gastroenterology',
        'gastro' => 'Gastroenterology',
        'stomach' => 'Gastroenterology',
        'digestive' => 'Gastroenterology',
        'liver' => 'Gastroenterology',
        'intestine' => 'Gastroenterology',
        'urologist' => 'Urology',
        'urology' => 'Urology',
        'urinary' => 'Urology',
        'kidney' => 'Urology',
        'urine' => 'Urology',
        'gynecologist' => 'Gynecology',
        'gynecology' => 'Gynecology',
        'gynae' => 'Gynecology',
        'pregnancy' => 'Gynecology',
        'women' => 'Gynecology',
        'female' => 'Gynecology',
        'maternity' => 'Gynecology',
        'delivery' => 'Gynecology',
        'childbirth' => 'Gynecology',
        'oncologist' => 'Oncology',
        'oncology' => 'Oncology',
        'cancer' => 'Oncology',
        'tumor' => 'Oncology',
        'dentist' => 'Dentist',
        'dental' => 'Dentist',
        'dentistry' => 'Dentist',
        'tooth' => 'Dentist',
        'teeth' => 'Dentist',
        'oral' => 'Dentist',
        'nephrologist' => 'Nephrology',
        'nephrology' => 'Nephrology',
        'kidney disease' => 'Nephrology',
        'renal' => 'Nephrology',
        'endocrinologist' => 'Endocrinology',
        'endocrinology' => 'Endocrinology',
        'diabetes' => 'Endocrinology',
        'sugar' => 'Endocrinology',
        'thyroid' => 'Endocrinology',
        'hormone' => 'Endocrinology',
        'সাধারণ' => 'General Medicine',
        'মেডিসিন' => 'General Medicine',
        'ডাক্তার' => 'General Medicine',
        'চিকিৎসক' => 'General Medicine',
        'হৃদরোগ' => 'Cardiology',
        'হৃদস্পন্দন' => 'Cardiology',
        'বুকে ব্যথা' => 'Cardiology',
        'উচ্চ রক্তচাপ' => 'Cardiology',
        'চর্ম' => 'Dermatology',
        'চামড়া' => 'Dermatology',
        'ত্বক' => 'Dermatology',
        'চর্মরোগ' => 'Dermatology',
        'স্নায়ু' => 'Neurology',
        'মস্তিষ্ক' => 'Neurology',
        'মাথাব্যথা' => 'Neurology',
        'হাড়' => 'Orthopedics',
        'জয়েন্ট' => 'Orthopedics',
        'পেশি' => 'Orthopedics',
        'ফ্র্যাকচার' => 'Orthopedics',
        'হাড় ভাঙা' => 'Orthopedics',
        'শিশু' => 'Pediatrics',
        'বাচ্চা' => 'Pediatrics',
        'ছোট বাচ্চা' => 'Pediatrics',
        'মানসিক' => 'Psychiatry',
        'দুঃচিন্তা' => 'Psychiatry',
        'বিষণ্নতা' => 'Psychiatry',
        'অবসাদ' => 'Psychiatry',
        'চোখ' => 'Ophthalmology',
        'চোখের' => 'Ophthalmology',
        'দৃষ্টি' => 'Ophthalmology',
        'কান' => 'ENT',
        'নাক' => 'ENT',
        'গলা' => 'ENT',
        'পাকস্থলী' => 'Gastroenterology',
        'পেট' => 'Gastroenterology',
        'আমাশয়' => 'Gastroenterology',
        'কলি' => 'Gastroenterology',
        'লিভার' => 'Gastroenterology',
        'কিডনি' => 'Nephrology',
        ' প্রস্রাব' => 'Urology',
        'গাইনি' => 'Gynecology',
        'মা' => 'Gynecology',
        'সন্তান' => 'Gynecology',
        'প্রসব' => 'Gynecology',
        'গর্ভবতী' => 'Gynecology',
        'ক্যান্সার' => 'Oncology',
        'টিউমার' => 'Oncology',
        'ডায়াবেটিস' => 'Endocrinology',
        'চিনি' => 'Endocrinology',
        'থাইরয়েড' => 'Endocrinology',
        'দাঁত' => 'Dentist',
        'দাঁতে ব্যথা' => 'Dentist',
        'মাড়ি' => 'Dentist',
    ];

    /**
     * Training Data: 50 Real Patient Questions
     */
    protected array $trainingData = [
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

    protected array $doctorInfoKeywords = [
        'best doctor', 'good doctor', 'experience', 'visiting hours', 'schedule',
        'profile', 'education', 'qualification', 'languages', 'female doctor',
        'woman doctor', 'male doctor', 'specialist', 'expert',
        'ভালো ডাক্তার', 'অভিজ্ঞতা', 'সময়সূচি', 'প্রোফাইল', 'শিক্ষা',
        'যোগ্যতা', 'ভাষা', 'মহিলা ডাক্তার', 'পুরুষ ডাক্তার', 'বিশেষজ্ঞ',
    ];

    protected array $clinicInfoKeywords = [
        'opening hours', 'open hours', 'hours', 'location', 'address', 'parking',
        'emergency', '24 hours', 'online consultation', 'insurance', 'fee',
        'consultation fee', 'price', 'cost', 'laboratory', 'lab', 'home visit',
        'খোলার সময়', 'খোলা', 'সময়', 'ঠিকানা', 'লোকেশন', 'পার্কিং',
        'জরুরি', '২৪ ঘণ্টা', 'অনলাইন', 'বীমা', 'ফি', 'পরামর্শ ফি',
        'ল্যাব', 'ল্যাবরেটরি', 'বাড়িতে', 'হোম ভিজিট',
    ];

    protected array $symptomKeywords = [
        'chest pain', 'heart pain', 'fever', 'cough', 'cold', 'flu',
        'stomach pain', 'belly pain', 'headache', 'migraine', 'dizzy',
        'dizziness', 'weak', 'weakness', 'tired', 'fatigue', 'allergy',
        'skin problem', 'rash', 'itching', 'blood pressure', 'bp', 'high bp',
        'diabetes', 'sugar', 'back pain', 'neck pain', 'joint pain',
        'breathing', 'shortness of breath', 'asthma', 'cough', 'vomiting',
        'nausea', 'diarrhea', 'constipation', 'pregnancy', 'baby', 'child',
        'tooth pain', 'teeth pain', 'toothache', 'gum pain', 'gum bleeding',
        'বুকে ব্যথা', 'হৃদযন্ত্র', 'জ্বর', 'সর্দি', 'কাশি', 'ফ্লু',
        'পেটে ব্যথা', 'মাথাব্যথা', 'মাইগ্রেইন', 'মাথা ঘুরা', 'দুর্বল',
        'ক্লান্ত', 'অ্যালার্জি', 'ত্বক', 'র‌্যাশ', 'চুলকানি',
        'রক্তচাপ', 'উচ্চ রক্তচাপ', 'ডায়াবেটিস', 'চিনি', 'পিঠে ব্যথা',
        'গলায় ব্যথা', 'শ্বাস নিতে সমস্যা', 'আসমা', 'বমি', 'ডায়রিয়া',
        'কোষ্ঠকাঠিন্য', 'গর্ভবতী', 'শিশু', 'বাচ্চা',
        'দাঁতে ব্যথা', 'দাঁতের ব্যথা', 'মাড়িতে ব্যথা',
    ];

    protected array $appointmentInfoKeywords = [
        'remind', 'reminder', 'document', 'documents', 'bring', 'arrive',
        'early', 'change doctor', 'switch doctor', 'multiple', 'previous',
        'past', 'history', 'download', 'prescription', 'prescription download',
        'confirmation', 'confirm', 'sms', 'message', 'email', 'pay',
        'payment', 'online payment', 'contact', 'phone', 'call', 'whatsapp',
        'মনে করিয়ে', 'রিমাইন্ডার', 'কাগজপত্র', 'নিতে হবে', 'আসা',
        'তাড়াতাড়ি', 'ডাক্তার পরিবর্তন', 'একাধিক', 'আগের', 'ইতিহাস',
        'ডাউনলোড', 'প্রেসক্রিপশন', 'কনফার্মেশন', 'মেসেজ', 'এসএমএস',
        'ইমেইল', 'পেমেন্ট', 'অনলাইন পেমেন্ট', 'যোগাযোগ', 'ফোন', 'কল',
    ];

    public function __construct()
    {
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
            if ($this->detectEmergency($message)) {
                return $this->buildEmergencyResponse();
            }

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

            return $this->processLocally($message, $context);
        } catch (Exception $e) {
            Log::error('AI Service Error: ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
            
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
            // if the user isn't actively asking for a list of doctors, the last
            // cached ID array is no longer valid.  Clearing it prevents an
            // unrelated number later from picking the wrong doctor.
            if ($detectedIntent !== self::INTENT_LIST_DOCTORS) {
                $this->lastDoctorIds = [];
            }
            
            foreach ($this->specializations as $keyword => $specialization) {
                if (str_contains($lowerMessage, $keyword)) {
                    $detectedSpecialization = $specialization;
                    Log::debug('Detected specialization in message: ' . $keyword . ' => ' . $specialization);
                    break;
                }
            }
            
            $extractedData = [];
            try {
                $extractedData = $this->extractEntitiesFromMessage($message, $context);
                Log::debug('Extracted entities from message: ' . json_encode($extractedData));
            } catch (Exception $e) {
                Log::warning('extractEntitiesFromMessage error: ' . $e->getMessage());
            }
            
            if ($detectedSpecialization !== null) {
                $extractedData['specialization'] = $detectedSpecialization;
            }
            
            try {
                $doctorNumber = $this->extractDoctorNumber($message);
                if ($doctorNumber !== null) {
                    // if the message also contained a date (e.g. user replied "6"
                    // to a list of days) then we should not treat the number as a
                    // doctor selection.  extractEntities already fills
                    // "date" in that case.
                    if (empty($extractedData['date'])) {
                        $extractedData['doctor_number'] = $doctorNumber;

                        // Map against the most recently shown doctor list if available
                        if (!empty($this->lastDoctorIds) && isset($this->lastDoctorIds[$doctorNumber - 1])) {
                            $extractedData['selected_doctor_id'] = $this->lastDoctorIds[$doctorNumber - 1];
                            Log::debug('Mapped extracted doctor_number ' . $doctorNumber . ' to selected_doctor_id ' . $extractedData['selected_doctor_id']);
                            // we've consumed the cached list; remove it from context
                            unset($extractedData['last_doctor_ids']);
                            // also clear the service property so it doesn't linger
                            $this->lastDoctorIds = [];
                        }
                    } else {
                        Log::debug('Ignored doctor_number ' . $doctorNumber . ' because date was extracted from same message');
                    }
                }
            } catch (Exception $e) {
                Log::warning('extractDoctorNumber error: ' . $e->getMessage());
            }
            
            $doctorNameFromMessage = $this->extractDoctorName($message);
            if ($doctorNameFromMessage) {
                $extractedData['doctor_name'] = $doctorNameFromMessage;
                Log::debug('Doctor name extracted from message: ' . $doctorNameFromMessage);
            }
            
            if (!empty($context['extracted_data'])) {
                // Filter out null values from new extracted data to preserve context values
                $newDataFiltered = array_filter($extractedData, function($value) {
                    return $value !== null;
                });
                $extractedData = array_merge($context['extracted_data'], $newDataFiltered);
                Log::debug('Merged extracted_data: context=' . json_encode($context['extracted_data']) . ', new=' . json_encode($extractedData));
            }
            
            $response = $this->buildResponse($detectedIntent, $extractedData, $context);

            // If we just showed a doctor list, stash the IDs in the extracted data
            // so subsequent messages can map a numeric reply to the correct record.
            if ($detectedIntent === self::INTENT_LIST_DOCTORS && !empty($this->lastDoctorIds)) {
                $extractedData['last_doctor_ids'] = $this->lastDoctorIds;
            }

            return [
                'intent' => $detectedIntent,
                'response' => $response,
                'extracted_data' => $extractedData,
                'emergency_detected' => false,
            ];
        } catch (Exception $e) {
            Log::error('processLocally Error: ' . $e->getMessage());
            
            return [
                'intent' => $detectedIntent,
                'response' => "I can help you book a doctor appointment. What type of doctor would you like to see? (e.g., cardiologist, dermatologist, eye doctor/ophthalmologist)",
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
        
        Log::debug('detectIntent called with message: ' . $message . ' (lower: ' . $lowerMessage . '), currentIntent: ' . ($currentIntent ?? 'null'));
        
        $trimmedMessage = trim($lowerMessage);
        if ($trimmedMessage === 'book' || $trimmedMessage === 'appointment' || 
            $trimmedMessage === 'book appointment' || $trimmedMessage === 'i want to book' ||
            str_starts_with($trimmedMessage, 'book ') || str_starts_with($trimmedMessage, 'appointment') ||
            str_contains($trimmedMessage, 'need dentist') || str_contains($trimmedMessage, 'want dentist') ||
            str_contains($trimmedMessage, 'see dentist') || str_contains($trimmedMessage, 'dental') ||
            str_contains($trimmedMessage, 'দাঁত') || str_contains($trimmedMessage, 'ডেন্টিস্ট') ||
            str_contains($trimmedMessage, 'দাঁতের ডাক্তার')) {
            Log::debug('Detected INTENT_BOOK_APPOINTMENT from quick check: ' . $trimmedMessage);
            return self::INTENT_BOOK_APPOINTMENT;
        }
        
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
        
        if (is_numeric($trimmedMessage) && intval($trimmedMessage) >= 1 && intval($trimmedMessage) <= 10) {
            // if we haven't shown a list or already chosen a doctor, an isolated
            // number should not start the booking flow.  'last_doctor_ids' is
            // stored in context when we list doctors.
            $hasDoctorInContext = !empty($extractedData['doctor_number']) || 
                                  !empty($extractedData['selected_doctor_id']) ||
                                  !empty($extractedData['doctor_name']);
            $hasListContext = !empty($context['extracted_data']['last_doctor_ids']);
            $hasSpecializationContext = !empty($extractedData['specialization']);
            
            // Also check context for specialization (from previous messages)
            $hasSpecializationContext = $hasSpecializationContext || !empty($context['extracted_data']['specialization']);
            
            if (!$hasDoctorInContext && !$hasListContext && !$hasSpecializationContext) {
                Log::debug('Numeric message without booking context, returning GENERAL');
                return self::INTENT_GENERAL;
            }

            // If we already have doctor AND date in context OR doctor AND time preference,
            // this could be a time slot selection.
            $hasDateInContext = !empty($extractedData['date']);
            $hasTimePreference = !empty($extractedData['time_preference']);
            $newDoctorNumber = intval($trimmedMessage);

            if ($hasDoctorInContext && ($hasDateInContext || $hasTimePreference)) {
                // determine slot count for the currently selected doctor/date
                $slotCount = 0;
                try {
                    $doctor = null;
                    if (!empty($extractedData['selected_doctor_id'])) {
                        $doctor = Doctor::with('schedules')->find($extractedData['selected_doctor_id']);
                    } elseif (!empty($extractedData['doctor_number'])) {
                        // if we have doctor_number but not id, we may have a recent
                        // list from lastDoctorIds; otherwise fall back to query by
                        // ranking similar to buildBookingResponse
                        if (!empty($this->lastDoctorIds) && isset($this->lastDoctorIds[$extractedData['doctor_number'] - 1])) {
                            $doctor = Doctor::with('schedules')->find($this->lastDoctorIds[$extractedData['doctor_number'] - 1]);
                        } else {
                            $doctorQuery = Doctor::query()->with('schedules')
                                ->orderBy('rating','desc')->orderBy('experience_years','desc');
                            $doctor = $doctorQuery->skip($extractedData['doctor_number'] - 1)->first();
                        }
                    } elseif (!empty($extractedData['doctor_name'])) {
                        $doctor = Doctor::query()->with('schedules')
                            ->whereHas('user', function($q) use ($extractedData) {
                                $q->where('name','LIKE','%'.$extractedData['doctor_name'].'%');
                            })->first();
                    }

                    if ($doctor) {
                        $slots = $doctor->getAvailableTimeSlotsForDate($extractedData['date']);
                        $slotCount = count($slots);
                    }
                } catch (
                    Exception $e) {
                    Log::warning('slot count lookup failed: '.$e->getMessage());
                }

                if ($slotCount > 0 && $newDoctorNumber <= $slotCount) {
                    Log::debug('Number within slot count ('.$newDoctorNumber.' <= '.$slotCount.'), treating as time slot');
                    return self::INTENT_BOOK_APPOINTMENT;
                }
                // otherwise fall through and allow later logic to treat it as doctor
            }

            Log::debug('Detected doctor number, assuming booking intent: ' . $trimmedMessage);
            return self::INTENT_BOOK_APPOINTMENT;
        }
        
        if (!empty($currentIntent) && $currentIntent === 'book_appointment') {
            Log::debug('Previous intent was booking, checking for specialization or doctor number');
            foreach ($this->specializations as $keyword => $specialization) {
                if (str_contains($lowerMessage, $keyword)) {
                    Log::debug('Detected specialization: ' . $keyword . ' => ' . $specialization);
                    return self::INTENT_BOOK_APPOINTMENT;
                }
            }
            
            // Check if we already have doctor and date in context - if so, any number is time slot
            $hasDoctorInContext = !empty($extractedData['doctor_number']) || 
                                  !empty($extractedData['selected_doctor_id']) ||
                                  !empty($extractedData['doctor_name']);
            $hasDateInContext = !empty($extractedData['date']);
            
            if (is_numeric($trimmedMessage) && intval($trimmedMessage) >= 1) {
                if ($hasDoctorInContext && $hasDateInContext) {
                    // Already have doctor and date - this must be time slot selection
                    Log::debug('Doctor + date in context, treating ' . $trimmedMessage . ' as time slot selection');
                    return self::INTENT_BOOK_APPOINTMENT;
                } elseif (!$hasDoctorInContext && intval($trimmedMessage) >= 1 && intval($trimmedMessage) <= 10) {
                    // No doctor yet, number 1-10 could be doctor selection
                    Log::debug('Detected doctor number in context: ' . $trimmedMessage);
                    return self::INTENT_BOOK_APPOINTMENT;
                }
            }
        }
        
        if ($this->matchesAny($message, $this->bookingKeywords)) {
            Log::debug('Detected INTENT_BOOK_APPOINTMENT from booking keywords');
            return self::INTENT_BOOK_APPOINTMENT;
        }
        
        $doctorNameInMessage = $this->extractDoctorName($message);
        if ($doctorNameInMessage) {
            Log::debug('Detected doctor name in message: ' . $doctorNameInMessage);
            return self::INTENT_BOOK_APPOINTMENT;
        }
        
        // Check if message is a number that could be time slot selection
        // Only treat as doctor selection if we don't already have doctor+date selected
        $hasDoctorInContext = !empty($extractedData['doctor_number']) || 
                              !empty($extractedData['selected_doctor_id']) ||
                              !empty($extractedData['doctor_name']);
        $hasDateInContext = !empty($extractedData['date']);
        
        if ($this->isDoctorSelection($message)) {
            if ($hasDoctorInContext && $hasDateInContext) {
                // compute available slot count for context doctor/date
                $newDoctorNumber = intval(trim($message));
                $slotCount = 0;
                try {
                    $doctor = null;
                    if (!empty($extractedData['selected_doctor_id'])) {
                        $doctor = Doctor::with('schedules')->find($extractedData['selected_doctor_id']);
                    } elseif (!empty($extractedData['doctor_number'])) {
                        if (!empty($this->lastDoctorIds) && isset($this->lastDoctorIds[$extractedData['doctor_number'] - 1])) {
                            $doctor = Doctor::with('schedules')->find($this->lastDoctorIds[$extractedData['doctor_number'] - 1]);
                        } else {
                            $doctorQuery = Doctor::query()->with('schedules')
                                ->orderBy('rating','desc')->orderBy('experience_years','desc');
                            $doctor = $doctorQuery->skip($extractedData['doctor_number'] - 1)->first();
                        }
                    } elseif (!empty($extractedData['doctor_name'])) {
                        $doctor = Doctor::query()->with('schedules')
                            ->whereHas('user', function($q) use ($extractedData) {
                                $q->where('name','LIKE','%'.$extractedData['doctor_name'].'%');
                            })->first();
                    }

                    if ($doctor) {
                        $slots = $doctor->getAvailableTimeSlotsForDate($extractedData['date']);
                        $slotCount = count($slots);
                    }
                } catch (Exception $e) {
                    Log::warning('slot count lookup failed: '.$e->getMessage());
                }

                if ($slotCount > 0 && $newDoctorNumber <= $slotCount) {
                    Log::debug('Number within slot count ('.$newDoctorNumber.' <= '.$slotCount.'), treating as time slot');
                    return self::INTENT_BOOK_APPOINTMENT;
                }
                // fall through to treat as new doctor
            }

            // Otherwise, treat as doctor selection
            Log::debug('Detected INTENT_BOOK_APPOINTMENT from doctor selection');
            return self::INTENT_BOOK_APPOINTMENT;
        }
        
        if ($this->isDateTimeInput($message)) {
            $tempExtracted = [];
            try {
                $tempExtracted = $this->extractEntitiesFromMessage($message);
            } catch (Exception $e) {
                Log::warning('extractEntitiesFromMessage error in date check: ' . $e->getMessage());
            }
            
            $checkData = array_merge($extractedData, $tempExtracted);
            
            if (!empty($checkData['doctor_number']) || 
                !empty($checkData['selected_doctor_id']) ||
                !empty($checkData['date'])) {
                return self::INTENT_BOOK_APPOINTMENT;
            }
        }
        
        if ($this->isContactInfoInput($message)) {
            $tempExtracted = [];
            try {
                $tempExtracted = $this->extractEntitiesFromMessage($message);
            } catch (Exception $e) {
                Log::warning('extractEntitiesFromMessage error in contact check: ' . $e->getMessage());
            }
            
            $checkData = array_merge($extractedData, $tempExtracted);
            $contextData = $context['extracted_data'] ?? [];
            $mergedCheckData = array_merge($contextData, $checkData);
            
            // if we already know a doctor and date, contact info should keep
            // the intent in the booking flow. previously we only checked for
            // time_preference; after the user picks a specific slot we instead
            // have time_slot_number, so the condition would fail and the
            // message would fall back to a general intent. include both fields
            // here so supplying name/phone keeps the conversation in booking.
            if ((!empty($mergedCheckData['doctor_number']) || !empty($mergedCheckData['selected_doctor_id'])) &&
                (!empty($mergedCheckData['date'])) &&
                (!empty($mergedCheckData['time_preference']) || !empty($mergedCheckData['time_slot_number']))) {
                return self::INTENT_BOOK_APPOINTMENT;
            }
        }

        if ($this->detectEmergency($message)) {
            return self::INTENT_EMERGENCY;
        }

        $intent = $this->matchTrainingData($message);
        Log::debug('matchTrainingData returned: ' . $intent);
        if ($intent !== self::INTENT_GENERAL) {
            return $intent;
        }

        if ($this->matchesAny($message, ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening'])) {
            Log::debug('Detected INTENT_GREET');
            return self::INTENT_GREET;
        }

        if ($this->matchesAny($message, ['thank', 'thanks', 'appreciate'])) {
            return self::INTENT_THANKS;
        }

        if ($this->matchesAny($message, ['bye', 'goodbye', 'see you', 'talk later'])) {
            return self::INTENT_GOODBYE;
        }

        if ($this->matchesAny($message, ['help', 'what can you do', 'capabilities'])) {
            return self::INTENT_HELP;
        }

        if ($this->matchesAny($message, $this->cancelKeywords)) {
            return self::INTENT_CANCEL_APPOINTMENT;
        }

        if ($this->matchesAny($message, $this->rescheduleKeywords)) {
            return self::INTENT_RESCHEDULE_APPOINTMENT;
        }

        if ($this->matchesAny($message, $this->doctorInfoKeywords)) {
            return self::INTENT_DOCTOR_INFO;
        }

        if ($this->matchesAny($message, $this->clinicInfoKeywords)) {
            return self::INTENT_CLINIC_INFO;
        }

        if ($this->matchesAny($message, $this->symptomKeywords)) {
            return self::INTENT_SYMPTOMS;
        }

        if ($this->matchesAny($message, $this->appointmentInfoKeywords)) {
            return self::INTENT_APPOINTMENT_INFO;
        }

        if ($this->matchesAny($message, $this->listDoctorsKeywords)) {
            return self::INTENT_LIST_DOCTORS;
        }

        if ($this->matchesAny($message, ['available', 'availability', 'schedule', 'when are you open', 'timings', 'doctor available', 'doctors available', 'is doctor available'])) {
            return self::INTENT_CHECK_AVAILABILITY;
        }

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
        
        $dateKeywords = ['today', 'tomorrow', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday', 'next week'];
        $timeKeywords = ['morning', 'afternoon', 'evening', 'noon', 'night', 'am', 'pm', '12 pm', '10 am', '11 am', '2 pm', '3 pm', '4 pm', '5 pm', '6 pm', '9 am'];
        $bnDateKeywords = ['আজকে', 'কাল', 'পরের সপ্তাহ', 'সোমবার', 'মঙ্গলবার', 'বুধবার', 'বৃহস্পতিবার', 'শুক্রবার', 'শনিবার', 'রবিবার'];
        $bnTimeKeywords = ['সকাল', 'দুপুর', 'বিকেল', 'সন্ধ্যা', 'রাত', 'সকালে', 'দুপুরে', 'বিকেলে'];
        
        foreach ($dateKeywords as $keyword) {
            if (str_contains($lowerMessage, $keyword)) {
                return true;
            }
        }
        
        foreach ($timeKeywords as $keyword) {
            if (str_contains($lowerMessage, $keyword)) {
                return true;
            }
        }
        
        foreach ($bnDateKeywords as $keyword) {
            if (str_contains($lowerMessage, $keyword)) {
                return true;
            }
        }
        
        foreach ($bnTimeKeywords as $keyword) {
            if (str_contains($lowerMessage, $keyword)) {
                return true;
            }
        }
        
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
        if (preg_match('/^(1|2|3|4|5|6|7|8|9|10)$/', trim($message))) {
            return true;
        }
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
        
        $phonePatterns = [
            '/\+?\d{1,3}[-.\s]?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}/',
            '/01[3-9]\d{8}/',
            '/\d{10,11}/',
            '/\d{4}[-.\s]?\d{4}[-.\s]?\d{4}/',
        ];
        
        foreach ($phonePatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }
        
        $enNamePatterns = [
            'my name is', 'i am', 'name is', 'this is', 'i\'m',
            'my name\'s', 'call me', 'name:', 'name -'
        ];
        
        foreach ($enNamePatterns as $pattern) {
            if (str_contains($lowerMessage, $pattern)) {
                return true;
            }
        }
        
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
        
        if (preg_match('/[\x{0980}-\x{09FF}]/u', $message)) {
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
        
        if (preg_match('/^(1|2|3|4|5|6|7|8|9|10)$/', $message)) {
            return (int) $message;
        }
        if (preg_match('/(?:number|option|no\.)\s*(1|2|3|4|5|6|7|8|9|10)/i', $message, $matches)) {
            return (int) $matches[1];
        }
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
        $patterns = [
            '/(?:dr\.?\s+)([a-z]+(?:\s+[a-z]+)*)/i',
            '/(?:doctor\s+)([a-z]+(?:\s+[a-z]+)*)/i',
            '/(?:book\s+.*\s+with\s+)([a-z]+(?:\s+[a-z]+)*)/i',
            '/(?:appointment\s+with\s+)([a-z]+(?:\s+[a-z]+)*)/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                $doctorName = trim($matches[1]);
                $doctor = Doctor::query()
                    ->with(['user'])
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
        
        $doctors = Doctor::query()
            ->with(['user'])
            ->limit(20)
            ->get();
        
        $lowerMessage = strtolower($message);
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
        
        if (isset($this->trainingData[$lowerMessage])) {
            return $this->trainingData[$lowerMessage];
        }

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
    protected function extractEntitiesFromMessage(string $message, array $context = []): array
    {
        $lowerMessage = strtolower($message);
        $entities = [
            'specialization' => null,
            'symptoms' => [],
            'date' => null,
            'time_preference' => null,
            'time_slot_number' => null,
            'patient_name' => null,
            'phone' => null,
            'email' => null,
            'location' => null,
            'appointment_number' => null,
        ];

        Log::debug('extractEntitiesFromMessage called with: ' . $message);

        // Check if doctor is already selected in context
        $hasDoctorInContext = !empty($context['extracted_data']['doctor_number']) ||
                              !empty($context['extracted_data']['selected_doctor_id']) ||
                              !empty($context['extracted_data']['doctor_name']);
        
        foreach ($this->specializations as $keyword => $specialization) {
            if (str_contains($lowerMessage, $keyword)) {
                $entities['specialization'] = $specialization;
                break;
            }
        }

        Log::debug('Checking for date patterns in message: ' . $message);
        
        // Map for numeric day selection (1-7 for days of week)
        $dayNumberMap = [
            '1' => 'monday', '2' => 'tuesday', '3' => 'wednesday', 
            '4' => 'thursday', '5' => 'friday', '6' => 'saturday', '7' => 'sunday',
            '8' => 'tomorrow', '9' => 'today'
        ];
        
        // Check for numeric day selection first (e.g., "1" for Monday).
        // Only treat bare numbers as dates when a doctor is already selected in
        // the context.  Otherwise a single digit could easily be a doctor
        // number, and we don't want to turn "2" into Tuesday during the
        // initial doctor selection step.  The caller (processLocally) will
        // still later run extractDoctorNumber so the doctor choice isn't lost.
        if (is_numeric(trim($message)) && isset($dayNumberMap[trim($message)])) {
            if ($hasDoctorInContext) {
                Log::debug('Numeric day selected (doctor in context): ' . $message . ' => ' . $dayNumberMap[trim($message)]);
                $entities['date'] = $this->parseRelativeDate($dayNumberMap[trim($message)]);
            } else {
                Log::debug('Numeric message "' . $message . '" detected but no doctor in context, skipping numeric day mapping');
            }
        } elseif (preg_match('/(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/', $message, $matches)) {
            Log::debug('Date pattern matched: ' . $matches[1]);
            $entities['date'] = $this->parseDate($matches[1]);
        } elseif (preg_match('/(today|tomorrow|next week|monday|tuesday|wednesday|thursday|friday|saturday|sunday)/i', $message, $matches)) {
            Log::debug('Relative date pattern matched: ' . $matches[1]);
            $entities['date'] = $this->parseRelativeDate($matches[1]);
            Log::debug('Parsed date: ' . ($entities['date'] ?? 'null'));
        }
        
        if (empty($entities['date'])) {
            Log::debug('No date pattern matched for message: ' . $message);
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
                // only map to a date if the user has already selected a doctor
                if ($hasDoctorInContext && str_contains($lowerMessage, $bn)) {
                    $entities['date'] = $this->parseRelativeDate($en);
                    break;
                }
            }
        }

        // Extract time_slot_number based on context
        // If doctor is already selected, treat numbers as time slots
        // However, if there's also a date in context, check if this could be a NEW doctor selection
        // This handles the case where user picks a new doctor after "no slots available"
        // Check if we just extracted a day number in the date extraction section above
        $extractedDayNumber = null;
        if (!empty($entities['date'])) {
            // A date was extracted - find which day number was used
            foreach ($dayNumberMap as $num => $day) {
                if ($entities['date'] === $this->parseRelativeDate($day)) {
                    $extractedDayNumber = $num;
                    break;
                }
            }
        }
        
        // Now check for time_slot_number - but NOT if we just extracted a day number
        // because in that case, the number is for date selection, not time slot
        $numValue = intval($message);
        if ($extractedDayNumber === null && is_numeric($message) && $numValue >= 1 && $numValue <= 20) {
            $hasDateInContext = !empty($context['extracted_data']['date']);
            
            // Also check for time_preference - if user selected morning/afternoon/evening,
            // then a number response should be treated as time slot selection
            $hasTimePreference = !empty($context['extracted_data']['time_preference']);
            
            // Only set time_slot_number if we have date OR time_preference in context
            // If we just extracted a day number, don't override with time_slot_number
            if ($hasDoctorInContext && ($hasDateInContext || $hasTimePreference)) {
                // Both doctor and date exist in context OR doctor and time preference exist
                // - assume the user is selecting a time slot.
                $entities['time_slot_number'] = $numValue;
                Log::debug('Doctor in context, setting time_slot_number: ' . $numValue . ', hasDate=' . ($hasDateInContext ? 'yes' : 'no') . ', hasTimePref=' . ($hasTimePreference ? 'yes' : 'no'));
            } else {
                // No date or time_preference in context yet - don't set time_slot_number
                // This could be a date selection or doctor number
                Log::debug('No date/time preference in context, not setting time_slot_number for: ' . $message);
            }
        } elseif ($extractedDayNumber === null && $this->matchesAny($lowerMessage, ['morning', 'am', '10 am', '11 am', '9 am'])) {
            $entities['time_preference'] = 'morning';
        } elseif ($this->matchesAny($lowerMessage, ['afternoon', 'pm', '2 pm', '3 pm', '4 pm'])) {
            $entities['time_preference'] = 'afternoon';
        } elseif ($this->matchesAny($lowerMessage, ['evening', '6 pm', '7 pm', '8 pm'])) {
            $entities['time_preference'] = 'evening';
        } else {
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

        $phonePatterns = [
            // International format with +88 (Bangladesh)
            '/\+88\d{10}/',
            // Bangladeshi mobile: 01XXXXXXXXX (11 digits starting with 01)
            '/01[3-9]\d{9}/',
            // Alternative Bangladeshi format (10 digits)
            '/01[3-9]\d{8}/',
            // Generic international formats
            '/\+?\d{1,3}[-.\s]?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}/',
            // Short Bangladeshi mobile (8 digits with leading 0)
            '/0\d{7,8}/',
            // Any 8-11 digit number
            '/\d{10,11}/',
        ];
        foreach ($phonePatterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                $entities['phone'] = $matches[0];
                Log::debug('Phone extracted: ' . $entities['phone']);
                break;
            }
        }

        // If phone found, extract name from remaining text
        if ($entities['phone']) {
            // Remove common separators and the phone number
            $tempMessage = str_replace($entities['phone'], '', $message);
            $tempMessage = str_replace(',', ' ', $tempMessage);
            $remaining = trim($tempMessage);
            $remaining = preg_replace('/[:,\-]/', ' ', $remaining);
            $remaining = trim(preg_replace('/\b(my name is|i am|i\'m|name is|name|phone|mobile|number)\b/i','',$remaining));
            $remaining = trim(preg_replace('/\s+/', ' ', $remaining)); // normalize whitespace
            if ($remaining !== '' && preg_match('/[A-Za-z\x{0980}-\x{09FF}]/u', $remaining)) {
                if (empty($entities['patient_name'])) {
                    // Clean up the name - take first word if multiple words
                    $nameParts = preg_split('/\s+/', $remaining);
                    $potentialName = trim($nameParts[0]);
                    // Clean up the name - only keep letters
                    $potentialName = preg_replace('/[^A-Za-z]/', '', $potentialName);
                    if (strlen($potentialName) >= 2) {
                        $entities['patient_name'] = ucfirst(strtolower($potentialName));
                        Log::debug('Name extracted from remaining: ' . $entities['patient_name']);
                    }
                }
            }
        } else {
            // No phone found - try to extract just the name
            // Check for common name patterns
            if (preg_match('/(?:my name is|i am|i\'m)\s+([A-Za-z]+(?:\s+[A-Za-z]+)*)/i', $message, $matches)) {
                $entities['patient_name'] = ucfirst(strtolower($matches[1]));
                Log::debug('Name extracted (no phone): ' . $entities['patient_name']);
            } elseif (empty($entities['patient_name']) && preg_match('/^([A-Za-z]+)\s+\d{5,}/', $message, $matches)) {
                // Name followed by number (like "John 01918329829")
                $entities['patient_name'] = ucfirst(strtolower($matches[1]));
                Log::debug('Name extracted (name before number): ' . $entities['patient_name']);
            } elseif (empty($entities['patient_name'])) {
                // Try to find a name at the start of message (first word with letters only)
                $words = preg_split('/\s+/', $message);
                foreach ($words as $word) {
                    $cleanWord = preg_replace('/[^A-Za-z]/', '', $word);
                    if (strlen($cleanWord) >= 2 && !is_numeric($cleanWord)) {
                        // Filter out common medical/specialization terms and other non-name words
                        $notNameTerms = ['heart', 'cardio', 'cardiac', 'neuro', 'brain', 'ortho', 'bone', 'derma', 'skin', 'eye', 'optic', 'ear', 'ent', 'child', 'pediatric', 'baby', 'women', 'female', 'pregnant', 'cancer', 'tumor', 'diabetes', 'sugar', 'kidney', 'liver', 'lung', 'breath', 'stomach', 'gas', 'thyroid', 'doctor', 'need', 'want', 'help', 'please', 'book', 'appointment', 'make', 'get', 'have', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday', 'today', 'tomorrow', 'yesterday', 'morning', 'afternoon', 'evening', 'night'];
                        if (!in_array(strtolower($cleanWord), $notNameTerms)) {
                            $entities['patient_name'] = ucfirst(strtolower($cleanWord));
                            Log::debug('Name extracted (first word): ' . $entities['patient_name']);
                            break;
                        }
                    }
                }
            }
        }

        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $message, $matches)) {
            $entities['email'] = $matches[0];
        }

        if (preg_match('/(APT|APP)[-]?\d{6}/i', $message, $matches)) {
            $entities['appointment_number'] = strtoupper($matches[0]);
        }

        if (preg_match('/(?:my name is|i am|i\'m)\s+([A-Za-z]+(?:\s+[A-Za-z]+)*)/i', $message, $matches)) {
            $entities['patient_name'] = ucfirst(strtolower($matches[1]));
        }
        
        if (preg_match('/(?:আমার\s*নাম|নাম\s*:?\s*)([\x{0980}-\x{09FF}]+)/u', $message, $matches)) {
            $entities['patient_name'] = $matches[1];
        }
        
        if (empty($entities['patient_name']) && preg_match('/(?:এটি|এই|আমি|হ্যাঁ)\s+([\x{0980}-\x{09FF}]{2,})/u', $message, $matches)) {
            $notName = ['নাম', 'ফোন', 'মোবাইল', 'নম্বর', 'হ্যাঁ', 'না'];
            if (!in_array($matches[1], $notName)) {
                $entities['patient_name'] = $matches[1];
            }
        }
        
        if (empty($entities['patient_name'])) {
            $bnNamePatterns = [
                '/^([\x{0980}-\x{09FF}]{2,})$/u',
                '/\s+([\x{0980}-\x{09FF}]{2,})$/u',
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

        return $entities;
    }

    /**
     * Build response based on intent
     */
    protected function buildResponse(string $intent, array $extractedData, array $context): string
    {
        $language = $context['language'] ?? 'en';
        
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
            Log::debug('buildBookingResponse called with data: ' . json_encode($data) . ', context extracted_data: ' . json_encode($context['extracted_data'] ?? []));
            
            $contextData = $context['extracted_data'] ?? [];
            
            // Fix: Don't let null values from new data overwrite existing context values
            // This preserves specialization, doctor_number, etc. from context
            $dataWithNonNulls = array_filter($data, function($value) {
                return $value !== null;
            });
            $mergedData = array_merge($contextData, $dataWithNonNulls);
            
            // Check if user selected a NEW doctor number (different from context)
            // If so, clear the stale date so they can pick a new date for the new doctor
            $previousDoctorNumber = $contextData['doctor_number'] ?? null;
            $newDoctorNumber = $data['doctor_number'] ?? null;
            
            if ($newDoctorNumber && $previousDoctorNumber && intval($newDoctorNumber) !== intval($previousDoctorNumber)) {
                // User selected a different doctor - clear the stale date
                Log::debug('New doctor selected (' . $newDoctorNumber . ' vs ' . $previousDoctorNumber . '), clearing stale date');
                $mergedData['date'] = null;
                unset($mergedData['date']);
                $date = null; // Also clear local variable
            }
            
            Log::debug('Fully merged data: ' . json_encode($mergedData));
            
            $specialization = $mergedData['specialization'] ?? null;
            $date = $mergedData['date'] ?? null;
            $time = $mergedData['time_preference'] ?? null;
            $doctorNumber = $mergedData['doctor_number'] ?? null;
            $selectedDoctorId = $mergedData['selected_doctor_id'] ?? null;
            $doctorName = $mergedData['doctor_name'] ?? null;
            $timeSlotNumber = $mergedData['time_slot_number'] ?? null;
            
            Log::debug('Using merged values - doctorNumber: ' . ($doctorNumber ?? 'null') . ', date: ' . ($date ?? 'null') . ', time: ' . ($time ?? 'null'));

            // Case 1: No doctor selected yet
            if (!$doctorNumber && !$selectedDoctorId && !$doctorName) {
                Log::debug('Case 1: No doctor selected');
                
                if ($specialization) {
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
                        'hi' => "नीचे दिए गए डॉक्टरों में से अपना डॉक्टर चुनें:{$doctorList}\n\nडॉक्टर का नंबर बताएं (1-" . count($doctors) . ")।",
                        default => "Choose a doctor from the list below:{$doctorList}\n\nPlease specify the doctor's number (1-" . count($doctors) . ").",
                        };
                    }
                }
                
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
                        'bn' => "নিচের ডাক্তারদের মধ্যে আপনার পছন্দের ডাক্তার নির্বাচন করুন:{$doctorList}\n\nডাক্তারের নম্বর বলুন (1-" . count($doctors) . ")।",
                        'hi' => "नीचे दिए गए डॉक्टरों में से अपना डॉक्टर चुनें:{$doctorList}\n\nडॉक्टर का नंबर बताएं (1-" . count($doctors) . ")।",
                        default => "Choose a doctor from the list below:{$doctorList}\n\nPlease specify the doctor's number (1-" . count($doctors) . ").",
                    };
                }
                
                return match($language) {
                    'bn' => "দুঃখিত, এই মুহূর্তে কোনো ডাক্তার পাওয়া যাচ্ছে না। অনুগ্রহ করে পরে আবার চেষ্টা করুন।",
                    'hi' => "क्षमा करें, इस समय कोई डॉक्टर उपलब्ध नहीं है। कृपया बाद में पुनः प्रयास करें।",
                    default => "Sorry, no doctors are available at the moment. Please try again later.",
                };
            }
            
            // Case 2: Doctor selected (by number, name, or ID), need date
            if (($doctorNumber || $selectedDoctorId || $doctorName) && !$date) {
                Log::debug('Case 2: Doctor selected, need date. doctorNumber=' . $doctorNumber . ', specialization=' . ($specialization ?? 'null'));
                
                $doctor = null;
                
                if ($doctorNumber) {
                    $doctorQuery = Doctor::query()
                        ->with(['specialization', 'user', 'schedules'])
                        ->orderBy('rating', 'desc')
                        ->orderBy('experience_years', 'desc');
                    
                    if ($specialization) {
                        Log::debug('Case 2: Filtering by specialization: ' . $specialization);
                        $doctorQuery->whereHas('specialization', function ($q) use ($specialization) {
                            $q->where('name', 'LIKE', '%' . $specialization . '%');
                        });
                    } else {
                        Log::debug('Case 2: NO specialization filter applied!');
                    }
                    $doctor = $doctorQuery->skip($doctorNumber - 1)->first();
                    Log::debug('Case 2: Doctor query result: ' . ($doctor ? $doctor->user->name : 'null'));
                } elseif ($doctorName) {
                    $doctor = Doctor::query()
                        ->with(['specialization', 'user', 'schedules'])
                        ->whereHas('user', function ($q) use ($doctorName) {
                            $q->where('name', 'LIKE', '%' . $doctorName . '%');
                        })
                        ->first();
                } elseif ($selectedDoctorId) {
                    $doctor = Doctor::with(['specialization', 'user', 'schedules'])->find($selectedDoctorId);
                }
                
                if (!$doctor) {
                    return match($language) {
                        'bn' => "দুঃখিত, ডাক্তার খুঁজে পাওয়া যায়নি। আবার চেষ্টা করুন।",
                        'hi' => "क्षमा करें, डॉक्टर नहीं मिला। कृपया पुनः प्रयास करें।",
                        default => "Sorry, doctor not found. Please try again.",
                    };
                }
                
                $doctorName = $doctor->user->name;
                
                $availableDays = [];
                if (!empty($doctor->available_days)) {
                    $availableDays = array_map('strtolower', $doctor->available_days);
                } elseif ($doctor->schedules) {
                    foreach ($doctor->schedules as $schedule) {
                        if ($schedule->is_active) {
                            $availableDays[] = strtolower($schedule->day_of_week);
                        }
                    }
                }

                // de-duplicate day list to avoid showing the same day twice
                if (!empty($availableDays)) {
                    // de-duplicate day list to avoid showing the same day twice
                    $availableDays = array_values(array_unique($availableDays));

                    // sort by weekday order for a more natural sequence
                    $weekOrder = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
                    usort($availableDays, function($a, $b) use ($weekOrder) {
                        $posA = array_search($a, $weekOrder);
                        $posB = array_search($b, $weekOrder);
                        return $posA <=> $posB;
                    });
                }
                
                $availableDaysInfo = "";
                if (!empty($availableDays)) {
                    $dayNamesEn = ['monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday', 'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday'];
                    $dayNamesBn = ['monday' => 'সোমবার', 'tuesday' => 'মঙ্গলবার', 'wednesday' => 'বুধবার', 'thursday' => 'বৃহস্পতিবার', 'friday' => 'শুক্রবার', 'saturday' => 'শনিবার', 'sunday' => 'রবিবার'];
                    
                    $dayIndex = 1;
                    foreach ($availableDays as $day) {
                        if ($language === 'bn') {
                            $dayName = $dayNamesBn[$day] ?? ucfirst($day);
                        } else {
                            $dayName = $dayNamesEn[$day] ?? ucfirst($day);
                        }
                        $availableDaysInfo .= "\n" . $dayIndex . ". " . $dayName;
                        $dayIndex++;
                    }
                }
                
                return match($language) {
                    'bn' => "আপনি Dr. {$doctorName}-কে বেছে নিয়েছেন।" . 
                           (!empty($availableDaysInfo) ? "\n\nউপলব্ধ দিন:" . $availableDaysInfo : "") . 
                           "\n\nকোন তারিখ আপনার জন্য সুবিধাজনক? (যেমন: আজকে, কাল, সোমবার অথবা উপরের দিনের নম্বর)",
                    'hi' => "आपने Dr. {$doctorName} को चुना है।" . 
                           (!empty($availableDaysInfo) ? "\n\nउपलब्ध दिन:" . $availableDaysInfo : "") . 
                           "\n\nकौन सी तारीख आपके लिए सुविधाजनक है? (जैसे: आज, कल, सोमवार या ऊपर से दिन का नंबर)",
                    default => "You've selected Dr. {$doctorName}." . 
                               (!empty($availableDaysInfo) ? "\n\nAvailable days:" . $availableDaysInfo : "") . 
                               "\n\nWhat date works best for you? (e.g., today, tomorrow, Monday or the day number above)",
                };
            }
            
            // Case 3: Doctor and date selected, need time
            if (($doctorNumber || $selectedDoctorId || $doctorName) && $date && !$time && !$timeSlotNumber) {
                Log::debug('Case 3: Doctor and date selected, need time. Date: ' . $date);
                
                $doctor = null;
                
                if ($doctorNumber) {
                    $doctorQuery = Doctor::query()
                        ->with(['specialization', 'user', 'schedules'])
                        ->orderBy('rating', 'desc')
                        ->orderBy('experience_years', 'desc');
                    
                    if ($specialization) {
                        $doctorQuery->whereHas('specialization', function ($q) use ($specialization) {
                            $q->where('name', 'LIKE', '%' . $specialization . '%');
                        });
                    }
                    $doctor = $doctorQuery->skip($doctorNumber - 1)->first();
                } elseif ($doctorName) {
                    $doctor = Doctor::query()
                        ->with(['specialization', 'user', 'schedules'])
                        ->whereHas('user', function ($q) use ($doctorName) {
                            $q->where('name', 'LIKE', '%' . $doctorName . '%');
                        })
                        ->first();
                } elseif ($selectedDoctorId) {
                    $doctor = Doctor::with(['specialization', 'user', 'schedules'])->find($selectedDoctorId);
                }
                
                if (!$doctor) {
                    return match($language) {
                        'bn' => "দুঃখিত, ডাক্তার খুঁজে পাওয়া যায়নি। আবার চেষ্টা করুন।",
                        'hi' => "क्षमा करें, डॉक्टर नहीं मिला। कृपया पुनः प्रयास करें।",
                        default => "Sorry, doctor not found. Please try again.",
                    };
                }
                
                $doctorName = $doctor->user->name;
                
                $slots = $doctor->getAvailableTimeSlotsForDate($date);
                
                if (!empty($slots)) {
                    $slotList = "";
                    foreach ($slots as $index => $slot) {
                        // Use formatted_time and extract just the start time (before ' - ')
                        $formatted = $slot['formatted_time'] ?? '';
                        $startTime = explode(' - ', $formatted)[0] ?? '';
                        $slotList .= "\n" . ($index + 1) . ". " . $startTime;
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
            
            // Case 4: Doctor, date, and time slot number selected
            // OR Doctor, date OR time preference with time slot number selected
            if (($doctorNumber || $selectedDoctorId || $doctorName) && 
                ($date || $time) && $timeSlotNumber) {
                Log::debug('Case 4: Doctor, date/time, and time slot selected');
                
                $doctor = null;
                
                if ($doctorNumber) {
                    $doctorQuery = Doctor::query()
                        ->with(['specialization', 'user', 'schedules'])
                        ->orderBy('rating', 'desc')
                        ->orderBy('experience_years', 'desc');
                    
                    if ($specialization) {
                        $doctorQuery->whereHas('specialization', function ($q) use ($specialization) {
                            $q->where('name', 'LIKE', '%' . $specialization . '%');
                        });
                    }
                    $doctor = $doctorQuery->skip($doctorNumber - 1)->first();
                } elseif ($doctorName) {
                    $doctor = Doctor::query()
                        ->with(['specialization', 'user', 'schedules'])
                        ->whereHas('user', function ($q) use ($doctorName) {
                            $q->where('name', 'LIKE', '%' . $doctorName . '%');
                        })
                        ->first();
                } elseif ($selectedDoctorId) {
                    $doctor = Doctor::with(['specialization', 'user', 'schedules'])->find($selectedDoctorId);
                }
                
                if (!$doctor) {
                    return match($language) {
                        'bn' => "দুঃখিত, ডাক্তার খুঁজে পাওয়া যায়নি। আবার চেষ্টা করুন।",
                        'hi' => "क्षमा करें, डॉक्टर नहीं मिला। कृपया पुनः प्रयास करें।",
                        default => "Sorry, doctor not found. Please try again.",
                    };
                }
                
                $doctorName = $doctor->user->name;
                
                $slots = $doctor->getAvailableTimeSlotsForDate($date);
                $slotIndex = $timeSlotNumber - 1;
                
                if (isset($slots[$slotIndex])) {
                    $selectedSlot = $slots[$slotIndex];
                    $selectedTime = $selectedSlot['formatted_time'];
                    
                    $patientName = $mergedData['patient_name'] ?? null;
                    $phone = $mergedData['phone'] ?? null;
                    
                    Log::debug('Case 4 - patient_name from mergedData: ' . ($patientName ?? 'NULL'));
                    Log::debug('Case 4 - phone from mergedData: ' . ($phone ?? 'NULL'));
                    
                    // Ensure we have actual values, not empty strings
                    $patientName = !empty(trim($patientName)) ? trim($patientName) : null;
                    $phone = !empty(trim($phone)) ? trim($phone) : null;
                    
                    Log::debug('Case 4 - after trim, patient_name: ' . ($patientName ?? 'NULL') . ', phone: ' . ($phone ?? 'NULL'));
                    
                    if ($patientName && $phone) {
                        // Create the appointment
                        Log::debug('Case 4: Both name and phone available, confirming appointment');
                        return match($language) {
                            'bn' => "✅ *অ্যাপয়েন্টমেন্ট নিশ্চিত করা হয়েছে!*\n\n" .
                                "👨‍⚕️ ডাক্তার: {$doctorName}\n" .
                                "📅 তারিখ: {$date}\n" .
                                "⏰ সময়: {$selectedTime}\n" .
                                "👤 রোগী: {$patientName}\n" .
                                "📱 ফোন: {$phone}\n\n" .
                                "ধন্যবাদ! আমরা আপনার সাথে যোগাযোগ করব।",
                            'hi' => "✅ *अपॉइंटमेंट पुष्टि की गई!*\n\n" .
                                "👨‍⚕️ डॉक्टर: {$doctorName}\n" .
                                "📅 तारीख: {$date}\n" .
                                "⏰ समय: {$selectedTime}\n" .
                                "👤 रोगी: {$patientName}\n" .
                                "📱 फोन: {$phone}\n\n" .
                                "धन्यवाद! हम आपसे संपर्क करेंगे।",
                            default => "✅ *Appointment Confirmed!*\n\n" .
                                "👨‍⚕️ Doctor: {$doctorName}\n" .
                                "📅 Date: {$date}\n" .
                                "⏰ Time: {$selectedTime}\n" .
                                "👤 Patient: {$patientName}\n" .
                                "📱 Phone: {$phone}\n\n" .
                                "Thank you! We will contact you shortly.",
                        };
                    }
                    
                    return match($language) {
                        'bn' => "চমৎকার! আমি আপনার জন্য Dr. {$doctorName}-এর সাথে {$date} তারিখে {$selectedTime} সময়ে অ্যাপয়েন্টমেন্ট বুক করছি।\n\nআপনার নাম এবং ফোন নম্বর কী?",
                        'hi' => "बढ़िया! मैं आपके लिए Dr. {$doctorName} के साथ {$date} को {$selectedTime} अपॉइंटमेंट बुक कर रहा हूं।\n\nआपका नाम और फोन नंबर क्या है?",
                        default => "Great! I'm booking an appointment with Dr. {$doctorName} on {$date} at {$selectedTime}.\n\nWhat is your name and phone number?",
                    };
                } else {
                    return match($language) {
                        'bn' => "অবৈধ সময় নম্বর। অনুগ্রহ করে আবার চেষ্টা করুন।",
                        'hi' => "अमान्य समय संख्या। कृपया पुनः प्रयास करें।",
                        default => "Invalid time number. Please try again.",
                    };
                }
            }
            
            // Case 5: Doctor, date, and time preference selected, need patient info
            if (($doctorNumber || $selectedDoctorId || $doctorName) && $date && $time && !$timeSlotNumber) {
                Log::debug('Case 5: Doctor, date, and time selected, need patient info');
                
                $doctor = null;
                
                if ($doctorNumber) {
                    $doctorQuery = Doctor::query()
                        ->with(['specialization', 'user'])
                        ->orderBy('rating', 'desc')
                        ->orderBy('experience_years', 'desc');
                    
                    if ($specialization) {
                        $doctorQuery->whereHas('specialization', function ($q) use ($specialization) {
                            $q->where('name', 'LIKE', '%' . $specialization . '%');
                        });
                    }
                    $doctor = $doctorQuery->skip($doctorNumber - 1)->first();
                } elseif ($doctorName) {
                    $doctor = Doctor::query()
                        ->with(['specialization', 'user'])
                        ->whereHas('user', function ($q) use ($doctorName) {
                            $q->where('name', 'LIKE', '%' . $doctorName . '%');
                        })
                        ->first();
                } elseif ($selectedDoctorId) {
                    $doctor = Doctor::with(['specialization', 'user'])->find($selectedDoctorId);
                }
                
                if ($doctor) {
                    $doctorName = $doctor->user->name;
                }
                
                $patientName = $mergedData['patient_name'] ?? null;
                $phone = $mergedData['phone'] ?? null;
                
                // Ensure we have actual values, not empty strings
                $patientName = !empty(trim($patientName)) ? trim($patientName) : null;
                $phone = !empty(trim($phone)) ? trim($phone) : null;
                
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
                
                return match($language) {
                    'bn' => "চমৎকার! আমি আপনার জন্য Dr. {$doctorName}-এর সাথে {$date} তারিখে {$time} সময়ে অ্যাপয়েন্টমেন্ট বুক করছি।\n\nআপনার নাম এবং ফোন নম্বর কী?",
                    'hi' => "बढ़िया! मैं आपके लिए Dr. {$doctorName} के साथ {$date} को {$time} अपॉइंटमेंट बुक कर रहा हूं।\n\nआपका नाम और फोन नंबर क्या है?",
                    default => "Great! I'm booking an appointment with Dr. {$doctorName} on {$date} at {$time}.\n\nWhat is your name and phone number?",
                };
            }
            
            return match($language) {
                'bn' => "আমি আপনাকে ডাক্তারের অ্যাপয়েন্টমেন্ট বুক করতে সাহায্য করতে পারি। আপনি কোন ধরনের ডাক্তার দেখাতে চান? (যেমন: হৃদরোগ বিশেষজ্ঞ, ত্বকের ডাক্তার, চোখের ডাক্তার/চক্ষু বিশেষজ্ঞ)",
                'hi' => "मैं आपकी डॉक्टर अपॉइंटमेंट बुक करने में मदद कर सकता हूं। आप किस तरह के डॉक्टर से मिलना चाहते हैं? (जैसे: हृदय विशेषज्ञ, त्वचा विशेषज्ञ, आंखों के डॉक्टर/ऑफ्थैल्मोलॉजिस्ट)",
                default => "I can help you book a doctor appointment. What type of doctor would you like to see? (e.g., cardiologist, dermatologist, eye doctor/ophthalmologist)",
            };
            
        } catch (Exception $e) {
            Log::error('buildBookingResponse error: ' . $e->getMessage());
            return match($language) {
                'bn' => "আমি আপনাকে ডাক্তারের অ্যাপয়েন্টমেন্ট বুক করতে সাহায্য করতে পারি। আপনি কোন ধরনের ডাক্তার দেখাতে চান? (যেমন: হৃদরোগ বিশেষজ্ঞ, ত্বকের ডাক্তার, চোখের ডাক্তার/চক্ষু বিশেষজ্ঞ)",
                'hi' => "मैं आपकी डॉक्टर अपॉइंटमेंट बुक करने में मदद कर सकता हूं। आप किस तरह के डॉक्टर से मिलना चाहते हैं? (जैसे: हृदय विशेषज्ञ, त्वचा विशेषज्ञ, आंखों के डॉक्टर/ऑफ्थैल्मोलॉजिस्ट)",
                default => "I can help you book a doctor appointment. What type of doctor would you like to see? (e.g., cardiologist, dermatologist, eye doctor/ophthalmologist)",
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
            
            $doctors = Doctor::query()
                ->with(['specialization'])
                // ensure stable ordering that matches the doctor-number logic used
                // in booking flows (rating desc, experience desc)
                ->orderBy('rating', 'desc')
                ->orderBy('experience_years', 'desc')
                ->when($specialization, function ($query) use ($specialization) {
                    $query->whereHas('specialization', function ($q) use ($specialization) {
                        $q->where('name', 'LIKE', '%' . $specialization . '%');
                    });
                })
                ->limit(10)
                ->get();
            // remember the order of IDs so the user’s numeric reply can be mapped
            // back to an actual doctor record later in processLocally
            $this->lastDoctorIds = $doctors->pluck('id')->toArray();
        
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
        // Check if a specific doctor is requested
        $specificDoctorId = $data['selected_doctor_id'] ?? null;
        $specificDoctorName = $data['doctor_name'] ?? null;
        
        // Get all doctors with their specializations
        $doctorsQuery = Doctor::query()->with(['specialization', 'user']);
        
        // If specific doctor is requested, filter to that doctor
        if ($specificDoctorId) {
            $doctorsQuery->where('id', $specificDoctorId);
        } elseif ($specificDoctorName) {
            $doctorsQuery->whereHas('user', function ($q) use ($specificDoctorName) {
                $q->where('name', 'LIKE', '%' . $specificDoctorName . '%');
            });
        }
        
        $doctors = $doctorsQuery->get();
        
        if ($doctors->isEmpty()) {
            return match($language) {
                'bn' => 'দুঃখিত, কোনো ডাক্তার পাওয়া যায়নি।',
                'hi' => 'माफ करें, कोई डॉक्टर नहीं मिला।',
                default => 'Sorry, no doctors found.',
            };
        }
        
        // Determine which date to show availability for
        $targetDate = $data['date'] ?? $data['appointment_date'] ?? date('Y-m-d');
        $formattedDate = date('F j, Y', strtotime($targetDate));
        $dayOfWeek = date('l', strtotime($targetDate));
        
        $response = match($language) {
            'bn' => "{$formattedDate} ({$dayOfWeek})-উপলব্ধ ডাক্তার এবং সময়:\n\n",
            'hi' => "{$formattedDate} ({$dayOfWeek}) को उपलब्ध डॉक्टर और समय:\n\n",
            default => "Available doctors and times for {$formattedDate} ({$dayOfWeek}):\n\n",
        };
        
        $hasAnySlots = false;
        $doctorNumber = 1;
        
        foreach ($doctors as $doctor) {
            $doctorName = $doctor->user->name ?? 'Unknown';
            $specialization = $doctor->specialization->name ?? 'General';
            $slots = $doctor->getAvailableTimeSlotsForDate($targetDate);
            
            if (!empty($slots)) {
                $hasAnySlots = true;
                $slotList = "";
                foreach ($slots as $index => $slot) {
                    $slotNumber = $index + 1;
                    // Use formatted_time and extract just the start time (before ' - ')
                    $formatted = $slot['formatted_time'] ?? '';
                    $startTime = explode(' - ', $formatted)[0] ?? '';
                    $slotList .= "  {$slotNumber}. {$startTime}\n";
                }
                
                $response .= match($language) {
                    'bn' => "{$doctorNumber}. Dr. {$doctorName} ({$specialization})\n   উপলব্ধ সময়:\n{$slotList}\n",
                    'hi' => "{$doctorNumber}. Dr. {$doctorName} ({$specialization})\n   उपलब्ध समय:\n{$slotList}\n",
                    default => "{$doctorNumber}. Dr. {$doctorName} ({$specialization})\n   Available times:\n{$slotList}\n",
                };
                $doctorNumber++;
            }
        }
        
        if (!$hasAnySlots) {
            $doctorInfo = $specificDoctorName ? " Dr. {$specificDoctorName}" : '';
            return match($language) {
                'bn' => "{$formattedDate} তারিখে{$doctorInfo}-এর কোনো সময় উপলব্ধ নেই। অন্য তারিখ চেষ্টা করুন।",
                'hi' => "{$formattedDate} को{$doctorInfo} के लिए कोई समय उपलब्ध नहीं है। कृपया कोई और तारीख चुनें।",
                default => "No slots available for{$doctorInfo} on {$formattedDate}. Please try a different date.",
            };
        }
        
        // Show appropriate next step based on whether specific doctor was requested
        if ($specificDoctorId || $specificDoctorName) {
            // Count total slots for the response
            $totalSlots = 0;
            foreach ($doctors as $doctor) {
                $slots = $doctor->getAvailableTimeSlotsForDate($targetDate);
                $totalSlots += count($slots);
            }
            $response .= match($language) {
                'bn' => "\nঅ্যাপয়েন্টমেন্ট বুক করতে, সময়ের নম্বর বলুন (1-{$totalSlots})।",
                'hi' => "\nअपॉइंटमेंट बुक करने के लिए, समय का नंबर बताएं (1-{$totalSlots})।",
                default => "\nTo book an appointment, please specify the time number (1-{$totalSlots}).",
            };
        } else {
            $response .= match($language) {
                'bn' => "\nঅ্যাপয়েন্টমেন্ট বুক করতে, ডাক্তারের নম্বর বলুন।",
                'hi' => "\nअपॉइंटमेंट बुक करने के लिए, डॉक्टर का नंबर बताएं।",
                default => "\nTo book an appointment, please specify the doctor number.",
            };
        }
        
        return $response;
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
            'hi' => "मैं आपका मेडिकल अपॉइंटमेंट असिस्टेंट हूं।{$doctorList}\n\nमैं आपकी अपॉइंटमेंट बुक, रद्द या पुनर्निर्धारित करने में मदद कर सकता हूं।\n\nआप क्या चाहते हैं?",
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
     * Build symptoms response
     */
    protected function buildSymptomsResponse(array $data, string $language): string
    {
        $symptoms = $data['symptoms'] ?? [];
        $specialization = $data['specialization'] ?? null;

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
            'tooth' => 'Dentist',
            'teeth' => 'Dentist',
            'toothache' => 'Dentist',
            'gum' => 'Dentist',
            'দাঁত' => 'Dentist',
            'মাড়ি' => 'Dentist',
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
        
        $text = '';
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $text = $result['candidates'][0]['content']['parts'][0]['text'];
        }

        return ['generated_text' => $text];
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
        
        // Note: Doctor list is no longer shown in initial greeting
        // Users can ask to see doctors or book an appointment to see the list
        
        return match($language) {
            'bn' => "{$timeGreeting}! 🏥\n\nআমি আপনার মেডিকেল অ্যাপয়েন্টমেন্ট অ্যাসিস্ট্যান্ট।\n\nআমি আপনাকে অ্যাপয়েন্টমেন্ট বুক করতে, বাতিল করতে বা পুনর্নির্ধারণ করতে সাহায্য করতে পারি।\n\nআপনাকে কীভাবে সাহায্য করতে পারি?",
            'hi' => "{$timeGreeting}! 🏥\n\nमैं आपका मेडिकल अपॉइंटमेंट असिस्टेंट हूं।\n\nमैं आपकी अपॉइंटमेंट बुक, रद्द या पुनर्निर्धारित करने में मदद कर सकता हूं।\n\nमैं आपकी कैसे मदद कर सकता हूं?",
            default => "{$timeGreeting}! 🏥\n\nI'm your Medical Appointment Assistant.\n\nI can help you book, cancel, or reschedule appointments.\n\nHow can I help you today?",
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
