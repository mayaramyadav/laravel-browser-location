# Changelog

All notable changes to `laravel-browser-location` will be documented in this file.

## v2.1.0 - 2026-02-27

**Full Changelog**: https://github.com/mayaramyadav/laravel-browser-location/compare/v2.0.0...v2.1.0

## v2.0.0 - 2026-02-25

**Full Changelog**: https://github.com/mayaramyadav/laravel-browser-location/compare/v1.1.0...v2.0.0

### Breaking Changes

- Database migration now includes polymorphic `locationable_type` and `locationable_id` columns, `collection_name` column, and `address` field. Run `php artisan migrate` to update your database.
- The `browser_locations` table schema has been significantly expanded to support polymorphic ownership and location collections.

### Added

- **Location Persistence Collections**: Store locations in named collections using the `HasLocations` trait
- **Polymorphic Model Ownership**: Attach locations to any Eloquent model (not just users) via polymorphic relationships
- **Spatie-style Location API**: `$model->addLocation($data)->toLocationCollection('name')` for fluent location management
- **Automatic DB Persistence**: Locations automatically persist to database from the tracker component via POST endpoint
- **Location Collections**: Query locations by collection name with `getLocations()` and `getLatestLocation()`
- **Single Collection Mode**: Use `toSingleLocationCollection()` for single-valued location collections (e.g., "live" location)
- **Livewire Trait Enhancements**: `InteractsWithBrowserLocation` now auto-persists locations to the authenticated user
- **HTTP Capture Endpoint**: New `StoreBrowserLocationController` at `/browser-location/capture` for automatic persistence
- **Quality Rules Enforcement**: Automatic duplicate prevention (within 20m) and accuracy validation
- **Extended Metadata**: Rich metadata includes raw GPS payload, geocoder response, request details, and app info
- **Address Geocoding**: Automatic address resolution from coordinates
- **Middleware**: `browser-location.validate` middleware now supports `accuracy` field validation
- **Configuration**: New config options for auto_save, min_accuracy, prevent_duplicates, default_collection, capture_endpoint, and allowed_locationable_models
- **Component Props**: New tracker component props for auto_save, capture_endpoint, collection_name, locationable_type, and locationable_id
- **Wire Ignore**: Tracker component now uses `wire:ignore` for better Livewire compatibility
- **JavaScript Events**: New `browser-location:saved` and `browser-location:save-error` events for persistence feedback
- **Route Registration**: Routes are now auto-loaded from the service provider

### Changed

- Database migration now uses morphs() for polymorphic relationships
- Component configuration now includes persistence options
- BrowserLocation model now supports all new database columns
- Service provider now registers LocationPersister singleton
- Tracker js now sends persistence requests automatically
- Livewire trait now handles both component-level and model-level persistence

## v1.1.0 - 2026-02-25

**Full Changelog**: https://github.com/mayaramyadav/laravel-browser-location/compare/v1.0.3...v1.1.0

## v1.0.3 - 2026-02-24

**Full Changelog**: https://github.com/mayaramyadav/laravel-browser-location/compare/v1.0.2...v1.0.3

## v1.0.2 - 2026-02-24

### What's Changed

* Bump actions/checkout from 4 to 6 by @dependabot[bot] in https://github.com/mayaramyadav/laravel-browser-location/pull/1

### New Contributors

* @dependabot[bot] made their first contribution in https://github.com/mayaramyadav/laravel-browser-location/pull/1

**Full Changelog**: https://github.com/mayaramyadav/laravel-browser-location/compare/v1.0.1...v1.0.2

## 0.1.0 - 2026-02-24

- Added a full Laravel package implementation for browser GPS capture via HTML5 Geolocation.
- Added service provider, install command, facade, middleware, API routes, request validation, and controller.
- Added `browser_locations` migration and Eloquent model with accuracy metadata.
- Added ready-to-use Blade tracker component with Livewire event bridge and SPA-safe API posting.
- Added configurable package settings and package tests using Orchestra Testbench + Pest.
