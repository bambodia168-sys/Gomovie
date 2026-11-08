import Hls from 'hls.js';

// Player is enabled only for verified first-party sessions and the private edge.
const root = document.querySelector('[data-gomovie-player]');
if (root) {
    const video = root.querySelector('video');
    const status = root.querySelector('[data-playback-status]');
    const stopButton = root.querySelector('[data-stop-playback]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const profileId = Number(root.dataset.profileId);
    let instance = null;
    let session = null;
    let heartbeatTimer = null;
    let busy = false;
    let loadedExpiry = 0;
    let requestGeneration = 0;

    const setStatus = (message) => { status.textContent = message; };

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
            throw new Error(json.message || (response.status === 401 ? 'Sign in to watch.' : 'Playback authorization failed.'));
        }
        return json.data || json;
    }

    function clearPlayback() {
        if (instance) { instance.destroy(); instance = null; }
        video.pause();
        video.removeAttribute('src');
        video.load();
    }

    function attach(url, seekPosition = 0) {
        clearPlayback();
        if (Hls.isSupported()) {
            instance = new Hls({
                enableWorker: true,
                lowLatencyMode: false,
                maxBufferLength: 30,
            });
            instance.on(Hls.Events.ERROR, (_event, data) => {
                if (data.fatal) setStatus('Playback interrupted. Check your connection or authorization.');
            });
            instance.on(Hls.Events.MANIFEST_PARSED, () => {
                if (seekPosition > 0) video.currentTime = seekPosition;
                video.play().catch(() => setStatus('Press play to resume watching.'));
            });
            instance.loadSource(url);
            instance.attachMedia(video);
        } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
            video.src = url;
            video.addEventListener('loadedmetadata', () => {
                if (seekPosition > 0) video.currentTime = seekPosition;
                video.play().catch(() => setStatus('Press play to resume watching.'));
            }, {once: true});
        } else {
            throw new Error('Adaptive playback is not supported by this browser.');
        }
    }

    async function stopCurrent() {
        clearInterval(heartbeatTimer);
        heartbeatTimer = null;
        const oldSession = session;
        session = null;
        loadedExpiry = 0;
        stopButton.hidden = true;
        clearPlayback();
        if (oldSession) {
            try {
                await send('DELETE', '/api/v1/stream/session/' + encodeURIComponent(oldSession), null);
            } catch { /* The server expires abandoned sessions automatically. */ }
        }
    }

    async function heartbeat(generation) {
        if (!session || generation !== requestGeneration) return;
        try {
            const grant = await send('POST','/api/v1/stream/session/' + encodeURIComponent(session) + '/heartbeat', {});
            if (generation !== requestGeneration) return;
            // The loaded manifest's signed URLs expire; fetch a fresh manifest before expiry.
            if (Date.now() >= loadedExpiry - 45000) {
                const seek = Number.isFinite(video.currentTime) ? video.currentTime : 0;
                attach(grant.playback_url, seek);
                loadedExpiry = Date.parse(grant.expires_at);
            }
        } catch (error) {
            setStatus(error.message);
            await stopCurrent();
        }
    }

    async function begin(type, titleId) {
        if (busy || !csrf || !Number.isInteger(profileId) || profileId <= 0) return;
        busy = true;
        const generation = ++requestGeneration;
        try {
            await stopCurrent();
            setStatus('Checking your access and preparing playback…');
            const grant = await send('POST', '/api/v1/stream/' + type + '/' + titleId + '/token', {
                profile_id: profileId,
            });
            if (generation !== requestGeneration) return;
            session = grant.session_id;
            loadedExpiry = Date.parse(grant.expires_at);
            stopButton.hidden = false;
            attach(grant.playback_url);
            setStatus('Secure streaming session active.');
            heartbeatTimer = setInterval(() => heartbeat(generation), 30000);
        } catch (error) {
            setStatus(error.message);
            await stopCurrent();
        } finally {
            busy = false;
        }
    }

    document.querySelectorAll('[data-play-type][data-play-id]').forEach((button) => {
        button.addEventListener('click', () => {
            begin(button.dataset.playType, encodeURIComponent(button.dataset.playId));
            root.scrollIntoView({behavior:'smooth',block:'center'});
        });
    });
    stopButton.addEventListener('click', () => {
        ++requestGeneration;
        stopCurrent().then(() => setStatus('Playback stopped.'));
    });
    window.addEventListener('pagehide', () => {
        clearInterval(heartbeatTimer);
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
