<?php

declare(strict_types=1);

namespace Mayaram\BrowserLocation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Mayaram\BrowserLocation\BrowserLocation;
use Symfony\Component\HttpFoundation\Response;

class ValidateBrowserLocation
{
    public function __construct(private readonly BrowserLocation $browserLocation) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $payload = $this->extractPayload($request);

        if ($payload === []) {
            if ((bool) config('browser-location.validation.required', false)) {
                throw ValidationException::withMessages([
                    'location' => ['Browser location is required for this route.'],
                ]);
            }

            return $next($request);
        }

        $validated = Validator::make($payload, [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0'],
            'address' => ['nullable', 'string', 'max:500'],
            'permission_state' => ['nullable', 'in:prompt,granted,denied'],
            'error_code' => ['nullable', 'integer', 'between:1,3'],
            'error_message' => ['nullable', 'string', 'max:500'],
            'source' => ['nullable', 'string', 'max:100'],
            'meta' => ['nullable', 'array'],
            'captured_at' => ['nullable', 'date'],
        ])->validate();

        if (! isset($validated['accuracy_meters']) && isset($validated['accuracy'])) {
            $validated['accuracy_meters'] = (float) $validated['accuracy'];
        }

        $accuracy = isset($validated['accuracy_meters']) ? (float) $validated['accuracy_meters'] : null;

        if (! $this->browserLocation->isAccuracyAcceptable($accuracy)) {
            throw ValidationException::withMessages([
                'accuracy_meters' => [
                    sprintf(
                        'Location accuracy must be less than or equal to %s meters.',
                        (string) config('browser-location.validation.max_accuracy_meters', 200)
                    ),
                ],
            ]);
        }

        $request->attributes->set('browser_location', $this->browserLocation->preparePayload($validated));

        return $next($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractPayload(Request $request): array
    {
        $direct = Arr::only($request->all(), [
            'latitude',
            'longitude',
            'accuracy',
            'accuracy_meters',
            'address',
            'permission_state',
            'error_code',
            'error_message',
            'source',
            'meta',
            'captured_at',
        ]);

        if (isset($direct['latitude'], $direct['longitude'])) {
            return $direct;
        }

        $nested = $request->input('location');

        if (is_array($nested) && isset($nested['latitude'], $nested['longitude'])) {
            return Arr::only($nested, array_keys($direct));
        }

        $rawHeader = $request->header('X-Browser-Location');

        if (! is_string($rawHeader) || $rawHeader === '') {
            return [];
        }

        $decoded = json_decode($rawHeader, true);

        return is_array($decoded) ? Arr::only($decoded, array_keys($direct)) : [];
    }
}
