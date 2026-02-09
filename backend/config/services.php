<?php

return [
    // Hugging Face Configuration (AI)
    'huggingface' => [
        'api_key' => env('HUGGINGFACE_API_KEY'),
        'model' => env('HUGGINGFACE_MODEL', 'meta-llama/Meta-Llama-3-8B-Instruct'),
    ],

    // OpenAI Configuration (Fallback)
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORGANIZATION'),
        'model' => env('OPENAI_MODEL', 'gpt-4o'),
    ],

    // Google Cloud Configuration (Text-to-Speech)
    'google' => [
        'api_key' => env('GOOGLE_API_KEY'),
        'project_id' => env('GOOGLE_PROJECT_ID'),
    ],

    // Twilio Configuration (SMS/WhatsApp)
    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'phone' => env('TWILIO_PHONE'),
        'whatsapp_phone' => env('TWILIO_WHATSAPP_PHONE'),
    ],

    // Mail Configuration
    'mail' => [
        'host' => env('MAIL_HOST'),
        'port' => env('MAIL_PORT'),
        'username' => env('MAIL_USERNAME'),
        'password' => env('MAIL_PASSWORD'),
        'encryption' => env('MAIL_ENCRYPTION'),
        'from_address' => env('MAIL_FROM_ADDRESS'),
        'from_name' => env('MAIL_FROM_NAME'),
    ],

    // Medicare Web Client API Configuration
    'medicare_api' => [
        'base_url' => env('MEDICARE_API_BASE_URL', 'http://192.168.48.208:9091'),
        'token' => env('MEDICARE_API_TOKEN'),
        'timeout' => env('MEDICARE_API_TIMEOUT', 30),
    ],
];
