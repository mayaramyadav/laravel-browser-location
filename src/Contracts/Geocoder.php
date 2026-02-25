<?php

declare(strict_types=1);

namespace Mayaram\BrowserLocation\Contracts;

interface Geocoder
{
    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function reverse(float $latitude, float $longitude, array $options = []): array;

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function forward(string $address, array $options = []): array;
}
