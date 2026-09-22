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

    'scryfall' => [
        'base_url' => env('SCRYFALL_API_BASE_URL', 'https://api.scryfall.com'),
        'timeout' => env('SCRYFALL_API_TIMEOUT', 10),
        'connect_timeout' => env('SCRYFALL_API_CONNECT_TIMEOUT', 3),
        'cache_ttl' => env('SCRYFALL_API_CACHE_TTL', 3600),
    ],

    'tesseract' => [
        'binary' => env('TESSERACT_BINARY', 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe'),
        'timeout' => env('TESSERACT_TIMEOUT', 15),
    ],

];
