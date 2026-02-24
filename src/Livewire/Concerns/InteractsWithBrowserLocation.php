<?php

declare(strict_types=1);

namespace Mayaram\BrowserLocation\Livewire\Concerns;

trait InteractsWithBrowserLocation
{
    /**
     * @var array<string, mixed>
     */
    public array $browserLocation = [];

    /**
     * @param  array<string, mixed>  $location
     */
    public function setBrowserLocation(array $location): void
    {
        $this->browserLocation = $location;

        if (method_exists($this, 'onBrowserLocationUpdated')) {
            $this->onBrowserLocationUpdated($location);
        }
    }

    public function browserLocationIsAccurate(?float $maxAccuracyMeters = null): bool
    {
        $accuracy = $this->browserLocation['accuracy_meters'] ?? null;

        if (! is_numeric($accuracy)) {
            return false;
        }

        $allowedAccuracy = $maxAccuracyMeters ?? (float) config('browser-location.validation.max_accuracy_meters', 200);

        return (float) $accuracy <= $allowedAccuracy;
    }
}
