<?php

declare(strict_types=1);

namespace Mayaram\BrowserLocation;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Arr;
use Mayaram\BrowserLocation\Contracts\Geocoder as GeocoderContract;
use Mayaram\BrowserLocation\Exceptions\GeocoderException;
use Throwable;

class Geocoder implements GeocoderContract
{
    private const GOOGLE = 'google';

    private const MAPBOX = 'mapbox';

    private const OPEN_STREET_MAP = 'openstreetmap';

    public function __construct(
        private readonly HttpFactory $http,
        private readonly CacheFactory $cache,
        private readonly ConfigRepository $config
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function reverse(float $latitude, float $longitude, array $options = []): array
    {
        $provider = $this->resolveProvider(Arr::get($options, 'provider'));

        $this->assertValidCoordinates($latitude, $longitude);

        $query = [
            'latitude' => round($latitude, 7),
            'longitude' => round($longitude, 7),
            'language' => Arr::get($options, 'language'),
            'region' => Arr::get($options, 'region'),
            'country' => Arr::get($options, 'country'),
            'limit' => Arr::get($options, 'limit'),
        ];

        return $this->remember($provider, 'reverse', $query, function () use ($provider, $latitude, $longitude, $options): array {
            return match ($provider) {
                self::GOOGLE => $this->reverseGoogle($latitude, $longitude, $options),
                self::MAPBOX => $this->reverseMapbox($latitude, $longitude, $options),
                self::OPEN_STREET_MAP => $this->reverseOpenStreetMap($latitude, $longitude, $options),
                default => throw new GeocoderException(sprintf('Unsupported geocoder provider [%s].', $provider)),
            };
        });
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function forward(string $address, array $options = []): array
    {
        $provider = $this->resolveProvider(Arr::get($options, 'provider'));
        $normalizedAddress = trim($address);

        if ($normalizedAddress === '') {
            throw new GeocoderException('The geocoder address must not be empty.');
        }

        $query = [
            'address' => $normalizedAddress,
            'language' => Arr::get($options, 'language'),
            'region' => Arr::get($options, 'region'),
            'country' => Arr::get($options, 'country'),
            'limit' => Arr::get($options, 'limit'),
        ];

        return $this->remember($provider, 'forward', $query, function () use ($provider, $normalizedAddress, $options): array {
            return match ($provider) {
                self::GOOGLE => $this->forwardGoogle($normalizedAddress, $options),
                self::MAPBOX => $this->forwardMapbox($normalizedAddress, $options),
                self::OPEN_STREET_MAP => $this->forwardOpenStreetMap($normalizedAddress, $options),
                default => throw new GeocoderException(sprintf('Unsupported geocoder provider [%s].', $provider)),
            };
        });
    }

    private function assertValidCoordinates(float $latitude, float $longitude): void
    {
        if ($latitude < -90 || $latitude > 90) {
            throw new GeocoderException('Latitude must be between -90 and 90.');
        }

        if ($longitude < -180 || $longitude > 180) {
            throw new GeocoderException('Longitude must be between -180 and 180.');
        }
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function reverseGoogle(float $latitude, float $longitude, array $options): array
    {
        $providerConfig = $this->providerConfig(self::GOOGLE);
        $apiKey = trim((string) Arr::get($providerConfig, 'api_key', ''));

        if ($apiKey === '') {
            throw new GeocoderException('Google geocoder API key is not configured.');
        }

        $query = [
            'latlng' => $latitude.','.$longitude,
            'key' => $apiKey,
        ];

        $language = $this->resolveOption($options, $providerConfig, 'language');

        if ($language !== null) {
            $query['language'] = $language;
        }

        $region = $this->resolveOption($options, $providerConfig, 'region');

        if ($region !== null) {
            $query['region'] = $region;
        }

        $response = $this->requestJson(
            self::GOOGLE,
            (string) Arr::get($providerConfig, 'base_url', 'https://maps.googleapis.com/maps/api/geocode/json'),
            $query
        );

        $status = strtoupper((string) Arr::get($response, 'status', 'UNKNOWN'));

        if ($status === 'ZERO_RESULTS') {
            return $this->formatResult(self::GOOGLE, ['latitude' => $latitude, 'longitude' => $longitude], [], $response);
        }

        if ($status !== 'OK') {
            $message = (string) Arr::get($response, 'error_message', 'Google geocoding request failed.');

            throw new GeocoderException(sprintf('Google geocoding failed with status [%s]: %s', $status, $message));
        }

        $results = array_map(
            fn (array $item): array => $this->normalizeGoogleResult($item),
            array_values(array_filter(Arr::get($response, 'results', []), 'is_array'))
        );

        return $this->formatResult(self::GOOGLE, ['latitude' => $latitude, 'longitude' => $longitude], $results, $response);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function forwardGoogle(string $address, array $options): array
    {
        $providerConfig = $this->providerConfig(self::GOOGLE);
        $apiKey = trim((string) Arr::get($providerConfig, 'api_key', ''));

        if ($apiKey === '') {
            throw new GeocoderException('Google geocoder API key is not configured.');
        }

        $query = [
            'address' => $address,
            'key' => $apiKey,
        ];

        $language = $this->resolveOption($options, $providerConfig, 'language');

        if ($language !== null) {
            $query['language'] = $language;
        }

        $region = $this->resolveOption($options, $providerConfig, 'region');

        if ($region !== null) {
            $query['region'] = $region;
        }

        $response = $this->requestJson(
            self::GOOGLE,
            (string) Arr::get($providerConfig, 'base_url', 'https://maps.googleapis.com/maps/api/geocode/json'),
            $query
        );

        $status = strtoupper((string) Arr::get($response, 'status', 'UNKNOWN'));

        if ($status === 'ZERO_RESULTS') {
            return $this->formatResult(self::GOOGLE, ['address' => $address], [], $response);
        }

        if ($status !== 'OK') {
            $message = (string) Arr::get($response, 'error_message', 'Google geocoding request failed.');

            throw new GeocoderException(sprintf('Google geocoding failed with status [%s]: %s', $status, $message));
        }

        $results = array_map(
            fn (array $item): array => $this->normalizeGoogleResult($item),
            array_values(array_filter(Arr::get($response, 'results', []), 'is_array'))
        );

        return $this->formatResult(self::GOOGLE, ['address' => $address], $results, $response);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function reverseMapbox(float $latitude, float $longitude, array $options): array
    {
        $providerConfig = $this->providerConfig(self::MAPBOX);
        $accessToken = trim((string) Arr::get($providerConfig, 'access_token', ''));

        if ($accessToken === '') {
            throw new GeocoderException('Mapbox access token is not configured.');
        }

        $baseUrl = rtrim((string) Arr::get($providerConfig, 'base_url', 'https://api.mapbox.com/geocoding/v5/mapbox.places'), '/');
        $endpoint = sprintf('%s/%s,%s.json', $baseUrl, $longitude, $latitude);

        $query = [
            'access_token' => $accessToken,
            'limit' => $this->resolveLimit($options, $providerConfig),
        ];

        $language = $this->resolveOption($options, $providerConfig, 'language');

        if ($language !== null) {
            $query['language'] = $language;
        }

        $country = $this->resolveOption($options, $providerConfig, 'country');

        if ($country !== null) {
            $query['country'] = $country;
        }

        $response = $this->requestJson(self::MAPBOX, $endpoint, $query);

        $message = Arr::get($response, 'message');

        if (is_string($message) && $message !== '') {
            throw new GeocoderException('Mapbox geocoding failed: '.$message);
        }

        $results = array_map(
            fn (array $item): array => $this->normalizeMapboxResult($item),
            array_values(array_filter(Arr::get($response, 'features', []), 'is_array'))
        );

        return $this->formatResult(self::MAPBOX, ['latitude' => $latitude, 'longitude' => $longitude], $results, $response);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function forwardMapbox(string $address, array $options): array
    {
        $providerConfig = $this->providerConfig(self::MAPBOX);
        $accessToken = trim((string) Arr::get($providerConfig, 'access_token', ''));

        if ($accessToken === '') {
            throw new GeocoderException('Mapbox access token is not configured.');
        }

        $baseUrl = rtrim((string) Arr::get($providerConfig, 'base_url', 'https://api.mapbox.com/geocoding/v5/mapbox.places'), '/');
        $endpoint = sprintf('%s/%s.json', $baseUrl, rawurlencode($address));

        $query = [
            'access_token' => $accessToken,
            'limit' => $this->resolveLimit($options, $providerConfig),
        ];

        $language = $this->resolveOption($options, $providerConfig, 'language');

        if ($language !== null) {
            $query['language'] = $language;
        }

        $country = $this->resolveOption($options, $providerConfig, 'country');

        if ($country !== null) {
            $query['country'] = $country;
        }

        $response = $this->requestJson(self::MAPBOX, $endpoint, $query);

        $message = Arr::get($response, 'message');

        if (is_string($message) && $message !== '') {
            throw new GeocoderException('Mapbox geocoding failed: '.$message);
        }

        $results = array_map(
            fn (array $item): array => $this->normalizeMapboxResult($item),
            array_values(array_filter(Arr::get($response, 'features', []), 'is_array'))
        );

        return $this->formatResult(self::MAPBOX, ['address' => $address], $results, $response);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function reverseOpenStreetMap(float $latitude, float $longitude, array $options): array
    {
        $providerConfig = $this->providerConfig(self::OPEN_STREET_MAP);

        $query = [
            'format' => 'jsonv2',
            'lat' => $latitude,
            'lon' => $longitude,
            'addressdetails' => 1,
            'zoom' => 18,
        ];

        $language = $this->resolveOption($options, $providerConfig, 'language');

        if ($language !== null) {
            $query['accept-language'] = $language;
        }

        $email = $this->resolveOption($options, $providerConfig, 'email');

        if ($email !== null) {
            $query['email'] = $email;
        }

        $endpoint = rtrim((string) Arr::get($providerConfig, 'base_url', 'https://nominatim.openstreetmap.org'), '/').'/reverse';
        $response = $this->requestJson(self::OPEN_STREET_MAP, $endpoint, $query, $this->openStreetMapHeaders($providerConfig));

        $error = Arr::get($response, 'error');

        if (is_string($error) && $error !== '') {
            throw new GeocoderException('OpenStreetMap geocoding failed: '.$error);
        }

        $results = [];

        if (Arr::get($response, 'display_name') !== null) {
            $results[] = $this->normalizeOpenStreetMapResult($response);
        }

        return $this->formatResult(self::OPEN_STREET_MAP, ['latitude' => $latitude, 'longitude' => $longitude], $results, $response);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function forwardOpenStreetMap(string $address, array $options): array
    {
        $providerConfig = $this->providerConfig(self::OPEN_STREET_MAP);

        $query = [
            'format' => 'jsonv2',
            'q' => $address,
            'addressdetails' => 1,
            'limit' => $this->resolveLimit($options, $providerConfig),
        ];

        $language = $this->resolveOption($options, $providerConfig, 'language');

        if ($language !== null) {
            $query['accept-language'] = $language;
        }

        $country = $this->resolveOption($options, $providerConfig, 'country');

        if ($country !== null) {
            $query['countrycodes'] = $country;
        }

        $email = $this->resolveOption($options, $providerConfig, 'email');

        if ($email !== null) {
            $query['email'] = $email;
        }

        $endpoint = rtrim((string) Arr::get($providerConfig, 'base_url', 'https://nominatim.openstreetmap.org'), '/').'/search';
        $response = $this->requestJson(self::OPEN_STREET_MAP, $endpoint, $query, $this->openStreetMapHeaders($providerConfig));

        if (! array_is_list($response)) {
            throw new GeocoderException('OpenStreetMap geocoding response payload is invalid.');
        }

        $results = array_map(
            fn (array $item): array => $this->normalizeOpenStreetMapResult($item),
            array_values(array_filter($response, 'is_array'))
        );

        return $this->formatResult(self::OPEN_STREET_MAP, ['address' => $address], $results, $response);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private function requestJson(string $provider, string $url, array $query = [], array $headers = []): array
    {
        $request = $this->http
            ->acceptJson()
            ->timeout((float) $this->config->get('browser-location.geocoder.timeout', 8))
            ->connectTimeout((float) $this->config->get('browser-location.geocoder.connect_timeout', 3));

        $retries = max(0, (int) $this->config->get('browser-location.geocoder.retries', 1));
        $retryDelayMs = max(0, (int) $this->config->get('browser-location.geocoder.retry_delay_ms', 150));

        if ($retries > 0) {
            $request = $request->retry($retries, $retryDelayMs);
        }

        if ($headers !== []) {
            $request = $request->withHeaders($headers);
        }

        try {
            $response = $request->get($url, $query);
        } catch (Throwable $exception) {
            throw new GeocoderException(sprintf('Unable to connect to %s geocoder provider.', $provider), 0, $exception);
        }

        if ($response->failed()) {
            throw new GeocoderException(sprintf('%s geocoder request failed with HTTP %d.', $provider, $response->status()));
        }

        $decoded = $response->json();

        if (! is_array($decoded)) {
            throw new GeocoderException(sprintf('Invalid JSON received from %s geocoder provider.', $provider));
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $providerConfig
     * @return array<string, string>
     */
    private function openStreetMapHeaders(array $providerConfig): array
    {
        $defaultAgent = 'laravel-browser-location/1.0 (+https://example.com)';
        $configuredAgent = trim((string) Arr::get($providerConfig, 'user_agent', $defaultAgent));

        return [
            'User-Agent' => $configuredAgent !== '' ? $configuredAgent : $defaultAgent,
        ];
    }

    private function resolveProvider(mixed $provider): string
    {
        $value = $provider;

        if (! is_string($value) || trim($value) === '') {
            $value = $this->config->get('browser-location.geocoder.provider', self::OPEN_STREET_MAP);
        }

        $normalized = strtolower(trim((string) $value));

        if (! in_array($normalized, [self::GOOGLE, self::MAPBOX, self::OPEN_STREET_MAP], true)) {
            throw new GeocoderException(sprintf('Unsupported geocoder provider [%s].', $normalized));
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $providerConfig
     */
    private function resolveLimit(array $options, array $providerConfig): int
    {
        $limit = Arr::get($options, 'limit', Arr::get($providerConfig, 'limit', 1));
        $limit = is_numeric($limit) ? (int) $limit : 1;

        return min(max($limit, 1), 10);
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $providerConfig
     */
    private function resolveOption(array $options, array $providerConfig, string $key): ?string
    {
        $value = Arr::get($options, $key, Arr::get($providerConfig, $key));

        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeGoogleResult(array $item): array
    {
        $location = Arr::get($item, 'geometry.location', []);
        $lat = is_numeric(Arr::get($location, 'lat')) ? (float) Arr::get($location, 'lat') : null;
        $lng = is_numeric(Arr::get($location, 'lng')) ? (float) Arr::get($location, 'lng') : null;

        return [
            'formatted_address' => Arr::get($item, 'formatted_address'),
            'latitude' => $lat,
            'longitude' => $lng,
            'place_id' => Arr::get($item, 'place_id'),
            'components' => Arr::get($item, 'address_components', []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeMapboxResult(array $item): array
    {
        $center = Arr::get($item, 'center', []);
        $lng = is_array($center) && isset($center[0]) && is_numeric($center[0]) ? (float) $center[0] : null;
        $lat = is_array($center) && isset($center[1]) && is_numeric($center[1]) ? (float) $center[1] : null;

        return [
            'formatted_address' => Arr::get($item, 'place_name'),
            'latitude' => $lat,
            'longitude' => $lng,
            'place_id' => Arr::get($item, 'id'),
            'components' => [
                'text' => Arr::get($item, 'text'),
                'type' => Arr::get($item, 'place_type', []),
                'context' => Arr::get($item, 'context', []),
                'properties' => Arr::get($item, 'properties', []),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeOpenStreetMapResult(array $item): array
    {
        $lat = Arr::get($item, 'lat');
        $lon = Arr::get($item, 'lon');

        return [
            'formatted_address' => Arr::get($item, 'display_name'),
            'latitude' => is_numeric($lat) ? (float) $lat : null,
            'longitude' => is_numeric($lon) ? (float) $lon : null,
            'place_id' => Arr::get($item, 'place_id'),
            'components' => Arr::get($item, 'address', []),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function formatResult(string $provider, array $query, array $results, array $raw): array
    {
        return [
            'provider' => $provider,
            'query' => $query,
            'resolved' => $results[0] ?? null,
            'results' => $results,
            'raw' => $raw,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function remember(string $provider, string $action, array $query, callable $callback): array
    {
        if (! (bool) $this->config->get('browser-location.geocoder.cache.enabled', true)) {
            return $callback();
        }

        $ttl = max(1, (int) $this->config->get('browser-location.geocoder.cache.ttl', 3600));
        $prefix = trim((string) $this->config->get('browser-location.geocoder.cache.prefix', 'browser-location:geocoder'));

        $cacheKeyPayload = [
            'provider' => $provider,
            'action' => $action,
            'query' => $this->sortRecursive($query),
        ];

        $serialized = json_encode($cacheKeyPayload);

        if ($serialized === false) {
            return $callback();
        }

        $cacheKey = sprintf('%s:%s', $prefix, sha1($serialized));
        $store = $this->config->get('browser-location.geocoder.cache.store');
        $repository = is_string($store) && trim($store) !== ''
            ? $this->cache->store(trim($store))
            : $this->cache->store();

        try {
            /** @var array<string, mixed> $cached */
            $cached = $repository->remember($cacheKey, $ttl, $callback);

            return $cached;
        } catch (GeocoderException $exception) {
            throw $exception;
        } catch (Throwable) {
            return $callback();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function providerConfig(string $provider): array
    {
        $config = $this->config->get('browser-location.geocoder.providers.'.$provider, []);

        return is_array($config) ? $config : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sortRecursive(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->sortRecursive($value);
            }
        }

        ksort($payload);

        return $payload;
    }
}
