# Laravel Browser Location

Capture browser-based GPS location in Laravel using the HTML5 Geolocation API.

## Core features

- Accurate GPS capture with HTML5 Geolocation
- Livewire-friendly component and event bridge
- Ready-to-use Blade component (`<x-browser-location-tracker />`)
- SPA-friendly API capture with `fetch` (no page reload)
- REST API endpoints for web and mobile frontends
- Permission + error handling for denied/timeout/unavailable states
- Accuracy detection (`excellent`, `good`, `poor`, `unknown`)
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

## Blade component

Add the tracker anywhere in your Blade view:

```blade
<x-browser-location-tracker />
```

Customize behavior:

```blade
<x-browser-location-tracker
    button-text="Use my current location"
    :auto-capture="true"
    :watch="false"
    :auto-save="true"
    livewire-method="setBrowserLocation"
    :required-accuracy-meters="80"
/>
```

Component events dispatched in the browser:

- `browser-location:updated`
- `browser-location:error`
- `browser-location:permission`

## Livewire 4 integration

Use the provided trait in your Livewire component:

```php
<?php

namespace App\Livewire;

use Livewire\Component;
use Mayaram\BrowserLocation\Livewire\Concerns\InteractsWithBrowserLocation;

class CheckoutLocation extends Component
{
    use InteractsWithBrowserLocation;

    public function onBrowserLocationUpdated(array $location): void
    {
        // Optional hook called after setBrowserLocation().
    }
}
```

Then include the tracker in the component view:

```blade
<x-browser-location-tracker livewire-method="setBrowserLocation" />
```

## REST API

Default endpoints:

- `POST /api/browser-location/capture`
- `GET /api/browser-location/latest`

Capture example:

```bash
curl -X POST http://localhost/api/browser-location/capture \
  -H "Content-Type: application/json" \
  -d '{
    "latitude": 12.9715987,
    "longitude": 77.5945627,
    "accuracy_meters": 15.2,
    "permission_state": "granted"
  }'
```

## Middleware

Use `browser-location.validate` on protected routes:

```php
use Illuminate\Support\Facades\Route;

Route::post('/checkout', CheckoutController::class)
    ->middleware('browser-location.validate');
```

Middleware accepts payload from request body, `location` object, or `X-Browser-Location` JSON header.

## Configuration

Publish (if not already published):

```bash
php artisan vendor:publish --tag=browser-location-config
```

Main config file: `config/browser-location.php`

Key options:

- API route prefix + middleware
- Accuracy thresholds + maximum accepted meters
- Required location/auth settings
- Storage persistence and precision
- Component defaults (auto capture, watch mode, Livewire method)

## Testing

```bash
composer test
```

## License

MIT
