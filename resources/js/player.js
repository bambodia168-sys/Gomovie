import Hls from 'hls.js';

// Playback is only granted by the authenticated API and approved private edge.
const root = document.querySelector('[data-gomovie-player]');

if (root) {
    const video = root.querySelector('video');
    const status = root.querySelector('[data-playback-status]');
    const stopButton = root.querySelector('[data-stop-playback]');
    const retryButton = root.querySelector('[data-retry-playback]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const profileId = Number(root.dataset.profileId);
    const tools = document.querySelector('[data-gomovie-player-tools]');
    const stage = root.closest('.gm-player-stage');
    const nextPrompt = document.querySelector('[data-gomovie-next-prompt]');
    const autoNextToggle = tools?.querySelector('[data-auto-next-toggle]');
    const qualitySelect = tools?.querySelector('[data-playback-quality]');
    const qualityContainer = tools?.querySelector('[data-quality-container]');
    const speedSelect = tools?.querySelector('[data-playback-speed]');
    const volumeSlider = tools?.querySelector('[data-playback-volume]');
    let instance = null;
    let session = null;
    let heartbeatTimer = null;
    let countdownTimer = null;
    let busy = false;
    let loadedExpiry = 0;
    let requestGeneration = 0;
    let playing = null;
    let nextSeconds = 7;

    let nextUrl = null;
    if (root.dataset.nextUrl) {
        try {
            const parsed = new URL(root.dataset.nextUrl, window.location.origin);
            if (parsed.origin === window.location.origin && parsed.pathname.startsWith('/watch/')) {
                nextUrl = parsed.href;
            }
        } catch { /* Invalid destinations are ignored; never redirect off-site. */ }
    }

    const setStatus = (message) => { if (status) status.textContent = message; };
    const setRetry = (visible) => { if (retryButton) retryButton.hidden = !visible; };

    function cancelNext(message = null) {
        if (countdownTimer !== null) {
            window.clearInterval(countdownTimer);
            countdownTimer = null;
        }
        if (nextPrompt) nextPrompt.hidden = true;
        if (message) setStatus(message);
    }

    async function send(method, path, payload) {
        const response = await fetch(path, {
            method,
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf || '',
            },
            body: payload ? JSON.stringify(payload) : undefined,
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(json.message || (response.status === 401
                ? 'Sign in to watch.' : 'Playback authorization failed.'));
        }
        return json.data || json;
    }

    function clearPlayback() {
        if (instance) {
            instance.destroy();
            instance = null;
        }
        video.pause();
        video.removeAttribute('src');
        video.load();
        if (qualityContainer) qualityContainer.hidden = true;
    }

    function fillQualityOptions(hls) {
        if (!qualitySelect || !qualityContainer || !hls?.levels?.length) return;
        qualitySelect.replaceChildren(new Option('Auto', '-1'));
        hls.levels.forEach((level, index) => {
            const label = level.height > 0
                ? String(level.height) + 'p'
                : String(Math.round((level.bitrate || 0) / 1000)) + ' kbps';
            qualitySelect.add(new Option(label, String(index)));
        });
        qualitySelect.value = '-1';
        qualityContainer.hidden = hls.levels.length < 2;
    }

    function attemptPlay() {
        const result = video.play();
        result?.catch(() => setStatus('Playback is ready. Press play to continue.'));
    }

    function applySeek(position) {
        if (!Number.isFinite(position) || position <= 0) return;
        const seek = () => {
            if (Number.isFinite(video.duration) && video.duration > 0) {
                video.currentTime = Math.min(position, Math.max(0, video.duration - 0.2));
            }
        };
        if (video.readyState >= 1) seek();
        else video.addEventListener('loadedmetadata', seek, {once: true});
    }

    function attach(url, seekPosition = 0) {
        clearPlayback();
        if (Hls.isSupported()) {
            const hls = new Hls({
                enableWorker: true,
                lowLatencyMode: false,
                maxBufferLength: 30,
            });
            instance = hls;
            hls.on(Hls.Events.ERROR, (_event, data) => {
                if (instance !== hls || !data.fatal) return;
                setStatus('Playback interrupted. Retry to request a fresh secure session.');
                setRetry(true);
                cancelNext();
            });
            hls.on(Hls.Events.MANIFEST_PARSED, () => {
                if (instance !== hls) return;
                fillQualityOptions(hls);
                applySeek(seekPosition);
                if (speedSelect) video.playbackRate = Number(speedSelect.value) || 1;
                attemptPlay();
            });
            hls.loadSource(url);
            hls.attachMedia(video);
        } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
            video.src = url;
            video.addEventListener('loadedmetadata', () => {
                applySeek(seekPosition);
                if (speedSelect) video.playbackRate = Number(speedSelect.value) || 1;
                attemptPlay();
            }, {once: true});
        } else {
            throw new Error('Adaptive playback is not supported by this browser.');
        }
    }

    async function stopCurrent() {
        window.clearInterval(heartbeatTimer);
        heartbeatTimer = null;
        const oldSession = session;
        session = null;
        loadedExpiry = 0;
        stopButton.hidden = true;
        clearPlayback();
        if (oldSession) {
            try {
                await send('DELETE', '/api/v1/stream/session/' + encodeURIComponent(oldSession), null);
            } catch {
                // The edge and server still expire/revoke abandoned sessions.
            }
        }
    }

    async function heartbeat(generation) {
        if (!session || generation !== requestGeneration) return;
        try {
            const grant = await send('POST',
                '/api/v1/stream/session/' + encodeURIComponent(session) + '/heartbeat', {});
            if (generation !== requestGeneration) return;
            const newExpiry = Date.parse(grant.expires_at);
            if (!Number.isFinite(newExpiry)) throw new Error('Playback renewal failed.');

            // Manifest and signed segment URLs must be refreshed before expiry.
            if (Date.now() >= loadedExpiry - 45000) {
                const seek = Number.isFinite(video.currentTime) ? video.currentTime : 0;
                const wasPaused = video.paused;
                attach(grant.playback_url, seek);
                if (wasPaused) {
                    video.addEventListener('playing', () => video.pause(), {once: true});
                }
                loadedExpiry = newExpiry;
            }
        } catch (error) {
            setStatus(error.message);
            setRetry(true);
            cancelNext();
            ++requestGeneration;
            await stopCurrent();
        }
    }

    async function begin(type, titleId) {
        if (busy || !csrf || !Number.isInteger(profileId) || profileId <= 0) return;
        if (!['movie', 'episode'].includes(type) || !/^[1-9][0-9]*$/.test(String(titleId))) return;
        busy = true;
        const generation = ++requestGeneration;
        playing = {type, titleId: String(titleId)};
        cancelNext();
        setRetry(false);

        try {
            await stopCurrent();
            if (generation !== requestGeneration) return;
            setStatus('Checking content rights and preparing secure playback…');
            const grant = await send('POST', '/api/v1/stream/' + type + '/' + titleId + '/token', {
                profile_id: profileId,
            });
            if (generation !== requestGeneration) return;
            session = grant.session_id;
            loadedExpiry = Date.parse(grant.expires_at);
            stopButton.hidden = false;
            attach(grant.playback_url);
            setStatus('Secure streaming session active.');
            heartbeatTimer = window.setInterval(() => heartbeat(generation), 30000);
        } catch (error) {
            setStatus(error.message || 'Secure playback is unavailable.');
            setRetry(true);
            await stopCurrent();
        } finally {
            busy = false;
        }
    }

    function nextAllowed() {
        return Boolean(nextUrl && autoNextToggle?.checked && session && video.ended);
    }

    async function playNext() {
        if (!nextAllowed()) {
            cancelNext('Autoplay next is unavailable.');
            return;
        }
        cancelNext();
        ++requestGeneration;
        setStatus('Opening the next licensed title…');
        await stopCurrent();
        // The next page requests a brand-new protected grant. No session or
        // entitlement is ever carried from the previous title.
        window.location.assign(nextUrl);
    }

    function prepareNext() {
        if (!nextAllowed() || !nextPrompt) {
            setStatus('Playback completed.');
            return;
        }
        cancelNext();
        nextSeconds = 7;
        const number = nextPrompt.querySelector('[data-next-countdown]');
        if (number) number.textContent = String(nextSeconds);
        nextPrompt.hidden = false;
        setStatus('Playback completed. Preparing the next available title.');

        countdownTimer = window.setInterval(() => {
            if (!nextAllowed()) {
                cancelNext('Autoplay canceled.');
                return;
            }
            nextSeconds -= 1;
            if (number) number.textContent = String(Math.max(0, nextSeconds));
            if (nextSeconds <= 0) {
                cancelNext();
                void playNext();
            }
        }, 1000);
    }

    root.querySelectorAll('[data-play-type][data-play-id]').forEach((button) => {
        button.addEventListener('click', () => {
            void begin(button.dataset.playType, button.dataset.playId);
            stage?.scrollIntoView({behavior: 'smooth', block: 'center'});
        });
    });

    stopButton?.addEventListener('click', () => {
        ++requestGeneration;
        cancelNext();
        setRetry(false);
        void stopCurrent().then(() => setStatus('Playback stopped.'));
    });
    retryButton?.addEventListener('click', () => {
        if (playing) void begin(playing.type, playing.titleId);
    });

    video.addEventListener('ended', prepareNext);
    video.addEventListener('error', () => {
        if (!session) return;
        setStatus('The video could not be decoded. Retry or choose a different title.');
        setRetry(true);
        cancelNext();
    });

    tools?.querySelectorAll('[data-seek-offset]').forEach(button => {
        button.addEventListener('click', () => {
            if (!session || !Number.isFinite(video.duration)) return;
            video.currentTime = Math.min(
                Math.max(0, video.currentTime + Number(button.dataset.seekOffset)),
                video.duration
            );
        });
    });

    tools?.querySelector('[data-mute-toggle]')?.addEventListener('click', event => {
        video.muted = !video.muted;
        event.currentTarget.setAttribute('aria-label', video.muted ? 'Unmute audio' : 'Mute audio');
    });
    volumeSlider?.addEventListener('input', () => {
        video.volume = Math.min(1, Math.max(0, Number(volumeSlider.value)));
        video.muted = video.volume === 0;
    });
    speedSelect?.addEventListener('change', () => {
        const speed = Number(speedSelect.value);
        if ([0.75, 1, 1.25, 1.5, 2].includes(speed)) video.playbackRate = speed;
    });
    qualitySelect?.addEventListener('change', () => {
        if (instance) instance.currentLevel = Number(qualitySelect.value);
    });

    tools?.querySelector('[data-captions-toggle]')?.addEventListener('click', event => {
        const button = event.currentTarget;
        if (instance?.subtitleTracks?.length) {
            const enabling = !instance.subtitleDisplay;
            instance.subtitleTrack = enabling ? 0 : -1;
            instance.subtitleDisplay = enabling;
            button.setAttribute('aria-pressed', String(enabling));
            setStatus(enabling ? 'Captions enabled.' : 'Captions disabled.');
            return;
        }
        const tracks = Array.from(video.textTracks || []).filter(track =>
            ['captions', 'subtitles'].includes(track.kind));
        if (!tracks.length) {
            setStatus('Captions are not provided for this title.');
            return;
        }
        const enabling = !tracks.some(track => track.mode === 'showing');
        tracks.forEach((track, index) => {
            track.mode = enabling && index === 0 ? 'showing' : 'disabled';
        });
        button.setAttribute('aria-pressed', String(enabling));
        setStatus(enabling ? 'Captions enabled.' : 'Captions disabled.');
    });

    tools?.querySelector('[data-picture-in-picture]')?.addEventListener('click', async () => {
        try {
            if (!document.pictureInPictureEnabled || !video.requestPictureInPicture) {
                setStatus('Picture-in-picture is unavailable in this browser.');
            } else if (document.pictureInPictureElement === video) {
                await document.exitPictureInPicture();
            } else if (session) {
                await video.requestPictureInPicture();
            } else {
                setStatus('Start authorized playback first.');
            }
        } catch {
            setStatus('Picture-in-picture was not permitted by the browser.');
        }
    });

    tools?.querySelector('[data-fullscreen-player]')?.addEventListener('click', async () => {
        try {
            if (document.fullscreenElement) await document.exitFullscreen();
            else if (stage?.requestFullscreen) await stage.requestFullscreen();
            else if (video.webkitEnterFullscreen) video.webkitEnterFullscreen();
            else setStatus('Fullscreen is unavailable in this browser.');
        } catch {
            setStatus('Fullscreen was not permitted by the browser.');
        }
    });

    if (autoNextToggle) {
        try {
            autoNextToggle.checked = window.sessionStorage.getItem('gomovie:autoplay-next') !== 'off';
        } catch { /* Optional preferences do not block playback. */ }
        autoNextToggle.addEventListener('change', () => {
            try {
                window.sessionStorage.setItem('gomovie:autoplay-next',
                    autoNextToggle.checked ? 'on' : 'off');
            } catch { /* Browsers may block optional storage. */ }
            if (!autoNextToggle.checked) cancelNext('Autoplay next disabled.');
        });
    }

    nextPrompt?.querySelector('[data-next-cancel]')?.addEventListener('click', () =>
        cancelNext('Autoplay canceled.'));
    nextPrompt?.querySelector('[data-next-now]')?.addEventListener('click', () => {
        if (nextAllowed()) void playNext();
    });

    // A continued page obtains a fresh stream grant before attempting playback.
    // Browsers may still require the user to press Play (autoplay policy).
    if (root.dataset.autoStart === '1') {
        const startButton = root.querySelector('[data-play-type][data-play-id]');
        if (startButton) {
            window.setTimeout(() => {
                if (!session && !busy) startButton.click();
            }, 0);
        }
    }

    window.addEventListener('pagehide', () => {
        cancelNext();
        window.clearInterval(heartbeatTimer);
        if (session && csrf) {
            fetch('/api/v1/stream/session/' + encodeURIComponent(session), {
                method: 'DELETE',
                credentials: 'same-origin',
                keepalive: true,
                headers: {'X-CSRF-TOKEN': csrf, Accept: 'application/json'},
            }).catch(() => {});
        }
    });
}
