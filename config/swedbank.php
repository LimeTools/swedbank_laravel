<?php

/**
 * Swedbank Payment Initiation API Configuration
 * 
 * This is a Laravel configuration file. The env() helper function
 * is provided by Laravel and is available at runtime.
 * 
 * @phpstan-ignore-file
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Swedbank Payment Initiation API Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for Swedbank Payment Initiation API V3.
    | The API uses JWS (JSON Web Signature) authentication for secure
    | communication between merchants and Swedbank.
    |
    */

    // @phpstan-ignore-next-line
    'environment' => env('SWEDBANK_ENVIRONMENT', 'production'),

    'sandbox' => [
        // @phpstan-ignore-next-line
        'enabled' => env('SWEDBANK_SANDBOX_ENABLED', false),
        // @phpstan-ignore-next-line
        'client_id' => env('SWEDBANK_SANDBOX_CLIENT_ID'),
        // @phpstan-ignore-next-line
        'private_key' => env('SWEDBANK_SANDBOX_PRIVATE_KEY'),
    ],

    'production' => [
        // @phpstan-ignore-next-line
        'enabled' => env('SWEDBANK_PRODUCTION_ENABLED', true),
        // @phpstan-ignore-next-line
        'client_id' => env('SWEDBANK_PRODUCTION_CLIENT_ID'),
        // @phpstan-ignore-next-line
        'private_key' => env('SWEDBANK_PRODUCTION_PRIVATE_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Endpoints
    |--------------------------------------------------------------------------
    |
    | Base URLs for different environments (V3 API)
    |
    */
    'endpoints' => [
        'sandbox' => [
            'base_url' => 'https://api-sandbox.swedbank.com/pi',
        ],
        'production' => [
            'base_url' => 'https://pi.swedbank.com/public/api',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | JWS Configuration
    |--------------------------------------------------------------------------
    |
    | JWS (JSON Web Signature) settings for API authentication
    | Note: This implementation uses detached JWS format
    |
    */
    'jws' => [
        'algorithm' => 'RS512',
        'header' => [
            'b64' => false,
            'crit' => ['b64'],
            'alg' => 'RS512',
            'typ' => 'JWT',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Configuration
    |--------------------------------------------------------------------------
    |
    | Default payment settings
    |
    */
    'payment' => [
        'default_currency' => 'EUR',
        'supported_currencies' => ['EUR', 'SEK', 'DKK', 'NOK'],
        'default_country' => 'LT',
        'supported_countries' => ['LT', 'LV', 'EE', 'SE', 'DK', 'NO'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Logging settings for debugging and monitoring
    |
    */
    'logging' => [
        // @phpstan-ignore-next-line
        'enabled' => env('SWEDBANK_LOGGING_ENABLED', true),
        // @phpstan-ignore-next-line
        'level' => env('SWEDBANK_LOG_LEVEL', 'info'),
        // @phpstan-ignore-next-line
        'channel' => env('SWEDBANK_LOG_CHANNEL', 'swedbank'),
    ],
];

