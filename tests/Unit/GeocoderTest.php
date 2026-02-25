<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Http\Client\Factory as HttpFactory;
use Mayaram\BrowserLocation\Exceptions\GeocoderException;
use Mayaram\BrowserLocation\Geocoder;

it('reverse geocodes with openstreetmap and returns normalized payload', function (): void {
    $http = new HttpFactory;
    $http->fake([
        'https://nominatim.openstreetmap.org/reverse*' => HttpFactory::response([
            'place_id' => 12345,
            'lat' => '28.6139',
            'lon' => '77.2090',
            'display_name' => 'New Delhi, India',
            'address' => [
                'city' => 'New Delhi',
                'country' => 'India',
            ],
        ]),
    ]);

    $service = buildGeocoderService($http, [
        'provider' => 'openstreetmap',
    ]);

    $result = $service->reverse(28.6139, 77.2090);

    expect($result)->toHaveKey('provider', 'openstreetmap')
        ->and($result['resolved'])->not->toBeNull()
        ->and($result['resolved'])->toHaveKey('formatted_address', 'New Delhi, India')
        ->and($result['resolved'])->toHaveKey('latitude', 28.6139)
        ->and($result['resolved'])->toHaveKey('longitude', 77.209);

    $http->assertSentCount(1);
});

it('uses cache for repeated geocode queries', function (): void {
    $http = new HttpFactory;
    $http->fake([
        'https://nominatim.openstreetmap.org/search*' => HttpFactory::response([
            [
                'place_id' => 100,
                'lat' => '12.9716',
                'lon' => '77.5946',
                'display_name' => 'Bengaluru, Karnataka, India',
                'address' => [
                    'city' => 'Bengaluru',
                    'country' => 'India',
                ],
            ],
        ]),
    ]);

    $service = buildGeocoderService($http, [
        'provider' => 'openstreetmap',
        'cache' => [
            'enabled' => true,
            'store' => null,
            'ttl' => 3600,
            'prefix' => 'browser-location:geocoder',
        ],
    ]);

    $first = $service->forward('Bengaluru, India');
    $second = $service->forward('Bengaluru, India');

    expect($first['resolved'])->toBe($second['resolved']);

    $http->assertSentCount(1);
});

it('throws on invalid coordinates', function (): void {
    $service = buildGeocoderService(new HttpFactory, ['provider' => 'openstreetmap']);

    $service->reverse(91, 77.2090);
})->throws(GeocoderException::class, 'Latitude must be between -90 and 90.');

it('throws when google provider key is missing', function (): void {
    $service = buildGeocoderService(new HttpFactory, [
        'provider' => 'google',
        'providers' => [
            'google' => [
                'base_url' => 'https://maps.googleapis.com/maps/api/geocode/json',
                'api_key' => '',
            ],
        ],
    ]);

    $service->forward('New Delhi, India');
})->throws(GeocoderException::class, 'Google geocoder API key is not configured.');

/**
 * @param  array<string, mixed>  $geocoderConfig
 */
function buildGeocoderService(HttpFactory $http, array $geocoderConfig): Geocoder
{
    $defaults = [
        'provider' => 'openstreetmap',
        'timeout' => 8,
        'connect_timeout' => 3,
        'retries' => 1,
        'retry_delay_ms' => 150,
        'cache' => [
            'enabled' => true,
            'store' => null,
            'ttl' => 3600,
            'prefix' => 'browser-location:geocoder',
        ],
        'providers' => [
            'google' => [
                'base_url' => 'https://maps.googleapis.com/maps/api/geocode/json',
                'api_key' => 'fake-google-key',
                'language' => null,
                'region' => null,
            ],
            'mapbox' => [
                'base_url' => 'https://api.mapbox.com/geocoding/v5/mapbox.places',
                'access_token' => 'fake-mapbox-token',
                'language' => null,
                'country' => null,
                'limit' => 1,
            ],
            'openstreetmap' => [
                'base_url' => 'https://nominatim.openstreetmap.org',
                'user_agent' => 'laravel-browser-location-tests/1.0 (tests@example.com)',
                'email' => 'tests@example.com',
                'language' => null,
                'country' => null,
                'limit' => 1,
            ],
        ],
    ];

    $config = new ConfigRepository([
        'browser-location' => [
            'geocoder' => array_replace_recursive($defaults, $geocoderConfig),
        ],
    ]);

    $cacheRepository = new CacheRepository(new ArrayStore);
    $cacheFactory = new class($cacheRepository) implements CacheFactory
    {
        public function __construct(private readonly CacheRepository $repository) {}

        public function store($name = null): CacheRepository
        {
            return $this->repository;
        }
    };

    return new Geocoder($http, $cacheFactory, $config);
}
