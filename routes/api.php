<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Mayaram\BrowserLocation\Http\Controllers\LocationController;

if (! (bool) config('browser-location.api.enabled', true)) {
    return;
}

Route::group([
    'prefix' => trim((string) config('browser-location.api.prefix', 'api/browser-location'), '/'),
    'middleware' => (array) config('browser-location.api.middleware', ['api']),
], function (): void {
    $captureRoute = Route::post('/capture', [LocationController::class, 'store'])
        ->name('browser-location.capture');

    $captureMiddleware = (array) config('browser-location.api.capture_middleware', ['browser-location.validate']);

    if ($captureMiddleware !== []) {
        $captureRoute->middleware($captureMiddleware);
    }

    Route::get('/latest', [LocationController::class, 'latest'])
        ->name('browser-location.latest');
});
