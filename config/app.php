<?php

return [
    'name' => env('APP_NAME', 'Blindem Liquidaciones'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    // Public demo deployments load the same demo accounts and data as local.
    'allow_demo_seed' => (bool) env('ALLOW_DEMO_SEED', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => 'America/Bogota',
    'locale' => env('APP_LOCALE', 'es'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'es'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'es_CO'),
    'key' => env('APP_KEY'),
    'previous_keys' => array_filter(explode(',', env('APP_PREVIOUS_KEYS', ''))),
    'cipher' => 'AES-256-CBC',
];
