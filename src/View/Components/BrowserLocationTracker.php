<?php

declare(strict_types=1);

namespace Mayaram\BrowserLocation\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\View\Component;

class BrowserLocationTracker extends Component
{
    public string $componentId;

    public ?string $endpoint;

    public string $buttonText;

    public bool $autoCapture;

    public bool $watch;

    public bool $autoSave;

    public ?string $livewireMethod;

    public float $requiredAccuracyMeters;

    public int $timeout;

    public int $maximumAge;

    public bool $enableHighAccuracy;

    public string $eventName;

    public string $errorEventName;

    public string $permissionEventName;

    public function __construct(
        ?string $endpoint = null,
        string $buttonText = '',
        bool $autoCapture = false,
        bool $watch = false,
        bool $autoSave = true,
        ?string $livewireMethod = null,
        ?float $requiredAccuracyMeters = null,
        ?int $timeout = null,
        ?int $maximumAge = null,
        ?bool $enableHighAccuracy = null,
        string $eventName = 'browser-location:updated',
        string $errorEventName = 'browser-location:error',
        string $permissionEventName = 'browser-location:permission'
    ) {
        $this->componentId = 'browser-location-'.Str::ulid();

        $this->endpoint = $endpoint
            ?? (Route::has('browser-location.capture') ? route('browser-location.capture') : null);

        $this->buttonText = $buttonText !== ''
            ? $buttonText
            : (string) config('browser-location.component.button_text', 'Share GPS location');

        $this->autoCapture = $autoCapture || (bool) config('browser-location.component.auto_capture', false);
        $this->watch = $watch || (bool) config('browser-location.component.watch', false);
        $this->autoSave = $autoSave && (bool) config('browser-location.component.auto_save', true);
        $this->livewireMethod = $livewireMethod ?? config('browser-location.component.livewire_method');
        $this->requiredAccuracyMeters = $requiredAccuracyMeters
            ?? (float) config('browser-location.validation.max_accuracy_meters', 200);
        $this->timeout = $timeout ?? (int) config('browser-location.defaults.timeout', 12000);
        $this->maximumAge = $maximumAge ?? (int) config('browser-location.defaults.maximum_age', 0);
        $this->enableHighAccuracy = $enableHighAccuracy ?? (bool) config('browser-location.defaults.enable_high_accuracy', true);
        $this->eventName = $eventName;
        $this->errorEventName = $errorEventName;
        $this->permissionEventName = $permissionEventName;
    }

    public function render(): View
    {
        return view('browser-location::components.tracker');
    }
}
