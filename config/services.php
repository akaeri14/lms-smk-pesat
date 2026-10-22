<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'profiling' => [
        'base_url' => env('PROFILING_API_URL', 'https://dev-profiling.smkpesat.id/api/v1'),
        'key' => env('PROFILING_API_KEY'),
        'auth_header' => env('PROFILING_API_AUTH_HEADER', 'X-API-KEY'),
        'auth_scheme' => env('PROFILING_API_AUTH_SCHEME'),
        'timeout' => (int) env('PROFILING_API_TIMEOUT', 15),
        'cache_ttl' => (int) env('PROFILING_API_CACHE_TTL', 300),
        'endpoints' => [
            'students' => env('PROFILING_API_STUDENTS_ENDPOINT', 'students'),
            'teachers' => env('PROFILING_API_TEACHERS_ENDPOINT', 'teachers'),
            'profiles' => env('PROFILING_API_PROFILES_ENDPOINT', 'profiles'),
        ],
    ],

];
