<?php

/**
 * Medicare Doctor Fetcher Script
 * Run this script to fetch doctor information from Medicare web client
 * 
 * Usage: php fetch_medicare_doctors.php
 * 
 * Requirements:
 * - PHP with cURL extension
 * - Network access to 192.168.48.208:9091
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Doctor;
use App\Models\User;
use App\Models\Specialization;
use Illuminate\Database\Capsule\Manager as Capsule;

// Initialize Laravel Eloquent
$capsule = new Capsule;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => getenv('DB_PORT') ?: '3306',
    'database' => getenv('DB_DATABASE') ?: 'medical_chatbot',
    'username' => getenv('DB_USERNAME') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

class MedicareDoctorFetcher
{
    private string $baseUrl;
    private ?string $apiToken;
    
    public function __construct()
    {
        $this->baseUrl = getenv('MEDICARE_API_BASE_URL') ?: 'http://192.168.48.208:9091';
        $this->apiToken = getenv('MEDICARE_API_TOKEN');
    }
    
    /**
     * Fetch doctors from Medicare web client
     */
    public function fetchDoctors(): array
    {
        $doctors = [];
        
        echo "Attempting to fetch doctors from: {$this->baseUrl}\n\n";
        
        // Try common API endpoints
        $endpoints = [
            '/api/doctors',
            '/api/v1/doctors',
            '/api/doctor/list',
            '/doctors',
            '/api/v1/appointments/doctors',
        ];
        
        foreach ($endpoints as $endpoint) {
            echo "Trying: {$endpoint}...\n";
            $result = $this->fetchFromEndpoint($endpoint);
            if (!empty($result)) {
                echo "Success! Found data at: {$endpoint}\n";
                return $this->parseDoctors($result);
            }
        }
        
        // Try fetching the main page to analyze structure
        echo "\nTrying to fetch main page...\n";
        $html = $this->fetchPage('/medicare-web-client-v2/');
        if ($html) {
            echo "Main page fetched. Analyzing structure...\n";
            $doctors = $this->extractFromHtml($html);
            if (!empty($doctors)) {
                return $doctors;
            }
        }
        
        echo "Could not fetch doctors automatically.\n";
        echo "Please check the Medicare web client API structure.\n";
        
        return [];
    }
    
    /**
     * Fetch from API endpoint
     */
    private function fetchFromEndpoint(string $endpoint): ?array
    {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                ...($this->apiToken ? ["Authorization: Bearer {$this->apiToken}"] : []),
            ],
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            echo "  Error: {$error}\n";
            return null;
        }
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }
        }
        
        return null;
    }
    
    /**
     * Fetch HTML page
     */
    private function fetchPage(string $path): ?string
    {
        $url = $this->baseUrl . $path;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error || $httpCode !== 200) {
            return null;
        }
        
        return $response;
    }
    
    /**
     * Parse doctors from API response
     */
    private function parseDoctors(array $data): array
    {
        $doctors = [];
        
        // Handle different API response formats
        $items = $data['data'] ?? $data['doctors'] ?? $data['results'] ?? $data;
        
        if (!is_array($items)) {
            $items = [$data];
        }
        
        foreach ($items as $item) {
            $doctors[] = [
                'name' => $item['name'] ?? $item['doctor_name'] ?? $item['full_name'] ?? 'Unknown',
                'specialization' => $item['specialization'] ?? $item['department'] ?? $item['specialty'] ?? 'General',
                'qualification' => $item['qualification'] ?? $item['degrees'] ?? null,
                'experience' => $item['experience'] ?? $item['experience_years'] ?? 0,
                'hospital' => $item['hospital'] ?? $item['hospital_name'] ?? $item['clinic'] ?? null,
                'fee' => $item['fee'] ?? $item['consultation_fee'] ?? 0,
                'rating' => $item['rating'] ?? 0,
                'email' => $item['email'] ?? null,
                'phone' => $item['phone'] ?? $item['mobile'] ?? null,
            ];
        }
        
        return $doctors;
    }
    
    /**
     * Extract doctors from HTML page
     */
    private function extractFromHtml(string $html): array
    {
        $doctors = [];
        
        // Look for JSON data embedded in the page
        if (preg_match_all('/"doctor[s]?"\s*:\s*(\[.*?\])/s', $html, $matches)) {
            foreach ($matches[1] as $match) {
                $data = json_decode($match, true);
                if (is_array($data)) {
                    $doctors = array_merge($doctors, $this->parseDoctors($data));
                }
            }
        }
        
        return $doctors;
    }
    
    /**
     * Save doctors to database
     */
    public function saveDoctors(array $doctors): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        
        foreach ($doctors as $doctorData) {
            // Find or create specialization
            $specialization = null;
            if (!empty($doctorData['specialization'])) {
                $specialization = Specialization::updateOrCreate(
                    ['name' => $doctorData['specialization']],
                    ['slug' => strtolower(str_replace(' ', '-', $doctorData['specialization']))]
                );
            }
            
            // Create or update user
            $email = $doctorData['email'] ?? 'doctor-' . strtolower(str_replace(' ', '-', $doctorData['name'])) . '@medicare.local';
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $doctorData['name'],
                    'phone' => $doctorData['phone'] ?? null,
                ]
            );
            
            // Check if doctor exists
            $doctor = Doctor::where('user_id', $user->id)->first();
            
            if ($doctor) {
                $doctor->update([
                    'specialization_id' => $specialization?->id,
                    'qualification' => $doctorData['qualification'],
                    'experience_years' => $doctorData['experience'] ?? 0,
                    'hospital_clinic' => $doctorData['hospital'],
                    'consultation_fee' => $doctorData['fee'] ?? 0,
                    'rating' => $doctorData['rating'] ?? 0,
                ]);
                $stats['updated']++;
                echo "Updated: Dr. {$doctorData['name']}\n";
            } else {
                Doctor::create([
                    'user_id' => $user->id,
                    'specialization_id' => $specialization?->id,
                    'qualification' => $doctorData['qualification'],
                    'experience_years' => $doctorData['experience'] ?? 0,
                    'hospital_clinic' => $doctorData['hospital'],
                    'consultation_fee' => $doctorData['fee'] ?? 0,
                    'rating' => $doctorData['rating'] ?? 0,
                    'is_available' => true,
                    'is_verified' => true,
                ]);
                $stats['created']++;
                echo "Created: Dr. {$doctorData['name']}\n";
            }
        }
        
        return $stats;
    }
    
    /**
     * Print doctors to console
     */
    public function printDoctors(array $doctors): void
    {
        if (empty($doctors)) {
            echo "No doctors found.\n";
            return;
        }
        
        echo "\n" . str_repeat('=', 70) . "\n";
        echo "DOCTORS FROM MEDICARE WEB CLIENT\n";
        echo str_repeat('=', 70) . "\n\n";
        
        foreach ($doctors as $index => $doc) {
            echo ($index + 1) . ". " . str_repeat('-', 50) . "\n";
            echo "   Name: {$doc['name']}\n";
            echo "   Specialization: {$doc['specialization']}\n";
            if ($doc['qualification']) {
                echo "   Qualification: {$doc['qualification']}\n";
            }
            if ($doc['experience']) {
                echo "   Experience: {$doc['experience']} years\n";
            }
            if ($doc['hospital']) {
                echo "   Hospital: {$doc['hospital']}\n";
            }
            if ($doc['fee']) {
                echo "   Fee: {$doc['fee']}\n";
            }
            if ($doc['rating']) {
                echo "   Rating: {$doc['rating']}\n";
            }
            echo "\n";
        }
        
        echo str_repeat('=', 70) . "\n";
        echo "Total: " . count($doctors) . " doctors\n";
        echo str_repeat('=', 70) . "\n";
    }
}

// Run the fetcher
echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  Medicare Doctor Fetcher                                     ║\n";
echo "║  Fetching doctors from: http://192.168.48.208:9091          ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

$fetcher = new MedicareDoctorFetcher();
$doctors = $fetcher->fetchDoctors();

if (!empty($doctors)) {
    $fetcher->printDoctors($doctors);
    
    echo "\nDo you want to save these doctors to the database? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    
    if (strtolower($line) === 'y') {
        echo "\nSaving doctors to database...\n";
        $stats = $fetcher->saveDoctors($doctors);
        echo "\nDone! Created: {$stats['created']}, Updated: {$stats['updated']}\n";
    }
} else {
    echo "\nNo doctors found to import.\n";
    echo "\nManual Import Instructions:\n";
    echo "==========================\n";
    echo "1. Access the Medicare web client at:\n";
    echo "   http://192.168.48.208:9091/medicare-web-client-v2/#/onlineappointment/appointment/home\n";
    echo "\n2. Find the doctor information you want to add\n";
    echo "\n3. Use the API endpoint to sync:\n";
    echo "   POST http://localhost/api/doctors/sync\n";
    echo "\n4. Or manually add doctors via:\n";
    echo "   POST http://localhost/api/doctors/sync/endpoint\n";
    echo "   With body: {\"endpoint\": \"/api/actual-doctors-endpoint\"}\n";
}
