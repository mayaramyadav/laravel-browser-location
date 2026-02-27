# Laravel Browser Location

Capture browser-based GPS location in Laravel using the HTML5 Geolocation API.  
Works with **plain Blade**, **Livewire 3**, and **Livewire 4**.

## Table of Contents

- [Core features](#core-features)
- [Installation](#installation)
- [Usage: Blade component](#usage-blade-component)
    - [All Component Props](#all-component-props)
    - [Browser Events](#browser-events)
    - [JavaScript API](#javascript-api)
- [Configuration](#configuration)
- [Geocoder service (Google / Mapbox / OpenStreetMap)](#geocoder-service-google--mapbox--openstreetmap)
    - [Configure provider in `.env`](#configure-provider-in-env)
    - [Usage via facade](#usage-via-facade)
    - [Usage via dependency injection (preferred)](#usage-via-dependency-injection-preferred)
    - [Normalized response shape](#normalized-response-shape)
    - [Best practices](#best-practices)
- [Location persistence collections](#location-persistence-collections)
    - [1) Add the `HasLocations` trait to your model](#1-add-the-haslocations-trait-to-your-model)
    - [2) Save locations manually (Spatie-style API)](#2-save-locations-manually-spatie-style-api)
    - [3) Read stored locations](#3-read-stored-locations)
    - [4) Automatic saving flow (no manual save required)](#4-automatic-saving-flow-no-manual-save-required)
    - [Persistence config](#persistence-config)
    - [Optional explicit locationable models](#optional-explicit-locationable-models)
- [Livewire integration (v3 & v4)](#livewire-integration-v3--v4)
    - [Basic setup](#basic-setup)
    - [Trait helper methods](#trait-helper-methods)
    - [Livewire 3 & 4 — recommended attributes](#livewire-3--4--recommended-attributes)
    - [Livewire 4: using \#\[On\] to react via JS dispatch](#livewire-4-using-on-to-react-via-js-dispatch)
- [Compatibility matrix](#compatibility-matrix)
- [Middleware Validation](#middleware-validation)
- [Testing](#testing)
- [License](#license)

---

## Core features

- Accurate GPS capture with HTML5 Geolocation
- Works with **plain Blade, Livewire 3, and Livewire 4** — Livewire is fully optional
- Ready-to-use Blade component (`<x-browser-location-tracker />`)
- SPA-friendly: re-captures after every Livewire navigation without page reload
- Permission + error handling for denied / timeout / unavailable states
- Accuracy detection (`excellent`, `good`, `poor`, `unknown`)
- Typed PHP helper methods on the Livewire trait
- Provider-based geocoding + reverse geocoding (Google, Mapbox, OpenStreetMap)
- Cache-ready geocoder responses for fast repeated lookups
- Spatie-style location collections with polymorphic model ownership
- Automatic DB persistence (`JS → API → Laravel`)
- Auto-loaded migration for `browser_locations` table
- One-command setup: `php artisan browser-location:install`
- Middleware alias for route-level location validation (`browser-location.validate`)

---

## Installation

```bash
composer require mayaram/laravel-browser-location
```

Run the installer command:

```bash
php artisan browser-location:install
```

This publishes config / views / migrations and runs migrations automatically.

---

## Usage: Blade component

Drop the tracker anywhere in any Blade view — including inside a Livewire component:

```blade
<x-browser-location-tracker />
```

> **Default behaviour:** `auto-capture="true"` and `force-permission="true"` are on by default, so the browser will request the user's location immediately. Set them to `false` if you want manual control.

### All Component Props

| Prop                       | Type   | Default                         | Description                                                                         |
| -------------------------- | ------ | ------------------------------- | ----------------------------------------------------------------------------------- |
| `button-text`              | string | `'Share GPS location'`          | Label for the (hidden by default) trigger button.                                   |
| `auto-capture`             | bool   | `true`                          | Requests location on page load and after every Livewire navigation.                 |
| `force-permission`         | bool   | `true`                          | Shows a full-screen overlay until the user grants permission.                       |
| `watch`                    | bool   | `false`                         | Continuously tracks position using `watchPosition()`.                               |
| `livewire-method`          | string | `'setBrowserLocation'`          | Livewire component method called on successful capture. Leave empty in plain Blade. |
| `required-accuracy-meters` | float  | `200`                           | Threshold for the `is_accurate` flag in the payload.                                |
| `enable-high-accuracy`     | bool   | `true`                          | Requests the most accurate reading from the device GPS.                             |
| `timeout`                  | int    | `12000`                         | Milliseconds before the location request times out.                                 |
| `maximum-age`              | int    | `0`                             | Milliseconds a cached location is considered fresh (`0` = always fresh).            |
| `auto-save`                | bool   | `true`                          | Automatically POSTs captures to the package save endpoint.                          |
| `capture-endpoint`         | string | `'/browser-location/capture'`   | Endpoint used by JS for automatic persistence.                                      |
| `collection-name`          | string | `'default'`                     | Target location collection for automatic save.                                      |
| `locationable-type`        | string | auth user morph class           | Override the model class that owns the location (must be allow-listed).             |
| `locationable-id`          | mixed  | auth user key                   | Override the model key.                                                             |
| `event-name`               | string | `'browser-location:updated'`    | JS event dispatched on successful capture.                                          |
| `error-event-name`         | string | `'browser-location:error'`      | JS event dispatched on error.                                                       |
| `permission-event-name`    | string | `'browser-location:permission'` | JS event dispatched when permission state changes.                                  |

**Example with custom options:**

```blade
<x-browser-location-tracker
    button-text="Locate Me"
    :auto-capture="false"
    :force-permission="false"
    :watch="true"
    :timeout="10000"
    livewire-method="saveLocation"
    :required-accuracy-meters="80"
/>
```

### Browser Events

The tracker dispatches these native DOM events on `document`:

| Event                         | Description                                                    |
| ----------------------------- | -------------------------------------------------------------- |
| `browser-location:updated`    | Successful capture — payload contains full location data       |
| `browser-location:error`      | Error — payload contains `code` and `message`                  |
| `browser-location:permission` | Permission state changed — payload contains `state`            |
| `browser-location:saved`      | Location persisted to DB — payload contains persistence result |
| `browser-location:save-error` | Persistence request failed                                     |

```js
document.addEventListener("browser-location:updated", (e) => {
    console.log(e.detail.latitude, e.detail.longitude);
});
```

### JavaScript API

```js
// Last-initialized tracker on the page:
window.BrowserLocationTracker.capture(); // Trigger a fresh capture
window.BrowserLocationTracker.requestPermission(); // Alias for capture()
window.BrowserLocationTracker.getJson(); // Returns latest data as JSON string

// Named instance (if you have multiple trackers):
window.BrowserLocation["browser-location-<ulid>"].capture();
```

---

## Configuration

```bash
php artisan vendor:publish --tag=browser-location-config
```

Creates `config/browser-location.php`. Key options:

```php
'auto_save'          => true,    // persist every capture automatically
'min_accuracy'       => 200,     // max accepted accuracy in metres
'prevent_duplicates' => true,    // skip saves within 20 m of last point
'default_collection' => 'default',

'component' => [
    'auto_capture'    => true,
    'force_permission' => true,
    'watch'           => false,
    'livewire_method' => 'setBrowserLocation',
],
```

---

## Geocoder service (Google / Mapbox / OpenStreetMap)

### Configure provider in `.env`

```dotenv
# Provider: google | mapbox | openstreetmap
BROWSER_LOCATION_GEOCODER_PROVIDER=openstreetmap

# Google
BROWSER_LOCATION_GOOGLE_API_KEY=

# Mapbox
BROWSER_LOCATION_MAPBOX_ACCESS_TOKEN=

# OpenStreetMap (required for Nominatim policy compliance)
BROWSER_LOCATION_OSM_USER_AGENT="your-app-name/1.0 (admin@example.com)"
BROWSER_LOCATION_OSM_EMAIL=admin@example.com

# Cache
BROWSER_LOCATION_GEOCODER_CACHE_ENABLED=true
BROWSER_LOCATION_GEOCODER_CACHE_TTL=3600
```

### Usage via facade

```php
use Mayaram\BrowserLocation\Facades\Geocoder;

$reverse = Geocoder::reverse(28.6139, 77.2090);
$forward = Geocoder::forward('New Delhi, India');
```

### Usage via dependency injection (preferred)

```php
use Mayaram\BrowserLocation\Contracts\Geocoder;

class CheckoutController
{
    public function __construct(private readonly Geocoder $geocoder) {}

    public function __invoke(): array
    {
        return $this->geocoder->reverse(28.6139, 77.2090);
    }
}
```

### Normalized response shape

```php
[
    'provider' => 'openstreetmap',
    'query'    => [...],
    'resolved' => [
        'formatted_address' => '...',
        'latitude'          => 28.6139,
        'longitude'         => 77.209,
        'place_id'          => '...',
        'components'        => [...],
    ],
    'results' => [...],
    'raw'     => [...],
]
```

### Best practices

- Prefer dependency injection for testability.
- Keep API credentials in `.env` — never commit keys.
- Enable cache in production to reduce cost and latency.
- For OpenStreetMap/Nominatim, always supply a real `user_agent` and contact email.
- Catch `Mayaram\BrowserLocation\Exceptions\GeocoderException` at your application boundary.

---

## Location persistence collections

### 1) Add the `HasLocations` trait to your model

```php
use Mayaram\BrowserLocation\Concerns\HasLocations;

class User extends Authenticatable
{
    use HasLocations;
}
```

### 2) Save locations manually (Spatie-style API)

```php
$user->addLocation($data)->toLocationCollection('checkins');
$order->addLocation($data)->toLocationCollection('delivery');
$user->addLocation($data)->toSingleLocationCollection('live');
```

### 3) Read stored locations

```php
$latest = $user->getLatestLocation();
$visits = $user->getLocations('visits');
```

### 4) Automatic saving flow (no manual save required)

When `<x-browser-location-tracker />` captures a location the package:

1. POSTs coordinates to `POST /browser-location/capture`
2. Validates via `browser-location.validate` middleware
3. Applies quality checks (accuracy threshold + anti-duplicate rules)
4. Persists in `browser_locations` and enriches `meta` with raw GPS, geocoder response, IP, user-agent

### Persistence config

```php
'auto_save'          => true,
'min_accuracy'       => 200,
'prevent_duplicates' => true,
'default_collection' => 'default',
'capture_endpoint'   => '/browser-location/capture',
```

### Optional explicit locationable models

```php
// config/browser-location.php
'allowed_locationable_models' => [
    App\Models\Order::class,
],
```

---

## Livewire integration (v3 & v4)

### Basic setup

**1. Add the trait to your Livewire component:**

```php
<?php

namespace App\Livewire;

use Livewire\Component;
use Mayaram\BrowserLocation\Livewire\Concerns\InteractsWithBrowserLocation;

class TripTracker extends Component
{
    use InteractsWithBrowserLocation;

    public string $status = 'Waiting for location…';

    // Called automatically after every location update
    public function onBrowserLocationUpdated(array $location): void
    {
        $this->status = "Lat: {$this->getLatitude()}, Lng: {$this->getLongitude()}";
    }

    // Optional: return the model that locations should be attached to
    public function getBrowserLocationable(): ?\Illuminate\Database\Eloquent\Model
    {
        return auth()->user();
    }

    public function render()
    {
        return view('livewire.trip-tracker');
    }
}
```

**2. Add the tracker inside the component's Blade view:**

```blade
{{-- livewire/trip-tracker.blade.php --}}
<div>
    <x-browser-location-tracker />   {{-- wire:ignore is applied automatically --}}

    @if ($this->hasLocation())
        <p>Lat: {{ $this->getLatitude() }}, Lng: {{ $this->getLongitude() }}</p>
        <p>Accuracy: {{ $this->getAccuracy() }} m ({{ $this->getAccuracyLevel() }})</p>
    @endif

    <p>{{ $status }}</p>
</div>
```

---

### Trait helper methods

All methods are available on any Livewire component that uses `InteractsWithBrowserLocation`:

#### Coordinate helpers

| Method                                   | Returns  | Description                                                  |
| ---------------------------------------- | -------- | ------------------------------------------------------------ |
| `hasLocation()`                          | `bool`   | `true` once the browser sends valid coordinates              |
| `getLatitude()`                          | `?float` | Captured latitude                                            |
| `getLongitude()`                         | `?float` | Captured longitude                                           |
| `getAccuracy()`                          | `?float` | GPS accuracy in metres                                       |
| `getAccuracyLevel()`                     | `string` | `'excellent'` / `'good'` / `'poor'` / `'unknown'`            |
| `browserLocationIsAccurate(?float $max)` | `bool`   | Whether accuracy is within the given or configured threshold |

#### Permission & error helpers

| Method                      | Returns   | Description                                    |
| --------------------------- | --------- | ---------------------------------------------- |
| `getLocationPermission()`   | `?string` | `'granted'`, `'denied'`, `'prompt'`, or `null` |
| `isLocationDenied()`        | `bool`    | Quick check for denied permission              |
| `getLocationErrorCode()`    | `?int`    | 1 = denied, 2 = unavailable, 3 = timeout       |
| `getLocationErrorMessage()` | `?string` | Human-readable error from the browser          |

#### Other helpers

| Method                                | Description                                          |
| ------------------------------------- | ---------------------------------------------------- |
| `setBrowserLocation(array $location)` | Receives the payload from JS (called automatically)  |
| `resetBrowserLocation()`              | Clears `$browserLocation` (e.g. after saving a trip) |
| `getBrowserLocationJson()`            | Returns the raw payload as a JSON string             |

#### Lifecycle hook (`onBrowserLocationUpdated`)

Implement this method on your component to react every time a new location arrives:

```php
public function onBrowserLocationUpdated(array $location): void
{
    // $location has the same keys as $this->browserLocation
    $this->dispatch('location-saved');
}
```

---

### Livewire 3 & 4 — recommended attributes

The trait itself does not include `#[Locked]` or `#[On]` because Livewire is an **optional** dependency. Add them directly on your component for the best security and DX:

```php
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

// Prevents the client from tampering with $browserLocation via wire:model
#[Locked]
public array $browserLocation = [];

// Allows triggering setBrowserLocation via $dispatch('browser-location:updated', payload)
// in addition to the default direct component.call() mechanism
#[On('browser-location:updated')]
public function setBrowserLocation(array $location): void
{
    // Call the trait's implementation:
    $this->persistBrowserLocationIfNeeded($location);
    $this->browserLocation = $location;

    if (method_exists($this, 'onBrowserLocationUpdated')) {
        $this->onBrowserLocationUpdated($location);
    }
}
```

> Both `#[Locked]` and `#[On]` work identically in Livewire 3 and Livewire 4.

---

### Livewire 4: using `#[On]` to react via JS dispatch

An alternative to having JS call `setBrowserLocation` directly is to dispatch a browser event and let Livewire 4's `#[On]` handle it:

```blade
<x-browser-location-tracker
    livewire-method=""
    event-name="browser-location:updated"
/>
```

```js
// In your own JS you can also do:
Livewire.dispatch("browser-location:updated", payload);
```

```php
#[On('browser-location:updated')]
public function setBrowserLocation(array $location): void { ... }
```

---

## Compatibility matrix

| Feature                                 | Plain Blade | Livewire 3 | Livewire 4 |
| --------------------------------------- | :---------: | :--------: | :--------: |
| `<x-browser-location-tracker>`          |     ✅      |     ✅     |     ✅     |
| Auto-capture on page load               |     ✅      |     ✅     |     ✅     |
| Hidden form inputs                      |     ✅      |     ✅     |     ✅     |
| `wire:ignore` prevents DOM morphing     |      —      |     ✅     |     ✅     |
| JS calls Livewire method on capture     |      —      |     ✅     |     ✅     |
| Re-capture after Livewire navigate      |      —      |     ✅     |     ✅     |
| `InteractsWithBrowserLocation` helpers  |      —      |     ✅     |     ✅     |
| `#[Locked]` / `#[On]` on your component |      —      |     ✅     |     ✅     |

---

## Middleware Validation

```php
Route::post('/checkout', CheckoutController::class)
    ->middleware('browser-location.validate');
```

The middleware accepts location from:

- Request body fields (`latitude`, `longitude`, …)
- A `location` object in the request body
- An `X-Browser-Location` JSON header

---

## Testing

```bash
composer test
```

---

## License

MIT
