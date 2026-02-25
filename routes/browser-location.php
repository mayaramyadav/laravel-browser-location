<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Mayaram\BrowserLocation\Http\Controllers\StoreBrowserLocationController;

$rateLimit = trim((string) config('browser-location.capture_rate_limit', '120,1'));
$middleware = ['web', 'browser-location.validate'];

if ($rateLimit !== '') {
    $middleware[] = 'throttle:'.$rateLimit;
}

Route::middleware($middleware)
    ->post(
        (string) config('browser-location.capture_endpoint', '/browser-location/capture'),
        StoreBrowserLocationController::class
    )
    ->name('browser-location.capture');
