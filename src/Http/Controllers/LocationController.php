<?php

declare(strict_types=1);

namespace Mayaram\BrowserLocation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mayaram\BrowserLocation\BrowserLocation;
use Mayaram\BrowserLocation\Http\Requests\StoreBrowserLocationRequest;
use Mayaram\BrowserLocation\Models\BrowserLocation as BrowserLocationModel;

class LocationController
{
    public function __construct(private readonly BrowserLocation $browserLocation) {}

    public function store(StoreBrowserLocationRequest $request): JsonResponse
    {
        $payload = $this->browserLocation->preparePayload($request->validated());

        if ((bool) config('browser-location.storage.attach_authenticated_user', true) && $request->user() !== null) {
            $payload['user_id'] = $request->user()->getAuthIdentifier();
        }

        $record = null;

        if ((bool) config('browser-location.storage.persist', true)) {
            $record = BrowserLocationModel::query()->create($payload);
        }

        return response()->json([
            'message' => 'Location captured successfully.',
            'saved' => $record !== null,
            'data' => $record?->toArray() ?? $payload,
        ], 201);
    }

    public function latest(Request $request): JsonResponse
    {
        if ((bool) config('browser-location.validation.require_authentication', false) && $request->user() === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $query = BrowserLocationModel::query()->latest('captured_at');

        if ((bool) config('browser-location.storage.attach_authenticated_user', true) && $request->user() !== null) {
            $query->where('user_id', $request->user()->getAuthIdentifier());
        }

        $record = $query->first();

        if ($record === null) {
            return response()->json(['message' => 'No location records found.'], 404);
        }

        return response()->json([
            'message' => 'Latest location fetched successfully.',
            'data' => $record,
        ]);
    }
}
