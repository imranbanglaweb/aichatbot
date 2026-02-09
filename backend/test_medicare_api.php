<?php

/**
 * Medicare API Tester
 * Use this script to test the Medicare API endpoint
 * 
 * How to find the API endpoint:
 * 1. Open Chrome Developer Tools (F12) in the Medicare web client
 * 2. Go to the Network tab
 * 3. Navigate to the doctor list section
 * 4. Look for API calls (usually /api/* or /v1/*)
 * 5. Copy the endpoint URL and response
 */

$baseUrl = 'http://192.168.48.208:9091';

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  Medicare API Tester                                        ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// Common doctor-related API endpoints
$endpoints = [
    '/api/doctors',
    '/api/v1/doctors',
    '/api/doctor/list',
    '/api/appointments/doctors',
    '/api/v1/appointments/doctors',
    '/api/onlineappointment/doctors',
    '/api/v1/onlineappointment/doctors',
    '/medicare-web-client-v2/api/doctors',
    '/medicare-web-client-v2/api/v1/doctors',
    '/api/getDoctors',
    '/api/getDoctorsList',
    '/api/getDoctorList',
    '/api/all/doctors',
    '/api/public/doctors',
    '/doctors/list',
];

echo "Testing common endpoints:\n";
echo str_repeat('-', 60) . "\n\n";

$foundDoctors = false;

foreach ($endpoints as $endpoint) {
    $url = $baseUrl . $endpoint;
    echo "Testing: $endpoint\n";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "  ❌ Error: $error\n\n";
        continue;
    }
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        
        // Check if response contains doctor data
        $hasDoctors = false;
        $doctorCount = 0;
        
        if (is_array($data)) {
            if (isset($data['data']) && is_array($data['data'])) {
                $hasDoctors = true;
                $doctorCount = count($data['data']);
            } elseif (isset($data['doctors']) && is_array($data['doctors'])) {
                $hasDoctors = true;
                $doctorCount = count($data['doctors']);
            } elseif (isset($data['results']) && is_array($data['results'])) {
                $hasDoctors = true;
                $doctorCount = count($data['results']);
            } elseif (!empty($data)) {
                // Check if it's a single doctor object
                if (isset($data['name']) || isset($data['doctor_name']) || isset($data['full_name'])) {
                    $hasDoctors = true;
                    $doctorCount = 1;
                }
            }
        }
        
        if ($hasDoctors) {
            echo "  ✅ SUCCESS! Found $doctorCount doctors\n";
            echo "  URL: $url\n\n";
            
            // Save the response
            file_put_contents(__DIR__ . '/medicare_doctors_response.json', $response);
            echo "  💾 Response saved to: medicare_doctors_response.json\n\n";
            
            $foundDoctors = true;
            
            // Print sample data
            echo "Sample data:\n";
            $this->printSampleDoctors($data);
            break;
        } else {
            echo "  ⚠️  Response received but no doctors found\n";
            echo "  HTTP Code: $httpCode\n";
            echo "  Response size: " . strlen($response) . " bytes\n\n";
        }
    } else {
        echo "  ❌ HTTP $httpCode\n\n";
    }
}

if (!$foundDoctors) {
    echo "\n╔════════════════════════════════════════════════════════════╗\n";
    echo "║  No doctors found via common endpoints                    ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n\n";
    
    echo "Instructions to find the correct API endpoint:\n";
    echo "═══════════════════════════════════════════════════════\n\n";
    
    echo "1. Open the Medicare web client in Chrome:\n";
    echo "   http://192.168.48.208:9091/medicare-web-client-v2/#/onlineappointment/appointment/home\n\n";
    
    echo "2. Open Developer Tools (press F12 or Ctrl+Shift+I)\n\n";
    
    echo "3. Go to the 'Network' tab\n\n";
    
    echo "4. In the 'Filter' box, type 'doctor' to find doctor-related API calls\n\n";
    
    echo "5. Look for requests with 'api' in the name\n\n";
    
    echo "6. Click on a request to see:\n";
    echo "   - Request URL (the endpoint we need)\n";
    echo "   - Response (the doctor data)\n\n";
    
    echo "7. Copy the endpoint URL and run:\n";
    echo "   php test_medicare_api.php --endpoint='/your/endpoint'\n\n";
    
    echo "Or provide the endpoint URL:\n";
    echo "═══════════════════════════════════════════════════════\n";
    
    // Check if endpoint was provided as argument
    if (isset($argv[1]) && isset($argv[2]) && $argv[1] === '--endpoint') {
        $endpoint = $argv[2];
        echo "Testing custom endpoint: $endpoint\n\n";
        
        $url = $baseUrl . $endpoint;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            echo "✅ SUCCESS! Endpoint works.\n\n";
            echo "Response:\n";
            echo $response . "\n";
            
            // Save response
            file_put_contents(__DIR__ . '/medicare_doctors_response.json', $response);
            echo "\n💾 Response saved to: medicare_doctors_response.json\n";
        } else {
            echo "❌ HTTP $httpCode - Endpoint may not be correct\n";
        }
    }
}

function printSampleDoctors(array $data): void
{
    $doctors = $data['data'] ?? $data['doctors'] ?? $data['results'] ?? $data;
    
    if (!is_array($doctors) || empty($doctors)) {
        echo "  No doctors to display\n";
        return;
    }
    
    $count = min(3, count($doctors));
    
    for ($i = 0; $i < $count; $i++) {
        $doctor = $doctors[$i];
        echo "\n  Doctor " . ($i + 1) . ":\n";
        echo "  - Name: " . ($doctor['name'] ?? $doctor['doctor_name'] ?? $doctor['full_name'] ?? 'N/A') . "\n";
        echo "  - Specialization: " . ($doctor['specialization'] ?? $doctor['department'] ?? $doctor['specialty'] ?? 'N/A') . "\n";
        if (isset($doctor['qualification']) || isset($doctor['degrees'])) {
            echo "  - Qualification: " . ($doctor['qualification'] ?? $doctor['degrees'] ?? 'N/A') . "\n";
        }
        if (isset($doctor['hospital']) || isset($doctor['hospital_name'])) {
            echo "  - Hospital: " . ($doctor['hospital'] ?? $doctor['hospital_name'] ?? 'N/A') . "\n";
        }
    }
    
    echo "\n  Total doctors: " . count($doctors) . "\n";
}
