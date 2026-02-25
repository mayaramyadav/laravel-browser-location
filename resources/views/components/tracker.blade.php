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
    let currentPermissionState = forcePermission ? 'prompt' : null;

    let overlayNode = null;
    let overlayTitleNode = null;
    let overlayMessageNode = null;
    let overlayActionNode = null;

    const overlayMessages = {
        prompt: {
            title: 'Location Access Required',
            message: 'To continue, allow location access for this site in your browser permission prompt.',
            buttonLabel: 'Enable Location',
            showAction: true,
        },
        denied: {
            title: 'Location Access Blocked',
            message: 'Location permission is blocked. Enable it in your browser site settings, then retry.',
            buttonLabel: "I Have Enabled It",
            showAction: true,
        },
        unsupported: {
            title: 'Location Not Supported',
            message: 'This browser does not support geolocation, so this page cannot continue while forced permission is enabled.',
            buttonLabel: '',
            showAction: false,
        },
    };

    const setOverlayState = (state = 'prompt') => {
        if (!overlayNode) {
            createOverlay();
        }

        const content = overlayMessages[state] ?? overlayMessages.prompt;

        if (overlayTitleNode) {
            overlayTitleNode.textContent = content.title;
        }

        if (overlayMessageNode) {
            overlayMessageNode.textContent = content.message;
        }

        if (overlayActionNode) {
            overlayActionNode.textContent = content.buttonLabel;
            overlayActionNode.style.display = content.showAction ? 'inline-flex' : 'none';
        }
    };

    const createOverlay = () => {
        if (overlayNode) {
            return;
        }

        overlayNode = document.createElement('div');
        overlayNode.style.cssText = 'position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.75);z-index:999999;display:flex;flex-direction:column;align-items:center;justify-content:center;font-family:ui-sans-serif,system-ui,sans-serif;color:#111827;backdrop-filter:blur(4px);';

        const card = document.createElement('div');
        card.style.cssText = 'background:#fff;padding:2.5rem;border-radius:1rem;max-width:90%;width:420px;text-align:center;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);';

        card.innerHTML = `
            <svg style="width:64px;height:64px;margin:0 auto 1.5rem;color:#ef4444;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <h2 data-browser-location-overlay-title style="font-size:1.5rem;font-weight:700;margin-bottom:0.75rem;color:#111827;"></h2>
            <p data-browser-location-overlay-message style="margin-bottom:2rem;font-size:1rem;color:#4b5563;line-height:1.5;"></p>
            <button type="button" data-browser-location-overlay-action style="background:#2563eb;color:#fff;padding:0.75rem 1.5rem;border-radius:0.5rem;border:none;font-weight:600;font-size:1rem;cursor:pointer;width:100%;transition:background 0.2s;display:inline-flex;align-items:center;justify-content:center;">
                Enable Location
            </button>
        `;

        overlayTitleNode = card.querySelector('[data-browser-location-overlay-title]');
        overlayMessageNode = card.querySelector('[data-browser-location-overlay-message]');
        overlayActionNode = card.querySelector('[data-browser-location-overlay-action]');

        overlayActionNode?.addEventListener('click', () => {
            setOverlayState('prompt');
            captureLocation();
        });

        overlayNode.appendChild(card);
        overlayNode.style.display = 'none';
        document.body.appendChild(overlayNode);

        setOverlayState('prompt');
    };

    const toggleOverlay = (show, state = 'prompt') => {
        if (!forcePermission) {
            return;
        }

        if (show) {
            if (!overlayNode) {
                createOverlay();
            }

            setOverlayState(state);
            overlayNode.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        } else {
            if (overlayNode) {
                overlayNode.style.display = 'none';
            }

            document.body.style.overflow = '';
        }
    };

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
        currentPermissionState = 'granted';
        toggleOverlay(false);

        const payload = toPayload(position);

        updateInputs(payload);

        setStatus(payload.is_accurate
            ? `Location captured (accuracy ${payload.accuracy_meters}m).`
            : `Location captured, but accuracy is low (${payload.accuracy_meters}m).`);

        emit(permissionEventName, { state: 'granted' });
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
        const permissionState = error.code === 1
            ? 'denied'
            : (currentPermissionState === 'granted' ? 'granted' : 'prompt');

        currentPermissionState = permissionState;

        setStatus(message);

        emit(errorEventName, {
            code: error.code,
            message,
            permission_state: permissionState,
        });

        emit(permissionEventName, { state: permissionState });

        if (forcePermission && permissionState !== 'granted') {
            toggleOverlay(true, permissionState === 'denied' ? 'denied' : 'prompt');
        }
    };

    const captureLocation = () => {
        if (!navigator.geolocation) {
            const message = 'Geolocation is not supported by this browser.';
            setStatus(message);
            emit(errorEventName, {
                code: 2,
                message,
                permission_state: 'prompt',
            });

            if (forcePermission) {
                toggleOverlay(true, 'unsupported');
            }

            return;
        }

        navigator.geolocation.getCurrentPosition(handleSuccess, handleError, geoOptions);

        if (watchMode && watchId === null) {
            watchId = navigator.geolocation.watchPosition(handleSuccess, handleError, geoOptions);
        }
    };

    const bindPermissionEvents = () => {
        if (!navigator.permissions || typeof navigator.permissions.query !== 'function') {
            if (forcePermission) {
                toggleOverlay(true, 'prompt');
            }

            return;
        }

        navigator.permissions
            .query({ name: 'geolocation' })
            .then((result) => {
                currentPermissionState = result.state;
                emit(permissionEventName, { state: result.state });

                if (result.state === 'granted') {
                    toggleOverlay(false);
                } else if (forcePermission) {
                    toggleOverlay(true, result.state === 'denied' ? 'denied' : 'prompt');
                }

                result.onchange = () => {
                    currentPermissionState = result.state;
                    emit(permissionEventName, { state: result.state });

                    if (result.state === 'granted') {
                        toggleOverlay(false);
                        captureLocation();
                    } else if (forcePermission) {
                        toggleOverlay(true, result.state === 'denied' ? 'denied' : 'prompt');
                    }
                };
            })
            .catch(() => {
                if (forcePermission) {
                    toggleOverlay(true, 'prompt');
                }
            });
    };

    trigger?.addEventListener('click', captureLocation);

    if (forcePermission) {
        toggleOverlay(true, 'prompt');
    }

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
