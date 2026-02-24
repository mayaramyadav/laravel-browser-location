<div id="{{ $componentId }}" class="browser-location-tracker" data-browser-location-root style="display: none;">
    <button type="button" data-browser-location-trigger style="display: none;">
        {{ $buttonText }}
    </button>

    <p data-browser-location-status aria-live="polite"></p>

    <input type="hidden" name="browser_location[latitude]" data-browser-location-input="latitude">
    <input type="hidden" name="browser_location[longitude]" data-browser-location-input="longitude">
    <input type="hidden" name="browser_location[accuracy_meters]" data-browser-location-input="accuracy_meters">
    <input type="hidden" name="browser_location[accuracy_level]" data-browser-location-input="accuracy_level">
    <input type="hidden" name="browser_location[is_accurate]" data-browser-location-input="is_accurate">
    <input type="hidden" name="browser_location[captured_at]" data-browser-location-input="captured_at">
</div>

<script>
(() => {
    const root = document.getElementById(@js($componentId));

    if (!root || root.dataset.browserLocationInit === '1') {
        return;
    }

    root.dataset.browserLocationInit = '1';

    const trigger = root.querySelector('[data-browser-location-trigger]');
    const status = root.querySelector('[data-browser-location-status]');
    const eventName = @json($eventName);
    const errorEventName = @json($errorEventName);
    const permissionEventName = @json($permissionEventName);
    const autoCapture = @json($autoCapture);
    const forcePermission = @json($forcePermission);
    const watchMode = @json($watch);
    const livewireMethod = @json($livewireMethod);
    const requiredAccuracyMeters = Number(@json($requiredAccuracyMeters));

    const geoOptions = {
        enableHighAccuracy: @json($enableHighAccuracy),
        timeout: Number(@json($timeout)),
        maximumAge: Number(@json($maximumAge)),
    };

    let watchId = null;

    const setStatus = (message) => {
        if (status) {
            status.textContent = message;
        }
    };

    const emit = (name, detail) => {
        const event = new CustomEvent(name, { detail, bubbles: true });

        root.dispatchEvent(event);
        document.dispatchEvent(new CustomEvent(name, { detail }));
    };

    const updateInputs = (payload) => {
        root.querySelectorAll('[data-browser-location-input]').forEach((input) => {
            const key = input.getAttribute('data-browser-location-input');

            if (!key) {
                return;
            }

            input.value = payload[key] ?? '';
        });
    };

    const accuracyLevel = (accuracy) => {
        if (accuracy === null || Number.isNaN(accuracy)) {
            return 'unknown';
        }

        if (accuracy <= 20) {
            return 'excellent';
        }

        if (accuracy <= 100) {
            return 'good';
        }

        return 'poor';
    };

    const toPayload = (position) => {
        const accuracy = Number.isFinite(position.coords.accuracy)
            ? Number(position.coords.accuracy.toFixed(2))
            : null;

        return {
            latitude: Number(position.coords.latitude.toFixed(7)),
            longitude: Number(position.coords.longitude.toFixed(7)),
            accuracy_meters: accuracy,
            accuracy_level: accuracyLevel(accuracy),
            is_accurate: accuracy !== null ? accuracy <= requiredAccuracyMeters : false,
            permission_state: 'granted',
            source: 'html5_geolocation',
            captured_at: new Date().toISOString(),
            meta: {
                altitude: position.coords.altitude,
                altitude_accuracy: position.coords.altitudeAccuracy,
                heading: position.coords.heading,
                speed: position.coords.speed,
            },
        };
    };

    const callLivewire = (payload) => {
        if (!livewireMethod || typeof window.Livewire === 'undefined') {
            return;
        }

        const host = root.closest('[wire\\:id]');

        if (!host) {
            return;
        }

        const componentId = host.getAttribute('wire:id');
        const component = componentId ? window.Livewire.find(componentId) : null;

        if (component && typeof component.call === 'function') {
            component.call(livewireMethod, payload);
        }
    };

    const handleSuccess = (position) => {
        const payload = toPayload(position);

        updateInputs(payload);

        setStatus(payload.is_accurate
            ? `Location captured (accuracy ${payload.accuracy_meters}m).`
            : `Location captured, but accuracy is low (${payload.accuracy_meters}m).`);

        emit(eventName, payload);
        callLivewire(payload);
    };

    const handleError = (error) => {
        const map = {
            1: 'Permission denied by user.',
            2: 'Position unavailable. Please try outdoors or enable GPS.',
            3: 'Location request timed out.',
        };

        const message = map[error.code] ?? 'Unable to determine browser location.';

        setStatus(message);

        emit(errorEventName, {
            code: error.code,
            message,
            permission_state: error.code === 1 ? 'denied' : 'prompt',
        });
    };

    const captureLocation = () => {
        if (!navigator.geolocation) {
            handleError({ code: 2 });

            return;
        }

        navigator.geolocation.getCurrentPosition(handleSuccess, handleError, geoOptions);

        if (watchMode && watchId === null) {
            watchId = navigator.geolocation.watchPosition(handleSuccess, handleError, geoOptions);
        }
    };

    const bindPermissionEvents = () => {
        if (!navigator.permissions || typeof navigator.permissions.query !== 'function') {
            return;
        }

        navigator.permissions
            .query({ name: 'geolocation' })
            .then((result) => {
                emit(permissionEventName, { state: result.state });
                result.onchange = () => emit(permissionEventName, { state: result.state });
            })
            .catch(() => {
                // Ignore permission API failures and rely on geolocation callback errors.
            });
    };

    trigger?.addEventListener('click', captureLocation);

    bindPermissionEvents();

    window.BrowserLocation = window.BrowserLocation || {};
    window.BrowserLocation[@js($componentId)] = {
        getJson: () => {
            const inputs = root.querySelectorAll('[data-browser-location-input]');
            const data = {};
            inputs.forEach(input => {
                const key = input.getAttribute('data-browser-location-input');
                if (key) {
                    data[key] = input.value;
                }
            });
            return JSON.stringify(data);
        },
        requestPermission: () => {
            captureLocation();
        }
    };

    window.BrowserLocationTracker = window.BrowserLocation[@js($componentId)];

    if (autoCapture || forcePermission) {
        captureLocation();
        
        if (autoCapture) {
            document.addEventListener('livewire:navigated', captureLocation);
        }
    }
})();
</script>
