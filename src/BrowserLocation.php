<?php

declare(strict_types=1);

namespace Mayaram\BrowserLocation;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Throwable;

class BrowserLocation
{
    public function preparePayload(array $payload): array
    {
        $accuracy = isset($payload['accuracy_meters']) ? round(max(0, (float) $payload['accuracy_meters']), 2) : null;
        $capturedAt = Arr::get($payload, 'captured_at', now());

        return [
            'user_id' => Arr::get($payload, 'user_id'),
            'latitude' => isset($payload['latitude']) ? $this->normalizeLatitude((float) $payload['latitude']) : null,
            'longitude' => isset($payload['longitude']) ? $this->normalizeLongitude((float) $payload['longitude']) : null,
            'accuracy_meters' => $accuracy,
            'accuracy_level' => $this->accuracyLevel($accuracy),
            'is_accurate' => $this->isAccuracyAcceptable($accuracy),
            'permission_state' => Arr::get($payload, 'permission_state'),
            'error_code' => Arr::get($payload, 'error_code'),
            'error_message' => Arr::get($payload, 'error_message'),
            'source' => Arr::get($payload, 'source', 'html5_geolocation'),
            'meta' => Arr::get($payload, 'meta', []),
            'captured_at' => $capturedAt instanceof Carbon ? $capturedAt : Carbon::parse((string) $capturedAt),
        ];
    }

    public function accuracyLevel(?float $accuracyMeters): string
    {
        if ($accuracyMeters === null) {
            return 'unknown';
        }

        $excellent = (float) $this->config('browser-location.accuracy.excellent_meters', 20);
        $good = (float) $this->config('browser-location.accuracy.good_meters', 100);

        if ($accuracyMeters <= $excellent) {
            return 'excellent';
        }

        if ($accuracyMeters <= $good) {
            return 'good';
        }

        return 'poor';
    }

    public function isAccuracyAcceptable(?float $accuracyMeters): bool
    {
        if ($accuracyMeters === null) {
            return ! (bool) $this->config('browser-location.validation.require_accuracy', false);
        }

        return $accuracyMeters <= (float) $this->config('browser-location.validation.max_accuracy_meters', 200);
    }

    public function normalizeLatitude(float $latitude): float
    {
        $precision = (int) $this->config('browser-location.storage.coordinate_precision', 7);

        return round(max(-90, min(90, $latitude)), $precision);
    }

    public function normalizeLongitude(float $longitude): float
    {
        $precision = (int) $this->config('browser-location.storage.coordinate_precision', 7);

        return round(max(-180, min(180, $longitude)), $precision);
    }

    private function config(string $key, mixed $default): mixed
    {
        try {
            return config($key, $default);
        } catch (Throwable) {
            return $default;
        }
    }

    public function toJson(array $payload, int $options = 0): string
    {
        return json_encode($this->preparePayload($payload), $options) ?: '{}';
    }
}
