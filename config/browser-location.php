<?php

declare(strict_types=1);

return [
    'defaults' => [
        'enable_high_accuracy' => true,
        'timeout' => 12000,
        'maximum_age' => 0,
    ],

    'accuracy' => [
        'excellent_meters' => 20,
        'good_meters' => 100,
    ],

    'validation' => [
        'required' => false,
        'require_accuracy' => false,
        'max_accuracy_meters' => 200,
        'require_authentication' => false,
    ],

    'storage' => [
        'persist' => true,
        'attach_authenticated_user' => true,
        'coordinate_precision' => 7,
    ],

    'component' => [
        'button_text' => 'Share GPS location',
        'auto_capture' => true,
        'force_permission' => true,
        'watch' => false,
        'livewire_method' => 'setBrowserLocation',
    ],

    'geocoder' => [
        'provider' => env('BROWSER_LOCATION_GEOCODER_PROVIDER', 'openstreetmap'),
        'timeout' => (float) env('BROWSER_LOCATION_GEOCODER_TIMEOUT', 8),
        'connect_timeout' => (float) env('BROWSER_LOCATION_GEOCODER_CONNECT_TIMEOUT', 3),
        'retries' => (int) env('BROWSER_LOCATION_GEOCODER_RETRIES', 1),
        'retry_delay_ms' => (int) env('BROWSER_LOCATION_GEOCODER_RETRY_DELAY_MS', 150),

        'cache' => [
            'enabled' => (bool) env('BROWSER_LOCATION_GEOCODER_CACHE_ENABLED', true),
            'store' => env('BROWSER_LOCATION_GEOCODER_CACHE_STORE'),
            'ttl' => (int) env('BROWSER_LOCATION_GEOCODER_CACHE_TTL', 3600),
            'prefix' => env('BROWSER_LOCATION_GEOCODER_CACHE_PREFIX', 'browser-location:geocoder'),
        ],

        'providers' => [
            'google' => [
                'base_url' => env('BROWSER_LOCATION_GOOGLE_BASE_URL', 'https://maps.googleapis.com/maps/api/geocode/json'),
                'api_key' => env('BROWSER_LOCATION_GOOGLE_API_KEY'),
                'language' => env('BROWSER_LOCATION_GOOGLE_LANGUAGE'),
                'region' => env('BROWSER_LOCATION_GOOGLE_REGION'),
            ],

            'mapbox' => [
                'base_url' => env('BROWSER_LOCATION_MAPBOX_BASE_URL', 'https://api.mapbox.com/geocoding/v5/mapbox.places'),
                'access_token' => env('BROWSER_LOCATION_MAPBOX_ACCESS_TOKEN'),
                'language' => env('BROWSER_LOCATION_MAPBOX_LANGUAGE'),
                'country' => env('BROWSER_LOCATION_MAPBOX_COUNTRY'),
                'limit' => (int) env('BROWSER_LOCATION_MAPBOX_LIMIT', 1),
            ],

            'openstreetmap' => [
                'base_url' => env('BROWSER_LOCATION_OSM_BASE_URL', 'https://nominatim.openstreetmap.org'),
                'user_agent' => env('BROWSER_LOCATION_OSM_USER_AGENT', env('APP_NAME', 'Laravel').' Browser Location Geocoder'),
                'email' => env('BROWSER_LOCATION_OSM_EMAIL'),
                'language' => env('BROWSER_LOCATION_OSM_LANGUAGE'),
                'country' => env('BROWSER_LOCATION_OSM_COUNTRY'),
                'limit' => (int) env('BROWSER_LOCATION_OSM_LIMIT', 1),
            ],
        ],
    ],
];
