<?php

declare(strict_types=1);

use Mayaram\BrowserLocation\BrowserLocation;

it('classifies accuracy levels', function (): void {
    $service = new BrowserLocation;

    expect($service->accuracyLevel(10.5))->toBe('excellent')
        ->and($service->accuracyLevel(64.5))->toBe('good')
        ->and($service->accuracyLevel(350.0))->toBe('poor')
        ->and($service->accuracyLevel(null))->toBe('unknown');
});

it('normalizes coordinate precision', function (): void {
    $service = new BrowserLocation;

    expect($service->normalizeLatitude(12.971598765))->toBe(12.9715988)
        ->and($service->normalizeLongitude(77.594562765))->toBe(77.5945628);
});

it('converts payload to json', function (): void {
    $service = new BrowserLocation;

    $payload = [
        'latitude' => 12.9715987,
        'longitude' => 77.5945627,
        'accuracy_meters' => 15,
    ];

    $json = $service->toJson($payload);
    $decoded = json_decode($json, true);

    expect($decoded)->toHaveKey('latitude', 12.9715987)
        ->and($decoded)->toHaveKey('longitude', 77.5945627)
        ->and($decoded)->toHaveKey('accuracy_meters', 15)
        ->and($decoded)->toHaveKey('accuracy_level', 'excellent');
});
