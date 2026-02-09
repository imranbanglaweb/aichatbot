<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\Specialization;
use App\Models\User;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DoctorSyncService
{
    protected Client $httpClient;
    protected string $baseUrl;
    protected ?string $apiToken;

    public function __construct()
    {
        $this->baseUrl = config('services.medicare_api.base_url', 'http://192.168.48.208:9091');
        $this->apiToken = config('services.medicare_api.token');
        
        $this->httpClient = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Sync all doctors from external Medicare API
     */
    public function syncAllDoctors(): array
    {
        $results = [
            'synced' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => [],
        ];

        try {
            $response = $this->httpClient->get('/api/doctors');
            
            if ($response->getStatusCode() === 200) {
                $doctors = json_decode($response->getBody()->getContents(), true);
                
                foreach ($doctors as $doctorData) {
                    try {
                        $result = $this->syncDoctor($doctorData);
                        $results['synced']++;
                        if ($result === 'created') {
                            $results['created']++;
                        } else {
                            $results['updated']++;
                        }
                    } catch (Exception $e) {
                        $results['errors'][] = [
                            'doctor' => $doctorData['name'] ?? 'Unknown',
                            'error' => $e->getMessage(),
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            Log::error('Medicare API Sync Error: ' . $e->getMessage());
            $results['errors'][] = [
                'error' => 'Failed to fetch doctors from API: ' . $e->getMessage(),
            ];
        }

        return $results;
    }

    /**
     * Sync a single doctor from external data
     */
    public function syncDoctor(array $externalData): string
    {
        return DB::transaction(function () use ($externalData) {
            // Find or create user
            $user = User::updateOrCreate(
                ['email' => $externalData['email'] ?? 'doctor-' . $externalData['id'] . '@medicare.local'],
                [
                    'name' => $externalData['name'] ?? 'Unknown Doctor',
                    'phone' => $externalData['phone'] ?? null,
                    'password' => bcrypt('medicare_' . ($externalData['id'] ?? time())),
                ]
            );

            // Find or create specialization
            $specialization = null;
            if (!empty($externalData['specialization'])) {
                $specialization = Specialization::updateOrCreate(
                    ['name' => $externalData['specialization']],
                    [
                        'slug' => strtolower(str_replace(' ', '-', $externalData['specialization'])),
                        'description' => $externalData['specialization_description'] ?? null,
                    ]
                );
            }

            // Prepare doctor data
            $doctorData = [
                'user_id' => $user->id,
                'specialization_id' => $specialization?->id,
                'license_number' => $externalData['license_number'] ?? null,
                'qualification' => $externalData['qualification'] ?? null,
                'experience_years' => $externalData['experience_years'] ?? 0,
                'bio' => $externalData['bio'] ?? null,
                'consultation_fee' => $externalData['consultation_fee'] ?? 0,
                'hospital_clinic' => $externalData['hospital'] ?? $externalData['hospital_clinic'] ?? null,
                'address' => $externalData['address'] ?? null,
                'city' => $externalData['city'] ?? null,
                'rating' => $externalData['rating'] ?? 0,
                'total_reviews' => $externalData['total_reviews'] ?? 0,
                'languages' => $externalData['languages'] ?? ['en'],
                'available_days' => $externalData['available_days'] ?? ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'start_time' => $externalData['start_time'] ?? '09:00:00',
                'end_time' => $externalData['end_time'] ?? '17:00:00',
                'slot_duration' => $externalData['slot_duration'] ?? 30,
                'is_available' => $externalData['is_available'] ?? true,
                'is_verified' => $externalData['is_verified'] ?? false,
            ];

            // Check if doctor exists by external_id or license_number
            $doctor = null;
            if (!empty($externalData['external_id'])) {
                $doctor = Doctor::where('external_id', $externalData['external_id'])->first();
            } elseif (!empty($externalData['license_number'])) {
                $doctor = Doctor::where('license_number', $externalData['license_number'])->first();
            }

            if ($doctor) {
                $doctor->update($doctorData);
                return 'updated';
            } else {
                $doctorData['external_id'] = $externalData['id'] ?? null;
                Doctor::create($doctorData);
                return 'created';
            }
        });
    }

    /**
     * Sync doctors from a specific endpoint/path
     */
    public function syncFromEndpoint(string $endpoint): array
    {
        $results = [
            'synced' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => [],
        ];

        try {
            $response = $this->httpClient->get($endpoint);
            
            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getBody()->getContents(), true);
                
                // Handle different API response formats
                $doctors = $data['data'] ?? $data['doctors'] ?? $data['results'] ?? $data;
                
                if (!is_array($doctors)) {
                    $doctors = [$data];
                }

                foreach ($doctors as $doctorData) {
                    try {
                        $result = $this->syncDoctor($doctorData);
                        $results['synced']++;
                        if ($result === 'created') {
                            $results['created']++;
                        } else {
                            $results['updated']++;
                        }
                    } catch (Exception $e) {
                        $results['errors'][] = [
                            'doctor' => $doctorData['name'] ?? 'Unknown',
                            'error' => $e->getMessage(),
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            Log::error('Medicare API Endpoint Sync Error: ' . $e->getMessage());
            $results['errors'][] = [
                'error' => 'Failed to sync from endpoint: ' . $e->getMessage(),
            ];
        }

        return $results;
    }

    /**
     * Test connection to Medicare API
     */
    public function testConnection(): array
    {
        try {
            $response = $this->httpClient->get('/api/health');
            
            return [
                'success' => true,
                'status_code' => $response->getStatusCode(),
                'message' => 'Connection successful',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get available specializations from external API
     */
    public function syncSpecializations(): array
    {
        $results = [
            'synced' => 0,
            'errors' => [],
        ];

        try {
            $response = $this->httpClient->get('/api/specializations');
            
            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getBody()->getContents(), true);
                $specializations = $data['data'] ?? $data['specializations'] ?? $data;

                foreach ($specializations as $specData) {
                    try {
                        Specialization::updateOrCreate(
                            ['name' => $specData['name']],
                            [
                                'slug' => $specData['slug'] ?? strtolower(str_replace(' ', '-', $specData['name'])),
                                'description' => $specData['description'] ?? null,
                                'icon' => $specData['icon'] ?? null,
                                'common_symptoms' => $specData['common_symptoms'] ?? null,
                            ]
                        );
                        $results['synced']++;
                    } catch (Exception $e) {
                        $results['errors'][] = [
                            'specialization' => $specData['name'] ?? 'Unknown',
                            'error' => $e->getMessage(),
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            Log::error('Medicare Specializations Sync Error: ' . $e->getMessage());
            $results['errors'][] = [
                'error' => 'Failed to fetch specializations: ' . $e->getMessage(),
            ];
        }

        return $results;
    }
}
