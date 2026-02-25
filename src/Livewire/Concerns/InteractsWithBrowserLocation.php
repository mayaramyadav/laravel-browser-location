<?php

declare(strict_types=1);

namespace Mayaram\BrowserLocation\Livewire\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Mayaram\BrowserLocation\Exceptions\LocationPersistenceException;
use Throwable;

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
        $this->persistBrowserLocationIfNeeded($location);
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

    public function getBrowserLocationJson(int $options = 0): string
    {
        return json_encode($this->browserLocation, $options) ?: '{}';
    }

    /**
     * @param  array<string, mixed>  $location
     */
    protected function persistBrowserLocationIfNeeded(array &$location): void
    {
        if (! (bool) config('browser-location.auto_save', true)) {
            return;
        }

        if ((bool) Arr::get($location, 'meta.persistence.saved', false)) {
            return;
        }

        $locationable = $this->resolveBrowserLocationableModel();

        if (! $locationable || ! method_exists($locationable, 'addLocation')) {
            return;
        }

        try {
            $saved = $locationable->addLocation($location)->toLocationCollection(
                (string) config('browser-location.default_collection', 'default')
            );
        } catch (LocationPersistenceException) {
            return;
        } catch (Throwable) {
            return;
        }

        if (! isset($location['meta']) || ! is_array($location['meta'])) {
            $location['meta'] = [];
        }

        $location['meta']['persistence'] = [
            'saved' => true,
            'id' => $saved->getKey(),
            'collection_name' => $saved->collection_name,
        ];
    }

    protected function resolveBrowserLocationableModel(): ?Model
    {
        if (method_exists($this, 'getBrowserLocationable')) {
            $model = $this->getBrowserLocationable();

            if ($model instanceof Model) {
                return $model;
            }
        }

        if (property_exists($this, 'locationable') && $this->locationable instanceof Model) {
            return $this->locationable;
        }

        if ((bool) config('browser-location.storage.attach_authenticated_user', true)) {
            $user = auth()->user();

            if ($user instanceof Model) {
                return $user;
            }
        }

        return null;
    }
}
