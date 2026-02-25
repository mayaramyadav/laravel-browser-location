<?php

declare(strict_types=1);

namespace Mayaram\BrowserLocation;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Mayaram\BrowserLocation\Contracts\Geocoder as GeocoderContract;
use Mayaram\BrowserLocation\Exceptions\LocationPersistenceException;
use Mayaram\BrowserLocation\Models\BrowserLocation as BrowserLocationModel;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Throwable;

class LocationPersister
{
    public function __construct(
        private readonly BrowserLocation $browserLocation,
        private readonly GeocoderContract $geocoder,
        private readonly ConfigRepository $config,
        private readonly ?SymfonyRequest $request = null
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function persist(Model $locationable, array $data, string $collection, bool $singleCollection = false): BrowserLocationModel
    {
        $normalized = $this->normalizePayload($data, $collection);

        $this->enforceQualityRules(
            $this->latestLocationFor($locationable, $normalized['collection_name']),
            $normalized
        );

        $geocoderPayload = $this->resolveGeocoderPayload(
            $normalized['latitude'],
            $normalized['longitude']
        );

        $address = $this->resolveAddress($normalized, $geocoderPayload);
        $meta = $this->enforceMetaSize($this->buildMeta($normalized, $geocoderPayload));
        $legacyPayload = $this->browserLocation->preparePayload([
            'latitude' => $normalized['latitude'],
            'longitude' => $normalized['longitude'],
            'accuracy_meters' => $normalized['accuracy'],
            'permission_state' => $normalized['permission_state'],
            'error_code' => $normalized['error_code'],
            'error_message' => $normalized['error_message'],
            'source' => $normalized['source'],
            'captured_at' => $normalized['captured_at'],
            'meta' => $meta,
        ]);

        /** @var BrowserLocationModel $location */
        $location = DB::transaction(function () use (
            $locationable,
            $normalized,
            $singleCollection,
            $address,
            $meta,
            $legacyPayload
        ): BrowserLocationModel {
            if ($singleCollection) {
                BrowserLocationModel::query()
                    ->where('locationable_type', $locationable->getMorphClass())
                    ->where('locationable_id', $locationable->getKey())
                    ->where('collection_name', $normalized['collection_name'])
                    ->delete();
            }

            /** @var BrowserLocationModel $model */
            $model = BrowserLocationModel::query()->create([
                'locationable_type' => $locationable->getMorphClass(),
                'locationable_id' => $locationable->getKey(),
                'collection_name' => $normalized['collection_name'],
                'latitude' => $normalized['latitude'],
                'longitude' => $normalized['longitude'],
                'accuracy' => $normalized['accuracy'],
                'address' => $address,
                'meta' => $meta,
                'user_id' => $this->resolveLegacyUserId($locationable),
                'accuracy_meters' => Arr::get($legacyPayload, 'accuracy_meters'),
                'accuracy_level' => Arr::get($legacyPayload, 'accuracy_level'),
                'is_accurate' => Arr::get($legacyPayload, 'is_accurate', false),
                'permission_state' => Arr::get($legacyPayload, 'permission_state'),
                'error_code' => Arr::get($legacyPayload, 'error_code'),
                'error_message' => Arr::get($legacyPayload, 'error_message'),
                'source' => Arr::get($legacyPayload, 'source', 'html5_geolocation'),
                'captured_at' => Arr::get($legacyPayload, 'captured_at'),
            ]);

            return $model;
        });

        return $location;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function persistAnonymous(array $data, string $collection): BrowserLocationModel
    {
        $normalized = $this->normalizePayload($data, $collection);
        $this->enforceQualityRules(
            $this->latestAnonymousLocation($normalized['collection_name']),
            $normalized
        );

        $geocoderPayload = $this->resolveGeocoderPayload(
            $normalized['latitude'],
            $normalized['longitude']
        );
        $address = $this->resolveAddress($normalized, $geocoderPayload);
        $meta = $this->enforceMetaSize($this->buildMeta($normalized, $geocoderPayload));
        $legacyPayload = $this->browserLocation->preparePayload([
            'latitude' => $normalized['latitude'],
            'longitude' => $normalized['longitude'],
            'accuracy_meters' => $normalized['accuracy'],
            'permission_state' => $normalized['permission_state'],
            'error_code' => $normalized['error_code'],
            'error_message' => $normalized['error_message'],
            'source' => $normalized['source'],
            'captured_at' => $normalized['captured_at'],
            'meta' => $meta,
        ]);

        /** @var BrowserLocationModel $location */
        $location = BrowserLocationModel::query()->create([
            'locationable_type' => null,
            'locationable_id' => null,
            'collection_name' => $normalized['collection_name'],
            'latitude' => $normalized['latitude'],
            'longitude' => $normalized['longitude'],
            'accuracy' => $normalized['accuracy'],
            'address' => $address,
            'meta' => $meta,
            'user_id' => null,
            'accuracy_meters' => Arr::get($legacyPayload, 'accuracy_meters'),
            'accuracy_level' => Arr::get($legacyPayload, 'accuracy_level'),
            'is_accurate' => Arr::get($legacyPayload, 'is_accurate', false),
            'permission_state' => Arr::get($legacyPayload, 'permission_state'),
            'error_code' => Arr::get($legacyPayload, 'error_code'),
            'error_message' => Arr::get($legacyPayload, 'error_message'),
            'source' => Arr::get($legacyPayload, 'source', 'html5_geolocation'),
            'captured_at' => Arr::get($legacyPayload, 'captured_at'),
        ]);

        return $location;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     latitude: float,
     *     longitude: float,
     *     accuracy: ?float,
     *     address: ?string,
     *     source: string,
     *     permission_state: ?string,
     *     error_code: ?int,
     *     error_message: ?string,
     *     captured_at: CarbonImmutable,
     *     collection_name: string,
     *     raw_payload: array<string, mixed>,
     *     browser_meta: array<string, mixed>
     * }
     */
    private function normalizePayload(array $payload, string $collection): array
    {
        $latitude = Arr::get($payload, 'latitude');
        $longitude = Arr::get($payload, 'longitude');

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            throw new LocationPersistenceException('Latitude and longitude are required to persist a browser location.');
        }

        $accuracyRaw = Arr::get($payload, 'accuracy', Arr::get($payload, 'accuracy_meters'));
        $accuracy = is_numeric($accuracyRaw) ? round(max(0, (float) $accuracyRaw), 2) : null;
        $addressRaw = Arr::get($payload, 'address');
        $address = is_string($addressRaw) && trim($addressRaw) !== '' ? trim($addressRaw) : null;
        $sourceRaw = Arr::get($payload, 'source', 'html5_geolocation');
        $source = is_string($sourceRaw) && trim($sourceRaw) !== '' ? trim($sourceRaw) : 'html5_geolocation';
        $permissionRaw = Arr::get($payload, 'permission_state');
        $permissionState = is_string($permissionRaw) ? trim($permissionRaw) : null;
        $errorCodeRaw = Arr::get($payload, 'error_code');
        $errorCode = is_numeric($errorCodeRaw) ? (int) $errorCodeRaw : null;
        $errorMessageRaw = Arr::get($payload, 'error_message');
        $errorMessage = is_string($errorMessageRaw) && trim($errorMessageRaw) !== '' ? trim($errorMessageRaw) : null;
        $capturedAt = $this->parseCapturedAt(Arr::get($payload, 'captured_at'));
        $browserMeta = Arr::get($payload, 'meta');
        $browserMeta = is_array($browserMeta) ? $browserMeta : [];

        $collectionName = trim($collection);
        if ($collectionName === '') {
            $collectionName = (string) $this->config->get('browser-location.default_collection', 'default');
        }

        return [
            'latitude' => $this->browserLocation->normalizeLatitude((float) $latitude),
            'longitude' => $this->browserLocation->normalizeLongitude((float) $longitude),
            'accuracy' => $accuracy,
            'address' => $address,
            'source' => $source,
            'permission_state' => $permissionState,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'captured_at' => $capturedAt,
            'collection_name' => $collectionName,
            'raw_payload' => $payload,
            'browser_meta' => $browserMeta,
        ];
    }

    /**
     * @param  array{
     *     latitude: float,
     *     longitude: float,
     *     accuracy: ?float,
     *     address: ?string,
     *     source: string,
     *     permission_state: ?string,
     *     error_code: ?int,
     *     error_message: ?string,
     *     captured_at: CarbonImmutable,
     *     collection_name: string,
     *     raw_payload: array<string, mixed>,
     *     browser_meta: array<string, mixed>
     * }  $normalized
     */
    private function enforceQualityRules(?BrowserLocationModel $latest, array $normalized): void
    {
        $maxAccuracy = (float) $this->config->get(
            'browser-location.min_accuracy',
            (float) $this->config->get('browser-location.validation.max_accuracy_meters', 200)
        );

        if ($normalized['accuracy'] !== null && $normalized['accuracy'] > $maxAccuracy) {
            throw new LocationPersistenceException(
                sprintf('Location accuracy %.2fm is above the allowed limit of %.2fm.', $normalized['accuracy'], $maxAccuracy)
            );
        }

        if (! (bool) $this->config->get('browser-location.prevent_duplicates', true)) {
            return;
        }

        if (! $latest) {
            return;
        }

        $distanceMeters = $this->distanceMeters(
            (float) $latest->latitude,
            (float) $latest->longitude,
            $normalized['latitude'],
            $normalized['longitude']
        );

        if ($distanceMeters < 20) {
            throw new LocationPersistenceException('Duplicate location ignored because it is within 20 meters of the last saved point.');
        }

        $sameCoordinates = abs((float) $latest->latitude - $normalized['latitude']) < 0.0000009
            && abs((float) $latest->longitude - $normalized['longitude']) < 0.0000009;

        if (! $sameCoordinates) {
            return;
        }

        $latestCapturedAt = $latest->captured_at;

        if (! $latestCapturedAt) {
            throw new LocationPersistenceException('Duplicate location ignored to prevent repeated submissions.');
        }

        if ($normalized['captured_at']->diffInSeconds($latestCapturedAt) < 60) {
            throw new LocationPersistenceException('Duplicate location ignored to prevent repeated submissions.');
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveGeocoderPayload(float $latitude, float $longitude): ?array
    {
        try {
            return $this->geocoder->reverse($latitude, $longitude);
        } catch (Throwable $exception) {
            return [
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @param  array{
     *     latitude: float,
     *     longitude: float,
     *     accuracy: ?float,
     *     address: ?string,
     *     source: string,
     *     permission_state: ?string,
     *     error_code: ?int,
     *     error_message: ?string,
     *     captured_at: CarbonImmutable,
     *     collection_name: string,
     *     raw_payload: array<string, mixed>,
     *     browser_meta: array<string, mixed>
     * }  $normalized
     * @param  array<string, mixed>|null  $geocoderPayload
     */
    private function resolveAddress(array $normalized, ?array $geocoderPayload): ?string
    {
        if ($normalized['address'] !== null) {
            return $normalized['address'];
        }

        $resolvedAddress = Arr::get($geocoderPayload ?? [], 'resolved.formatted_address');

        return is_string($resolvedAddress) && trim($resolvedAddress) !== ''
            ? trim($resolvedAddress)
            : null;
    }

    /**
     * @param  array{
     *     latitude: float,
     *     longitude: float,
     *     accuracy: ?float,
     *     address: ?string,
     *     source: string,
     *     permission_state: ?string,
     *     error_code: ?int,
     *     error_message: ?string,
     *     captured_at: CarbonImmutable,
     *     collection_name: string,
     *     raw_payload: array<string, mixed>,
     *     browser_meta: array<string, mixed>
     * }  $normalized
     * @param  array<string, mixed>|null  $geocoderPayload
     * @return array<string, mixed>
     */
    private function buildMeta(array $normalized, ?array $geocoderPayload): array
    {
        return [
            'raw_browser_gps_response' => Arr::get(
                $normalized['raw_payload'],
                'raw_browser_gps',
                Arr::get($normalized['browser_meta'], 'raw_browser_gps', Arr::get($normalized['browser_meta'], 'raw_position'))
            ),
            'raw_geocoder_response' => $geocoderPayload,
            'request' => [
                'ip_address' => $this->request?->getClientIp(),
                'user_agent' => $this->request?->headers->get('User-Agent'),
            ],
            'timestamp' => now()->toIso8601String(),
            'captured_at' => $normalized['captured_at']->toIso8601String(),
            'app' => [
                'name' => (string) $this->config->get('app.name', 'Laravel'),
                'env' => (string) $this->config->get('app.env', 'production'),
                'url' => (string) $this->config->get('app.url', ''),
            ],
            'source' => $normalized['source'],
            'permission_state' => $normalized['permission_state'],
            'error' => [
                'code' => $normalized['error_code'],
                'message' => $normalized['error_message'],
            ],
            'payload' => $normalized['raw_payload'],
        ];
    }

    private function parseCapturedAt(mixed $value): CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value !== null && $value !== '') {
            try {
                return CarbonImmutable::parse((string) $value);
            } catch (Throwable) {
                // Fall back to now() below.
            }
        }

        return CarbonImmutable::now();
    }

    private function resolveLegacyUserId(Model $locationable): ?int
    {
        $authIdentifier = method_exists($locationable, 'getAuthIdentifier')
            ? $locationable->getAuthIdentifier()
            : null;

        return is_numeric($authIdentifier) ? (int) $authIdentifier : null;
    }

    private function latestLocationFor(Model $locationable, string $collection): ?BrowserLocationModel
    {
        /** @var BrowserLocationModel|null $location */
        $location = BrowserLocationModel::query()
            ->where('locationable_type', $locationable->getMorphClass())
            ->where('locationable_id', $locationable->getKey())
            ->where('collection_name', $collection)
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->first();

        return $location;
    }

    private function latestAnonymousLocation(string $collection): ?BrowserLocationModel
    {
        $query = BrowserLocationModel::query()
            ->whereNull('locationable_type')
            ->whereNull('locationable_id')
            ->where('collection_name', $collection);

        $requestIp = $this->request?->getClientIp();
        if (is_string($requestIp) && $requestIp !== '') {
            $query->where('meta->request->ip_address', $requestIp);
        }

        /** @var BrowserLocationModel|null $location */
        $location = $query->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->first();

        return $location;
    }

    private function distanceMeters(float $latOne, float $lngOne, float $latTwo, float $lngTwo): float
    {
        $earthRadiusMeters = 6371000;
        $deltaLatitude = deg2rad($latTwo - $latOne);
        $deltaLongitude = deg2rad($lngTwo - $lngOne);

        $a = sin($deltaLatitude / 2) ** 2
            + cos(deg2rad($latOne)) * cos(deg2rad($latTwo)) * sin($deltaLongitude / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusMeters * $c;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function enforceMetaSize(array $meta): array
    {
        $maxMetaBytes = max(1024, (int) $this->config->get('browser-location.max_meta_bytes', 65535));
        $encoded = json_encode($meta);

        if ($encoded === false) {
            throw new LocationPersistenceException('Unable to encode location meta payload.');
        }

        if (strlen($encoded) > $maxMetaBytes) {
            throw new LocationPersistenceException(
                sprintf('Location meta payload exceeded maximum size of %d bytes.', $maxMetaBytes)
            );
        }

        return $meta;
    }
}
