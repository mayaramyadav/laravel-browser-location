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
    const componentLockId = @js($componentId);

    const geoOptions = {
        enableHighAccuracy: @json($enableHighAccuracy),
        timeout: Number(@json($timeout)),
        maximumAge: Number(@json($maximumAge)),
    };

    const forceGate = (() => {
        if (window.__browserLocationForceGate) {
            return window.__browserLocationForceGate;
        }

        window.__browserLocationForceGate = {
            locks: new Set(),
            styleInjected: false,
            ensureStyle() {
                if (this.styleInjected) {
                    return;
                }

                const styleNode = document.createElement('style');
                styleNode.id = 'browser-location-force-gate-style';
                styleNode.textContent = `
                    html[data-browser-location-force-locked="1"] body > :not([data-browser-location-force-overlay="1"]) {
                        display: none !important;
                    }
                `;

                document.head.appendChild(styleNode);
                this.styleInjected = true;
            },
            update() {
                if (this.locks.size > 0) {
                    document.documentElement.setAttribute('data-browser-location-force-locked', '1');
                } else {
                    document.documentElement.removeAttribute('data-browser-location-force-locked');
                }
            },
            lock(lockId) {
                this.ensureStyle();
                this.locks.add(lockId);
                this.update();
            },
            unlock(lockId) {
                this.locks.delete(lockId);
                this.update();
            },
        };

        return window.__browserLocationForceGate;
    })();

    let watchId = null;
    let currentPermissionState = forcePermission ? 'prompt' : null;

    let overlayNode = null;
    let overlayCardNode = null;
    let overlayIconNode = null;
    let overlayTitleNode = null;
    let overlayLeadNode = null;
    let overlayMessageNode = null;
    let overlayActionNode = null;
    let overlayCurrentState = 'prompt';

    const resolveColorMode = () => {
        const dataTheme = (document.documentElement.getAttribute('data-theme') || '').toLowerCase();
        const rootIsDark = document.documentElement.classList.contains('dark');
        const dataThemeIsDark = dataTheme === 'dark';
        const systemIsDark = window.matchMedia
            && window.matchMedia('(prefers-color-scheme: dark)').matches;

        return rootIsDark || dataThemeIsDark || systemIsDark ? 'dark' : 'light';
    };

    const themeTokens = {
        light: {
            backdrop: '#e5e7eb',
            card: '#ffffff',
            border: '#e5e7eb',
            title: '#111827',
            titleDanger: '#ef4444',
            lead: '#111827',
            text: '#4b5563',
            iconPrimary: '#0ea5e9',
            iconDanger: '#ef4444',
            iconMuted: '#9ca3af',
            buttonPrimary: '#2563eb',
            buttonDanger: '#6d28d9',
            buttonText: '#ffffff',
        },
        dark: {
            backdrop: 'rgba(3, 7, 18, 0.96)',
            card: '#111827',
            border: '#1f2937',
            title: '#f9fafb',
            titleDanger: '#f87171',
            lead: '#f3f4f6',
            text: '#d1d5db',
            iconPrimary: '#38bdf8',
            iconDanger: '#f87171',
            iconMuted: '#9ca3af',
            buttonPrimary: '#2563eb',
            buttonDanger: '#7c3aed',
            buttonText: '#ffffff',
        },
    };

    const stateIcon = (tone) => {
        if (tone === 'danger') {
            return `<svg style="width:76px;height:76px;" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M1.43 20.25h21.14c1.08 0 1.75-1.17 1.2-2.1L13.2 2.43c-.54-.9-1.86-.9-2.4 0L.23 18.15c-.55.93.12 2.1 1.2 2.1zM11 9h2v5h-2V9zm0 7h2v2h-2v-2z"/></svg>`;
        }

        if (tone === 'muted') {
            return `<svg style="width:76px;height:76px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" /></svg>`;
        }

        return `<svg style="width:76px;height:76px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>`;
    };

    const overlayMessages = {
        prompt: {
            title: 'Location Permission Required',
            lead: 'Allow location access to continue.',
            message: 'Use browser permission prompt and press Enable Location.',
            buttonLabel: 'Enable Location',
            tone: 'primary',
            action: 'retry',
            showAction: true,
            iconTone: 'primary',
        },
        denied: {
            title: 'Location Access Required',
            lead: 'Access Denied.',
            message: 'Location permission is blocked. Enable it in browser site settings, then press Retry.',
            buttonLabel: 'I have enabled it, Retry',
            tone: 'danger',
            action: 'reload',
            showAction: true,
            iconTone: 'danger',
        },
        unsupported: {
            title: 'Location Not Supported',
            lead: 'Geolocation unavailable.',
            message: 'This browser does not support geolocation, so the page cannot continue in force-permission mode.',
            buttonLabel: '',
            tone: 'primary',
            action: 'none',
            showAction: false,
            iconTone: 'muted',
        },
    };

    const applyOverlayTheme = () => {
        if (!overlayNode || !overlayCardNode) {
            return;
        }

        const mode = resolveColorMode();
        const theme = themeTokens[mode];
        const content = overlayMessages[overlayCurrentState] ?? overlayMessages.prompt;

        overlayNode.style.background = theme.backdrop;
        overlayCardNode.style.background = theme.card;
        overlayCardNode.style.borderColor = theme.border;

        if (overlayTitleNode) {
            overlayTitleNode.style.color = content.tone === 'danger' ? theme.titleDanger : theme.title;
        }

        if (overlayLeadNode) {
            overlayLeadNode.style.color = theme.lead;
        }

        if (overlayMessageNode) {
            overlayMessageNode.style.color = theme.text;
        }

        if (overlayActionNode) {
            overlayActionNode.style.background = content.tone === 'danger' ? theme.buttonDanger : theme.buttonPrimary;
            overlayActionNode.style.color = theme.buttonText;
        }

        if (overlayIconNode) {
            if (content.iconTone === 'danger') {
                overlayIconNode.style.color = theme.iconDanger;
            } else if (content.iconTone === 'muted') {
                overlayIconNode.style.color = theme.iconMuted;
            } else {
                overlayIconNode.style.color = theme.iconPrimary;
            }
        }
    };

    const setOverlayState = (state = 'prompt') => {
        if (!overlayNode) {
            createOverlay();
        }

        const content = overlayMessages[state] ?? overlayMessages.prompt;
        overlayCurrentState = state;

        if (overlayNode) {
            overlayNode.dataset.state = state;
        }

        if (overlayIconNode) {
            overlayIconNode.innerHTML = stateIcon(content.iconTone);
        }

        if (overlayTitleNode) {
            overlayTitleNode.textContent = content.title;
        }

        if (overlayLeadNode) {
            overlayLeadNode.textContent = content.lead;
        }

        if (overlayMessageNode) {
            overlayMessageNode.textContent = content.message;
        }

        if (overlayActionNode) {
            overlayActionNode.textContent = content.buttonLabel;
            overlayActionNode.style.display = content.showAction ? 'inline-flex' : 'none';
        }

        applyOverlayTheme();
    };

    const createOverlay = () => {
        if (overlayNode) {
            return;
        }

        overlayNode = document.createElement('div');
        overlayNode.setAttribute('data-browser-location-force-overlay', '1');
        overlayNode.style.cssText = 'position:fixed;inset:0;z-index:2147483647;display:flex;align-items:center;justify-content:center;padding:1.5rem;font-family:ui-sans-serif,system-ui,sans-serif;';

        overlayCardNode = document.createElement('div');
        overlayCardNode.style.cssText = 'padding:2.5rem 2.25rem;border-radius:1rem;width:min(100%,560px);text-align:center;box-shadow:0 25px 60px -20px rgba(15,23,42,0.45);border:1px solid;';

        overlayCardNode.innerHTML = `
            <div data-browser-location-overlay-icon style="display:flex;justify-content:center;margin-bottom:1.25rem;"></div>
            <h2 data-browser-location-overlay-title style="font-size:clamp(1.85rem,2.8vw,2.35rem);font-weight:800;line-height:1.15;margin:0;"></h2>
            <p data-browser-location-overlay-lead style="font-size:clamp(1.3rem,2.2vw,1.85rem);font-weight:700;line-height:1.22;margin:0.9rem 0 0;"></p>
            <p data-browser-location-overlay-message style="font-size:clamp(1.08rem,1.8vw,1.35rem);line-height:1.5;margin:0.8rem 0 2rem;"></p>
            <button type="button" data-browser-location-overlay-action style="padding:0.85rem 1.5rem;border-radius:0.65rem;border:none;font-weight:700;font-size:clamp(1rem,1.45vw,1.2rem);cursor:pointer;width:min(100%,420px);display:inline-flex;align-items:center;justify-content:center;transition:all 0.2s ease;">
            </button>
        `;

        overlayIconNode = overlayCardNode.querySelector('[data-browser-location-overlay-icon]');
        overlayTitleNode = overlayCardNode.querySelector('[data-browser-location-overlay-title]');
        overlayLeadNode = overlayCardNode.querySelector('[data-browser-location-overlay-lead]');
        overlayMessageNode = overlayCardNode.querySelector('[data-browser-location-overlay-message]');
        overlayActionNode = overlayCardNode.querySelector('[data-browser-location-overlay-action]');

        overlayActionNode?.addEventListener('click', () => {
            const content = overlayMessages[overlayCurrentState] ?? overlayMessages.prompt;

            if (content.action === 'reload') {
                window.location.reload();
            } else if (content.action === 'retry') {
                setOverlayState('prompt');
                captureLocation();
            }
        });

        overlayNode.appendChild(overlayCardNode);
        overlayNode.style.display = 'none';
        document.body.appendChild(overlayNode);

        const colorScheme = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
        if (colorScheme && typeof colorScheme.addEventListener === 'function') {
            colorScheme.addEventListener('change', applyOverlayTheme);
        }

        const classObserver = new MutationObserver(applyOverlayTheme);
        classObserver.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class', 'data-theme'],
        });

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

            forceGate.lock(componentLockId);
            setOverlayState(state);
            overlayNode.style.display = 'flex';
        } else {
            forceGate.unlock(componentLockId);

            if (overlayNode) {
                overlayNode.style.display = 'none';
            }
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

    const releaseForceGate = () => {
        if (forcePermission) {
            forceGate.unlock(componentLockId);
        }
    };

    window.addEventListener('pagehide', releaseForceGate);
    document.addEventListener('livewire:navigate', releaseForceGate);

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
