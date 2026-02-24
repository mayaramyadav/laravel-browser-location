<?php

declare(strict_types=1);

namespace Mayaram\BrowserLocation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class StoreBrowserLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ((bool) config('browser-location.validation.require_authentication', false)) {
            return $this->user() !== null;
        }

        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0'],
            'permission_state' => ['nullable', 'in:prompt,granted,denied'],
            'error_code' => ['nullable', 'integer', 'between:1,3'],
            'error_message' => ['nullable', 'string', 'max:500'],
            'source' => ['nullable', 'string', 'max:100'],
            'meta' => ['nullable', 'array'],
            'captured_at' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('latitude') && $this->has('longitude')) {
            return;
        }

        $location = $this->input('location');

        if (is_array($location) && isset($location['latitude'], $location['longitude'])) {
            $this->merge(Arr::only($location, [
                'latitude',
                'longitude',
                'accuracy_meters',
                'permission_state',
                'error_code',
                'error_message',
                'source',
                'meta',
                'captured_at',
            ]));

            return;
        }

        $rawHeader = $this->header('X-Browser-Location');

        if (! is_string($rawHeader) || $rawHeader === '') {
            return;
        }

        $decoded = json_decode($rawHeader, true);

        if (! is_array($decoded)) {
            return;
        }

        $this->merge(Arr::only($decoded, [
            'latitude',
            'longitude',
            'accuracy_meters',
            'permission_state',
            'error_code',
            'error_message',
            'source',
            'meta',
            'captured_at',
        ]));
    }
}
