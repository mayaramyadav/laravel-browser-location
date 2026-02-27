<?php

declare(strict_types=1);

namespace Mayaram\BrowserLocation\Livewire\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Mayaram\BrowserLocation\Exceptions\LocationPersistenceException;
use Throwable;

/**
 * Adds browser geolocation support to a Livewire component.
 *
 * Works with plain Blade, Livewire 3, and Livewire 4.
 *
 * ## Quick start
 *
 * 1. Add this trait to your Livewire component.
 * 2. Place `<x-browser-location-tracker />` inside the component's Blade view.
 * 3. Optionally implement `onBrowserLocationUpdated(array $location): void`
 *    to react when the browser sends coordinates.
 *
 * ## Livewire 3 & 4 – recommended attributes
 *
 * Add these PHP 8 attributes **on your component class** (not here) when you use Livewire:
 *
 * ```php
 * use Livewire\Attributes\Locked;   // prevents client-side tampering
 * use Livewire\Attributes\On;        // allows dispatch('browser-location:updated', payload)
 *
 * #[Locked]                          // on the property (copy it to your component)
 * public array $browserLocation = [];
 *
 * #[On('browser-location:updated')] // on the method (copy it to your component)
 * public function setBrowserLocation(array $location): void { ... }
 * ```
 *
 * @method void onBrowserLocationUpdated(array $location) Optional hook called after every update.
 * @method Model|null getBrowserLocationable() Override to return the model to attach the location to.
 */
trait InteractsWithBrowserLocation
{
    /**
     * Raw location payload sent by the browser JS tracker.
     *
     * Keys: latitude, longitude, accuracy_meters, accuracy_level,
     *       is_accurate, permission_state, source, captured_at, meta.
     *
     * @var array<string, mixed>
     */
    public array $browserLocation = [];

    // -------------------------------------------------------------------------
    // Primary action – called by the browser JS tracker
    // -------------------------------------------------------------------------

    /**
     * Receives the location payload from the browser and stores it.
     *
     * Called automatically by the `<x-browser-location-tracker>` component via
     * Livewire's JS `component.call()` mechanism (works in Livewire 3 and 4).
     *
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

    /**
     * Clears the stored location (e.g. after processing it).
     */
    public function resetBrowserLocation(): void
    {
        $this->browserLocation = [];
    }

    // -------------------------------------------------------------------------
    // Coordinate helpers
    // -------------------------------------------------------------------------

    /**
     * Returns true if the browser has already sent valid coordinates.
     */
    public function hasLocation(): bool
    {
        return isset($this->browserLocation['latitude'], $this->browserLocation['longitude'])
            && is_numeric($this->browserLocation['latitude'])
            && is_numeric($this->browserLocation['longitude']);
    }

    /**
     * Returns the captured latitude or null if no location is available.
     */
    public function getLatitude(): ?float
    {
        $value = $this->browserLocation['latitude'] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Returns the captured longitude or null if no location is available.
     */
    public function getLongitude(): ?float
    {
        $value = $this->browserLocation['longitude'] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Returns the GPS accuracy in meters or null.
     */
    public function getAccuracy(): ?float
    {
        $value = $this->browserLocation['accuracy_meters'] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Returns 'excellent', 'good', 'poor', or 'unknown'.
     */
    public function getAccuracyLevel(): string
    {
        return (string) ($this->browserLocation['accuracy_level'] ?? 'unknown');
    }

    // -------------------------------------------------------------------------
    // Permission / error helpers
    // -------------------------------------------------------------------------

    /**
     * Returns the browser permission state: 'granted', 'denied', 'prompt', or null.
     */
    public function getLocationPermission(): ?string
    {
        $state = $this->browserLocation['permission_state'] ?? null;

        return is_string($state) ? $state : null;
    }

    /**
     * Returns true if the browser has denied the geolocation permission.
     */
    public function isLocationDenied(): bool
    {
        return $this->getLocationPermission() === 'denied';
    }

    /**
     * Returns the error code sent by the browser (1 = denied, 2 = unavailable, 3 = timeout).
     */
    public function getLocationErrorCode(): ?int
    {
        $code = $this->browserLocation['error_code'] ?? null;

        return is_numeric($code) ? (int) $code : null;
    }

    /**
     * Returns the human-readable error message sent by the browser.
     */
    public function getLocationErrorMessage(): ?string
    {
        $msg = $this->browserLocation['error_message'] ?? null;

        return is_string($msg) && $msg !== '' ? $msg : null;
    }

    /**
     * Returns the reverse-geocoded address string attached to this location, or null.
     */
    public function getBrowserLocationAddress(): ?string
    {
        $address = $this->browserLocation['address'] ?? null;

        return is_string($address) && $address !== '' ? $address : null;
    }

    // -------------------------------------------------------------------------
    // Accuracy guard
    // -------------------------------------------------------------------------

    /**
     * Returns true when the captured accuracy is within the allowed threshold.
     *
     * @param  float|null  $maxAccuracyMeters  Override the config threshold (meters).
     */
    public function browserLocationIsAccurate(?float $maxAccuracyMeters = null): bool
    {
        $accuracy = $this->getAccuracy();

        if ($accuracy === null) {
            return false;
        }

        $limit = $maxAccuracyMeters ?? (float) config('browser-location.validation.max_accuracy_meters', 200);

        return $accuracy <= $limit;
    }

    // -------------------------------------------------------------------------
    // JSON export
    // -------------------------------------------------------------------------

    /**
     * Returns the raw location payload as a JSON string (useful inside Blade views).
     */
    public function getBrowserLocationJson(int $options = 0): string
    {
        return json_encode($this->browserLocation, $options) ?: '{}';
    }

    // -------------------------------------------------------------------------
    // Internal – auto-save
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $location
     */
    protected function persistBrowserLocationIfNeeded(array &$location): void
    {
        if (! (bool) config('browser-location.auto_save', false)) {
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

    /**
     * Resolves the Eloquent model that locations should be attached to.
     *
     * Override `getBrowserLocationable(): Model` on your component to return a
     * specific model, or set a `public Model $locationable` property.
     * Falls back to the authenticated user when `attach_authenticated_user` is enabled.
     */
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
