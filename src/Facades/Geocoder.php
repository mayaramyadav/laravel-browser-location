<?php

declare(strict_types=1);

namespace Mayaram\BrowserLocation\Facades;

use Illuminate\Support\Facades\Facade;
use Mayaram\BrowserLocation\Contracts\Geocoder as GeocoderContract;

/**
 * @method static array<string, mixed> reverse(float $latitude, float $longitude, array<string, mixed> $options = [])
 * @method static array<string, mixed> forward(string $address, array<string, mixed> $options = [])
 */
class Geocoder extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return GeocoderContract::class;
    }
}
