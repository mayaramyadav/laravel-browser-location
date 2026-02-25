<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Mayaram\BrowserLocation\Concerns\HasLocations;
use Mayaram\BrowserLocation\PendingLocation;

it('creates a pending location from a locationable model', function (): void {
    $model = new class extends Model
    {
        use HasLocations;
    };

    $pending = $model->addLocation([
        'latitude' => 12.9716,
        'longitude' => 77.5946,
    ]);

    expect($pending)->toBeInstanceOf(PendingLocation::class);
});
