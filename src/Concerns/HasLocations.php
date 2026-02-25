<?php

declare(strict_types=1);

namespace Mayaram\BrowserLocation\Concerns;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Mayaram\BrowserLocation\Models\BrowserLocation;
use Mayaram\BrowserLocation\PendingLocation;

trait HasLocations
{
    public function locations(): MorphMany
    {
        return $this->morphMany(BrowserLocation::class, 'locationable');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addLocation(array $data): PendingLocation
    {
        return new PendingLocation($this, $data);
    }

    public function getLocations(string $collection = 'default'): Collection
    {
        return $this->locations()
            ->where('collection_name', $collection)
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->get();
    }

    public function getLatestLocation(string $collection = 'default'): ?BrowserLocation
    {
        return $this->locations()
            ->where('collection_name', $collection)
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->first();
    }
}
