# Laravel Browser Location

Capture browser-based GPS location in Laravel using the HTML5 Geolocation API.

## Core features

- Accurate GPS capture with HTML5 Geolocation
- Livewire-friendly component and event bridge
- Ready-to-use Blade component (`<x-browser-location-tracker />`)
- SPA-friendly integration with Livewire navigation (no page reload)
- Permission + error handling for denied/timeout/unavailable states
- Accuracy detection (`excellent`, `good`, `poor`, `unknown`)
- Provider-based geocoding + reverse geocoding (Google, Mapbox, OpenStreetMap)
- Cache-ready geocoder responses for fast repeated lookups
- Spatie-style location collections with polymorphic model ownership
- Automatic DB persistence from tracker capture (`JS -> API -> Laravel`)
- Auto-loaded package migration for `browser_locations` table
- One-command package setup (`php artisan browser-location:install`)
- Config-driven behavior for API, validation, capture options, and storage
- Middleware alias for route-level location validation (`browser-location.validate`)

## Installation

```bash
composer require mayaram/laravel-browser-location
```

Run the installer command:

```bash
php artisan browser-location:install
```

This command publishes config/views/migrations and runs migrations.

## Usage: Blade component

Add the tracker anywhere in your Blade view. The easiest way is using the defaults:

```blade
<x-browser-location-tracker />
```

> **Note:** By default, the component sets `auto-capture="true"` and `force-permission="true"`. This means as soon as the component loads, the browser will automatically request the user's location. If you don't want this to happen, explicitly set them to `false`.

### All Package Options (Component Props)

You can customize the component behavior by passing any of these props. Most default values come directly from your `config/browser-location.php` file:

| Prop                       | Type   | Default                         | Description                                                                                          |
| -------------------------- | ------ | ------------------------------- | ---------------------------------------------------------------------------------------------------- |
| `button-text`              | string | `'Share GPS location'`          | Text used if you trigger location capture via a button click.                                        |
| `auto-capture`             | bool   | `true`                          | When `true`, automatically requests location on page load and Livewire navigation.                   |
| `force-permission`         | bool   | `true`                          | When `true`, forces the permission prompt on page load and blocks UI until granted.                  |
| `watch`                    | bool   | `false`                         | When `true`, continually watches and updates location using `navigator.geolocation.watchPosition()`. |
| `livewire-method`          | string | `'setBrowserLocation'`          | The Livewire method to call on success (if inside a Livewire component).                             |
| `required-accuracy-meters` | float  | `200`                           | Validates if the reading is accurate enough.                                                         |
| `enable-high-accuracy`     | bool   | `true`                          | Requests the most accurate reading possible from the device GPS.                                     |
| `timeout`                  | int    | `12000`                         | Milliseconds the browser waits to get the location before timing out.                                |
| `maximum-age`              | int    | `0`                             | Milliseconds a cached location is considered valid (`0` enforces a fresh reading).                   |
| `auto-save`                | bool   | `true`                          | Automatically sends successful captures to the package save endpoint.                                 |
| `capture-endpoint`         | string | `'/browser-location/capture'`   | Endpoint used by JS for automatic persistence.                                                        |
| `collection-name`          | string | `'default'`                     | Target location collection name for automatic save.                                                   |
| `locationable-type`        | string | `auth user morph class`         | Optional explicit location owner model class (must be allow-listed).                                 |
| `locationable-id`          | mixed  | `auth user key`                 | Optional explicit location owner key.                                                                 |
| `event-name`               | string | `'browser-location:updated'`    | The JavaScript event dispatched on successful capture.                                               |
| `error-event-name`         | string | `'browser-location:error'`      | The JavaScript event dispatched on error.                                                            |
| `permission-event-name`    | string | `'browser-location:permission'` | The JavaScript event dispatched when permission state changes.                                       |

**Example configuration overriding defaults:**

```blade
<x-browser-location-tracker
    button-text="Locate Me"
    :auto-capture="false"
    :force-permission="false"
    :watch="true"
    :timeout="10000"
    :enable-high-accuracy="true"
    livewire-method="saveLocation"
    :required-accuracy-meters="80"
/>
```

Component events dispatched natively in the browser on the `document`:

- `browser-location:updated` (Payload contains location details)
- `browser-location:error` (Payload contains error code and message)
- `browser-location:permission` (Payload contains permission state)
- `browser-location:saved` (Payload contains persistence result from package endpoint)
- `browser-location:save-error` (Payload contains persistence request error details)

### Javascript API

The tracker exposes a global `window.BrowserLocationTracker` object that you can use to programmatically interact with the component:

- `window.BrowserLocationTracker.getJson()`: Returns the latest captured location data as a JSON string.
- `window.BrowserLocationTracker.requestPermission()`: Programmatically triggers the browser's location permission prompt and captures location.

## Configuration

Publish the config file if you want to modify package-wide defaults:

```bash
php artisan vendor:publish --tag=browser-location-config
```

This creates `config/browser-location.php`. Here you can configure:

- Accuracy thresholds + maximum accepted meters
- Component defaults (auto capture, force permission, watch mode, Livewire method)
- Required location/auth settings for middleware
- Storage persistence and precision
- Geocoder provider switch, API credentials, timeouts, retries, and cache behavior

## Geocoder service (Google / Mapbox / OpenStreetMap)

The package ships with a production-ready geocoder service with:

- Config-driven provider switching
- Optional cache storage and TTL controls
- Unified response shape across providers
- Container binding + facade access
- Built-in request timeouts/retries and provider-level validation

### Configure provider in `.env`

```dotenv
# Provider: google | mapbox | openstreetmap
BROWSER_LOCATION_GEOCODER_PROVIDER=openstreetmap

# Google
BROWSER_LOCATION_GOOGLE_API_KEY=

# Mapbox
BROWSER_LOCATION_MAPBOX_ACCESS_TOKEN=

# OpenStreetMap (recommended for Nominatim policy compliance)
BROWSER_LOCATION_OSM_USER_AGENT="your-app-name/1.0 (admin@example.com)"
BROWSER_LOCATION_OSM_EMAIL=admin@example.com

# Cache
BROWSER_LOCATION_GEOCODER_CACHE_ENABLED=true
BROWSER_LOCATION_GEOCODER_CACHE_TTL=3600
```

### Usage via facade

```php
<?php

use Mayaram\BrowserLocation\Facades\Geocoder;

$reverse = Geocoder::reverse(28.6139, 77.2090);

$forward = Geocoder::forward('New Delhi, India', [
    'limit' => 3,
    'language' => 'en',
]);
```

### Usage via dependency injection (preferred)

```php
<?php

namespace App\Http\Controllers;

use Mayaram\BrowserLocation\Contracts\Geocoder;

class CheckoutController
{
    public function __construct(private readonly Geocoder $geocoder)
    {
    }

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
    'query' => [...],
    'resolved' => [
        'formatted_address' => '...',
        'latitude' => 28.6139,
        'longitude' => 77.209,
        'place_id' => '...',
        'components' => [...],
    ],
    'results' => [...],
    'raw' => [...],
]
```

### Best practices

- Prefer dependency injection (`Mayaram\BrowserLocation\Contracts\Geocoder`) for testability.
- Keep API credentials in environment variables only; never hardcode keys in source control.
- Enable cache in production to reduce provider cost and improve p95 latency.
- Set strict timeout/retry values to avoid slow request fan-out.
- For OpenStreetMap/Nominatim, always provide a real `user_agent` and contact email.
- Catch `Mayaram\BrowserLocation\Exceptions\GeocoderException` at your application boundary and return user-safe errors.

## Location persistence collections

The package stores captured locations in `browser_locations` with polymorphic ownership and collection names.

### 1) Add the `HasLocations` trait to your model

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
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

When `<x-browser-location-tracker />` captures location, the package:

1. Sends payload to `POST /browser-location/capture`
2. Validates via `browser-location.validate`
3. Applies quality checks (accuracy + anti-duplicate rules)
4. Persists in `browser_locations`

The endpoint also enriches `meta` with:

- Raw browser GPS payload
- Raw geocoder payload
- Request IP and user-agent
- Timestamp and app metadata

### Persistence config

```php
// config/browser-location.php
'auto_save' => true,
'min_accuracy' => 200,
'prevent_duplicates' => true,
'default_collection' => 'default',
'capture_endpoint' => '/browser-location/capture',
```

### Optional explicit locationable models

For security, explicit `locationable_type` + `locationable_id` are only accepted if class is allow-listed:

```php
'allowed_locationable_models' => [
    App\Models\Order::class,
],
```

## Livewire 4 integration

Use the provided trait in your Livewire component to automatically handle location updates:

```php
<?php

namespace App\Livewire;

use Livewire\Component;
use Mayaram\BrowserLocation\Livewire\Concerns\InteractsWithBrowserLocation;

class CheckoutLocation extends Component
{
    use InteractsWithBrowserLocation;

    // Optional: return the model to attach persisted locations to.
    public function getBrowserLocationable(): ?\Illuminate\Database\Eloquent\Model
    {
        return auth()->user();
    }

    public function onBrowserLocationUpdated(array $location): void
    {
        // Optional hook called after setBrowserLocation() updates the location state.
        // You can access $location['latitude'], $location['longitude'], etc.
    }
}
```

Then include the tracker in your component's blade view:

```blade
<x-browser-location-tracker livewire-method="setBrowserLocation" />
```

`x-browser-location-tracker` renders with `wire:ignore` so re-renders do not interrupt the browser permission / capture lifecycle.

## Middleware Validation

Use `browser-location.validate` on protected routes to ensure a location is attached:

```php
use Illuminate\Support\Facades\Route;

Route::post('/checkout', CheckoutController::class)
    ->middleware('browser-location.validate');
```

The middleware accepts the location payload from the request body, a `location` object, or an `X-Browser-Location` JSON header.

## Testing

```bash
composer test
```

## License

MIT
