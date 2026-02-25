<?php

declare(strict_types=1);

namespace Mayaram\BrowserLocation\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\View\Component;

class BrowserLocationTracker extends Component
{
    public string $componentId;

    public string $buttonText;

    public bool $autoCapture;

    public bool $forcePermission;

    public bool $watch;

    public ?string $livewireMethod;

    public float $requiredAccuracyMeters;

    public int $timeout;

    public int $maximumAge;

    public bool $enableHighAccuracy;

    public string $eventName;

    public string $errorEventName;

    public string $permissionEventName;

    public bool $autoSave;

    public string $captureEndpoint;

    public ?string $locationableType;

    public string|int|null $locationableId;

    public string $collectionName;

    public function __construct(
        string $buttonText = '',
        ?bool $autoCapture = null,
        ?bool $forcePermission = null,
        ?bool $watch = null,
        ?string $livewireMethod = null,
        ?float $requiredAccuracyMeters = null,
        ?int $timeout = null,
        ?int $maximumAge = null,
        ?bool $enableHighAccuracy = null,
        string $eventName = 'browser-location:updated',
        string $errorEventName = 'browser-location:error',
        string $permissionEventName = 'browser-location:permission',
        ?bool $autoSave = null,
        ?string $captureEndpoint = null,
        ?string $locationableType = null,
        string|int|null $locationableId = null,
        ?string $collectionName = null
    ) {
        $this->componentId = 'browser-location-'.Str::ulid();

        $this->buttonText = $buttonText !== ''
            ? $buttonText
            : (string) config('browser-location.component.button_text', 'Share GPS location');

        $this->autoCapture = $autoCapture ?? (bool) config('browser-location.component.auto_capture', true);
        $this->forcePermission = $forcePermission ?? (bool) config('browser-location.component.force_permission', true);
        $this->watch = $watch ?? (bool) config('browser-location.component.watch', false);
        $this->livewireMethod = $livewireMethod ?? config('browser-location.component.livewire_method');
        $this->requiredAccuracyMeters = $requiredAccuracyMeters
            ?? (float) config('browser-location.validation.max_accuracy_meters', 200);
        $this->timeout = $timeout ?? (int) config('browser-location.defaults.timeout', 12000);
        $this->maximumAge = $maximumAge ?? (int) config('browser-location.defaults.maximum_age', 0);
        $this->enableHighAccuracy = $enableHighAccuracy ?? (bool) config('browser-location.defaults.enable_high_accuracy', true);
        $this->eventName = $eventName;
        $this->errorEventName = $errorEventName;
        $this->permissionEventName = $permissionEventName;
        $this->autoSave = $autoSave ?? (bool) config('browser-location.auto_save', true);
        $this->captureEndpoint = $captureEndpoint ?? (string) config('browser-location.capture_endpoint', '/browser-location/capture');
        $this->collectionName = $collectionName ?? (string) config('browser-location.default_collection', 'default');

        $resolvedLocationable = $this->resolveLocationable();
        $this->locationableType = $locationableType ?? $resolvedLocationable?->getMorphClass();
        $this->locationableId = $locationableId ?? $resolvedLocationable?->getKey();
    }

    public function render(): View
    {
        return view('browser-location::components.tracker');
    }

    private function resolveLocationable(): ?Model
    {
        if (! (bool) config('browser-location.storage.attach_authenticated_user', true)) {
            return null;
        }

        $user = auth()->user();

        return $user instanceof Model ? $user : null;
    }
}
