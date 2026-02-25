<?php

declare(strict_types=1);

namespace Mayaram\BrowserLocation\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Mayaram\BrowserLocation\Exceptions\LocationPersistenceException;
use Mayaram\BrowserLocation\LocationPersister;

class StoreBrowserLocationController extends Controller
{
    public function __construct(
        private readonly LocationPersister $persister
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! (bool) config('browser-location.auto_save', true)) {
            return response()->json([
                'saved' => false,
                'reason' => 'auto_save_disabled',
            ], 202);
        }

        $preparedPayload = $request->attributes->get('browser_location');

        if (! is_array($preparedPayload)) {
            return response()->json([
                'saved' => false,
                'reason' => 'invalid_payload',
            ], 422);
        }

        $collection = $this->resolveCollectionName($request);

        if ($collection === null) {
            return response()->json([
                'saved' => false,
                'reason' => 'invalid_collection_name',
            ], 422);
        }

        $rawMeta = Arr::get($preparedPayload, 'meta', []);
        $rawBrowserGps = $request->input('raw_browser_gps');

        $mergedMeta = array_merge(
            is_array($rawMeta) ? $rawMeta : [],
            [
                'raw_browser_gps' => is_array($rawBrowserGps) ? $rawBrowserGps : null,
            ]
        );

        $payloadForStorage = array_merge($preparedPayload, [
            'meta' => $mergedMeta,
            'address' => $request->input('address'),
        ]);

        $locationable = $this->resolveLocationable($request);

        if (! $locationable && ! (bool) config('browser-location.allow_anonymous_capture', true)) {
            return response()->json([
                'saved' => false,
                'reason' => 'anonymous_capture_not_allowed',
            ], 401);
        }

        try {
            if ($locationable) {
                $location = method_exists($locationable, 'addLocation')
                    ? $locationable->addLocation($payloadForStorage)->toLocationCollection($collection)
                    : $this->persister->persist($locationable, $payloadForStorage, $collection, false);
            } else {
                $location = $this->persister->persistAnonymous($payloadForStorage, $collection);
            }
        } catch (LocationPersistenceException $exception) {
            return response()->json([
                'saved' => false,
                'reason' => $exception->getMessage(),
            ], 202);
        }

        return response()->json([
            'saved' => true,
            'location_id' => $location->getKey(),
            'collection_name' => $location->collection_name,
        ], 201);
    }

    private function resolveLocationable(Request $request): ?Model
    {
        $user = $request->user();

        $allowClientOverride = (bool) config('browser-location.allow_client_locationable_override', false);

        if ($allowClientOverride && $user instanceof Model) {
            $overrideTarget = $this->resolveClientLocationableOverride($request, $user);

            if ($overrideTarget instanceof Model) {
                return $overrideTarget;
            }
        }

        if (! (bool) config('browser-location.storage.attach_authenticated_user', true)) {
            return null;
        }

        return $user instanceof Model ? $user : null;
    }

    private function resolveClientLocationableOverride(Request $request, Model $user): ?Model
    {
        $modelClassRaw = $request->input('locationable_type');
        $modelClass = is_string($modelClassRaw) ? trim($modelClassRaw) : '';

        if ($modelClass === '' || strlen($modelClass) > 191) {
            return null;
        }

        $modelKey = $request->input('locationable_id');
        if ($modelKey === null) {
            return null;
        }

        if (is_string($modelKey)) {
            $modelKey = trim($modelKey);

            if ($modelKey === '' || strlen($modelKey) > 128) {
                return null;
            }
        }

        if (! is_string($modelKey) && ! is_int($modelKey)) {
            return null;
        }

        $allowedModels = config('browser-location.allowed_locationable_models', []);
        if (! is_array($allowedModels) || ! in_array($modelClass, $allowedModels, true)) {
            return null;
        }

        if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            return null;
        }

        /** @var class-string<Model> $modelClass */
        $model = $modelClass::query()->find($modelKey);

        if (! $model instanceof Model) {
            return null;
        }

        if (method_exists($user, 'is') && $user->is($model)) {
            return $model;
        }

        if (method_exists($user, 'can') && ($user->can('view', $model) || $user->can('update', $model))) {
            return $model;
        }

        return null;
    }

    private function resolveCollectionName(Request $request): ?string
    {
        $defaultCollection = trim((string) config('browser-location.default_collection', 'default'));
        $raw = $request->input('collection_name', $defaultCollection);
        $collection = is_scalar($raw) ? trim((string) $raw) : '';

        if ($collection === '') {
            $collection = $defaultCollection !== '' ? $defaultCollection : 'default';
        }

        if (strlen($collection) > 100) {
            return null;
        }

        return Str::of($collection)->match('/^[A-Za-z0-9._:-]+$/')->isNotEmpty()
            ? $collection
            : null;
    }
}
