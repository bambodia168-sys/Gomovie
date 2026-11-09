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
    const autoNextToggle = document.querySelector('[data-auto-next-toggle]');
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
        video.dataset.gmSubtitleCount = '0';
        video.dispatchEvent(new Event('gomovie:tracks-change'));
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

    function reportSubtitles(hls) {
        video.dataset.gmSubtitleCount = String(hls?.subtitleTracks?.length || 0);
        video.dispatchEvent(new Event('gomovie:tracks-change'));
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
                reportSubtitles(hls);
                applySeek(seekPosition);
                if (speedSelect) video.playbackRate = Number(speedSelect.value) || 1;
                attemptPlay();
            });
            hls.on(Hls.Events.SUBTITLE_TRACKS_UPDATED, () => {
                if (instance === hls) reportSubtitles(hls);
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


/**
 * Gomovie's shared, accessible player controls. Works with the authenticated
 * private-upload <source> and the existing HLS grant/session controller above.
 * This layer only controls an HTMLMediaElement; it never creates stream URLs
 * or modifies server-side rights, entitlements, publication or processing.
 */
document.querySelectorAll('[data-modern-player]').forEach((root) => {
    const video = root.querySelector('video');
    const ui = root.querySelector('[data-gm-controls]');
    if (!video || !ui) return;

    const stage = root.closest('.gm-player-stage');
    const seek = ui.querySelector('[data-gm-timeline]');
    const current = ui.querySelector('[data-gm-current]');
    const duration = ui.querySelector('[data-gm-duration]');
    const menuVolume = ui.querySelector('[data-gm-menu-volume]');
    const volumePercent = ui.querySelector('[data-gm-volume-percent]');
    const viewSelect = ui.querySelector('[data-gm-view-mode]');
    const viewHint = ui.querySelector('[data-gm-view-hint]');
    const placement = root.closest('[data-gm-view-placement]');
    const settingsClose = ui.querySelector('[data-gm-settings-close]');
    const spinner = ui.querySelector('.gm-center-spinner');
    const adLayer = ui.querySelector('[data-gm-ad-layer]');
    const adKind = ui.querySelector('[data-gm-ad-kind]');
    const adCountdown = ui.querySelector('[data-gm-ad-countdown]');
    const adSkip = ui.querySelector('[data-gm-ad-skip]');
    const adMessage = ui.querySelector('[data-gm-ad-message]');
    const mute = ui.querySelector('[data-gm-mute]');
    const speed = ui.querySelector('[data-playback-speed]');
    const captions = ui.querySelector('[data-captions-toggle]');
    const pip = ui.querySelector('[data-picture-in-picture]');
    const menuPip = ui.querySelector('[data-gm-menu-pip]');
    const fullscreen = ui.querySelector('[data-fullscreen-player]');
    const settingsButton = ui.querySelector('[data-gm-settings-toggle]');
    const settingsPanel = ui.querySelector('[data-gm-settings]');
    const feedback = ui.querySelector('[data-gm-feedback]');
    const secure = root.hasAttribute('data-gomovie-player');
    const seekSeconds = [5, 10, 15, 30].includes(Number(root.dataset.seekSeconds))
        ? Number(root.dataset.seekSeconds) : 10;
    const hideSeconds = [0, 3, 4, 5, 8].includes(Number(root.dataset.hideSeconds))
        ? Number(root.dataset.hideSeconds) : 4;

    let idleTimer = null;
    let feedbackTimer = null;
    let isScrubbing = false;
    let keyboardInteracting = false;
    let viewPreference = ['portrait', 'widescreen'].includes(placement?.dataset.gmViewPreference)
        ? placement.dataset.gmViewPreference : 'auto';
    let sourceOrientation = null;
    let activeAd = null;
    let adCleanup = null;
    // Native controls in the markup remain available if this script does not load.
    // Switch to the custom controls only after the UI is initialized.
    video.controls = false;

    const fmt = seconds => {
        if (!Number.isFinite(seconds) || seconds < 0) return '0:00';
        const total = Math.floor(seconds);
        const h = Math.floor(total / 3600);
        const m = Math.floor(total / 60) % 60;
        const s = String(total % 60).padStart(2, '0');
        return h ? h + ':' + String(m).padStart(2, '0') + ':' + s : m + ':' + s;
    };
    const updateTime = () => {
        const len = Number.isFinite(video.duration) && video.duration > 0 ? video.duration : 0;
        if (current) current.textContent = fmt(video.currentTime);
        if (duration) duration.textContent = fmt(len);
        if (seek) {
            seek.disabled = len === 0 || !video.seekable.length;
            if (!isScrubbing) seek.value = len ? String(video.currentTime / len * 100) : '0';
            const played = len ? Math.min(100, video.currentTime / len * 100) : 0;
            let buffered = played;
            try {
                for (let i = 0; i < video.buffered.length; i++) {
                    if (video.buffered.start(i) <= video.currentTime + 0.5)
                        buffered = Math.max(buffered, video.buffered.end(i) / len * 100);
                }
            } catch { /* Buffered ranges are optional. */ }
            seek.style.setProperty('--gm-played', played + '%');
            seek.style.setProperty('--gm-buffered', Math.min(100, buffered) + '%');
            seek.setAttribute('aria-valuetext', fmt(video.currentTime) + ' of ' + fmt(len));
        }
    };
    // Auto orientation is based on actual loaded media dimensions, never catalog
    // artwork. An explicit user override does not reload the video or lose time.
    const applyViewMode = (mode, updateUrl = false) => {
        if (!placement || !['auto', 'portrait', 'widescreen'].includes(mode)) return;
        viewPreference = mode;
        const chosen = mode === 'auto' ? (sourceOrientation || 'widescreen') : mode;
        placement.classList.toggle('is-portrait', chosen === 'portrait');
        placement.classList.toggle('is-widescreen', chosen !== 'portrait');
        placement.dataset.gmViewPreference = mode;
        stage?.setAttribute('aria-label', (chosen === 'portrait' ? 'Portrait' : 'Widescreen') + ' watch player');
        if (viewSelect) viewSelect.value = mode;
        if (viewHint) {
            viewHint.textContent = sourceOrientation
                ? 'Video detected: ' + (sourceOrientation === 'portrait' ? 'portrait' : 'widescreen')
                : 'The original video determines Auto mode when available.';
        }
        if (updateUrl) {
            const url = new URL(window.location.href);
            if (mode === 'auto') url.searchParams.delete('view');
            else url.searchParams.set('view', mode);
            window.history.replaceState(window.history.state, '', url.pathname + url.search + url.hash);
        }
    };
    const detectOrientation = () => {
        if (video.videoWidth > 0 && video.videoHeight > 0) {
            sourceOrientation = video.videoHeight > video.videoWidth ? 'portrait' : 'widescreen';
            applyViewMode(viewPreference);
        }
    };
    viewSelect?.addEventListener('change', () => applyViewMode(viewSelect.value, true));
    video.addEventListener('loadedmetadata', detectOrientation);
    video.addEventListener('resize', detectOrientation);
    applyViewMode(viewPreference);

    const updatePlay = () => {
        const playing = !video.paused && !video.ended;
        root.classList.toggle('is-playing', playing);
        ui.querySelectorAll('[data-gm-toggle]').forEach(button => {
            button.setAttribute('aria-label', playing ? 'Pause video' : 'Play video');
            button.setAttribute('title', playing ? 'Pause video' : 'Play video');
        });
        // A paused player retains the central Play affordance while its
        // bottom chrome fades after inactivity, just as during playback.
        scheduleIdle();
    };
    const closeSettings = () => {
        if (settingsPanel) settingsPanel.hidden = true;
        settingsButton?.setAttribute('aria-expanded', 'false');
    };
    settingsClose?.addEventListener('click', () => {
        closeSettings();
        settingsButton?.focus();
    });
    const showControls = () => {
        root.classList.remove('is-idle');
        if (idleTimer !== null) window.clearTimeout(idleTimer);
        idleTimer = null;
    };
    // Do not treat a mouse-clicked button's focus as keyboard interaction:
    // otherwise controls get stuck on screen for the entire video.
    const hasKeyboardFocus = () => keyboardInteracting && ui.contains(document.activeElement);
    const scheduleIdle = () => {
        showControls();
        if (!hideSeconds || hasKeyboardFocus()
            || (settingsPanel && !settingsPanel.hidden) || activeAd) return;
        idleTimer = window.setTimeout(() => {
            if (!hasKeyboardFocus() && !activeAd
                && (!settingsPanel || settingsPanel.hidden)) root.classList.add('is-idle');
        }, hideSeconds * 1000);
    };
    const tell = (message) => {
        if (!feedback) return;
        feedback.textContent = message;
        feedback.hidden = false;
        if (feedbackTimer !== null) window.clearTimeout(feedbackTimer);
        feedbackTimer = window.setTimeout(() => { feedback.hidden = true; }, 3300);
    };
    const togglePlay = async () => {
        if (video.paused || video.ended) {
            if (secure && !video.currentSrc && !video.src) {
                // Stream token issuance and expiry remain wholly inside
                // the original protected secure playback controller.
                const start = root.querySelector('[data-play-type][data-play-id]');
                if (start) start.click();
                else tell('Playback is not available for this title.');
                return;
            }
            try { await video.play(); }
            catch { tell('Unable to play this video. Check the file or browser codec.'); }
        } else video.pause();
    };
    const jump = direction => {
        if (!Number.isFinite(video.duration) || !video.seekable.length) return;
        video.currentTime = Math.max(0, Math.min(
            Math.max(0, video.duration - 0.05), video.currentTime + direction * seekSeconds
        ));
    };

    ui.querySelectorAll('[data-gm-toggle]').forEach(b => b.addEventListener('click', togglePlay));
    ui.querySelectorAll('[data-gm-skip]').forEach(b => b.addEventListener('click', () => {
        jump(Number(b.dataset.gmSkip));
        scheduleIdle();
    }));
    video.addEventListener('click', togglePlay);
    video.addEventListener('dblclick', () => {
        void toggleFullscreen();
    });

    seek?.addEventListener('pointerdown', () => { isScrubbing = true; });
    seek?.addEventListener('pointerup', () => { isScrubbing = false; updateTime(); });
    seek?.addEventListener('change', () => { isScrubbing = false; updateTime(); });
    seek?.addEventListener('input', () => {
        if (!Number.isFinite(video.duration) || !video.seekable.length) return;
        isScrubbing = true;
        video.currentTime = Math.max(0, Math.min(video.duration - 0.05,
            video.duration * Number(seek.value) / 100));
        if (current) current.textContent = fmt(video.currentTime);
        seek.style.setProperty('--gm-played', seek.value + '%');
    });

    mute?.addEventListener('click', () => { video.muted = !video.muted; });
    menuVolume?.addEventListener('input', () => {
        video.volume = Math.min(1, Math.max(0, Number(menuVolume.value)));
        video.muted = video.volume === 0;
    });
    video.addEventListener('volumechange', () => {
        if (mute) mute.setAttribute('aria-label', video.muted || !video.volume ? 'Unmute audio' : 'Mute audio');
        if (menuVolume) menuVolume.value = video.muted ? '0' : String(video.volume);
        if (volumePercent) volumePercent.textContent = Math.round((video.muted ? 0 : video.volume) * 100) + '%';
    });
    speed?.addEventListener('change', () => {
        const value = Number(speed.value);
        if ([0.5, 0.75, 1, 1.25, 1.5, 1.75, 2].includes(value)) video.playbackRate = value;
    });
    video.addEventListener('ratechange', () => {
        if (speed && [...speed.options].some(option => Number(option.value) === video.playbackRate))
            speed.value = String(video.playbackRate);
    });

    // Never show captions or PiP as functioning when the media/browser
    // does not actually provide them. HLS reports subtitle tracks separately.
    const updateCapabilities = () => {
        const nativeCount = Array.from(video.textTracks || []).filter(track =>
            ['captions', 'subtitles'].includes(track.kind)).length;
        const hlsCount = Number(video.dataset.gmSubtitleCount || 0);
        const hasCaptions = nativeCount > 0 || (secure && hlsCount > 0);
        if (captions) {
            captions.hidden = !hasCaptions;
            captions.disabled = !hasCaptions;
            if (!hasCaptions) captions.setAttribute('aria-pressed', 'false');
        }
        const supportsPip = Boolean(document.pictureInPictureEnabled
            && video.requestPictureInPicture && !video.disablePictureInPicture);
        for (const button of [pip, menuPip]) {
            if (button) {
                button.hidden = !supportsPip;
                button.disabled = !supportsPip;
            }
        }
        const supportsFullscreen = Boolean(
            stage?.requestFullscreen || stage?.webkitRequestFullscreen || video.webkitEnterFullscreen
        );
        if (fullscreen) {
            fullscreen.hidden = !supportsFullscreen;
            fullscreen.disabled = !supportsFullscreen;
        }
    };
    if (!secure) {
        captions?.addEventListener('click', () => {
            const tracks = Array.from(video.textTracks || []).filter(track =>
                ['captions', 'subtitles'].includes(track.kind));
            if (!tracks.length) return tell('No captions are available for this video.');
            const enabled = tracks.some(track => track.mode === 'showing');
            tracks.forEach((track, i) => { track.mode = !enabled && i === 0 ? 'showing' : 'disabled'; });
            captions.setAttribute('aria-pressed', String(!enabled));
        });
    }
    const togglePip = async () => {
        try {
            if (!document.pictureInPictureEnabled || !video.requestPictureInPicture)
                return tell('Picture-in-picture is unavailable in this browser.');
            if (document.pictureInPictureElement === video) await document.exitPictureInPicture();
            else await video.requestPictureInPicture();
        } catch { tell('Picture-in-picture was blocked by the browser.'); }
    };
    pip?.addEventListener('click', togglePip);
    menuPip?.addEventListener('click', () => {
        closeSettings();
        void togglePip();
    });
    video.addEventListener('loadedmetadata', updateCapabilities);
    video.textTracks?.addEventListener?.('addtrack', updateCapabilities);
    video.textTracks?.addEventListener?.('removetrack', updateCapabilities);
    video.addEventListener('gomovie:tracks-change', updateCapabilities);
    const getFullscreenElement = () =>
        document.fullscreenElement || document.webkitFullscreenElement ||
        document.mozFullScreenElement || document.msFullscreenElement || null;

    const playerIsFullscreen = () => {
        const active = getFullscreenElement();
        return Boolean(
            (active && (active === stage || active === root || active === video)) ||
            video.webkitDisplayingFullscreen
        );
    };

    // The glyph state is explicit, not dependent on :fullscreen CSS alone.
    // This also covers Safari/WebKit fullscreen events and iframe contexts.
    const syncFullscreen = () => {
        const active = playerIsFullscreen();
        root.classList.toggle('is-custom-fullscreen', active);
        stage?.classList.toggle('gm-stage-fullscreen', active);
        if (!fullscreen) return;
        fullscreen.dataset.fullscreenState = active ? 'expanded' : 'windowed';
        fullscreen.setAttribute('aria-label', active ? 'Exit fullscreen' : 'Enter fullscreen');
        fullscreen.setAttribute('title', active ? 'Exit fullscreen' : 'Enter fullscreen');
        fullscreen.setAttribute('aria-pressed', String(active));
        if (active) closeSettings();
    };

    const toggleFullscreen = async () => {
        if (!fullscreen || fullscreen.disabled || fullscreen.hidden) return;
        try {
            if (playerIsFullscreen()) {
                if (video.webkitDisplayingFullscreen && video.webkitExitFullscreen) {
                    video.webkitExitFullscreen();
                } else if (document.exitFullscreen) {
                    await document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else {
                    tell('Exit fullscreen is unavailable in this browser.');
                    return;
                }
            } else if (getFullscreenElement()) {
                // Never exit an unrelated element's fullscreen session.
                tell('Close the other fullscreen view first.');
                return;
            } else if (stage?.requestFullscreen) {
                await stage.requestFullscreen();
            } else if (stage?.webkitRequestFullscreen) {
                stage.webkitRequestFullscreen();
            } else if (video.webkitEnterFullscreen) {
                // iOS Safari may require native fullscreen for HTML5 video.
                video.webkitEnterFullscreen();
            } else {
                tell('Fullscreen is unavailable in this browser.');
                return;
            }
            syncFullscreen();
            scheduleIdle();
        } catch {
            tell('Fullscreen was blocked by the browser.');
            syncFullscreen();
        }
    };

    for (const eventName of [
        'fullscreenchange', 'webkitfullscreenchange',
        'mozfullscreenchange', 'MSFullscreenChange',
    ]) {
        document.addEventListener(eventName, syncFullscreen);
    }
    video.addEventListener('webkitbeginfullscreen', syncFullscreen);
    video.addEventListener('webkitendfullscreen', syncFullscreen);
    fullscreen?.addEventListener('click', () => {
        void toggleFullscreen();
    });
    video.addEventListener('enterpictureinpicture', () => {
        menuPip?.setAttribute('aria-pressed', 'true');
    });
    video.addEventListener('leavepictureinpicture', () => {
        menuPip?.setAttribute('aria-pressed', 'false');
    });
    settingsButton?.addEventListener('click', () => {
        const open = settingsPanel?.hidden !== false;
        if (settingsPanel) settingsPanel.hidden = !open;
        settingsButton.setAttribute('aria-expanded', String(open));
        showControls();
        if (!open) scheduleIdle();
    });
    document.addEventListener('pointerdown', event => {
        if (settingsPanel && !settingsPanel.hidden
            && !event.target.closest('.gm-modern-settings-wrap')) closeSettings();
    });

    root.addEventListener('pointerdown', () => {
        keyboardInteracting = false;
        root.classList.remove('is-keyboard');
        scheduleIdle();
    }, {passive: true});
    root.addEventListener('pointermove', scheduleIdle);
    root.addEventListener('pointerleave', scheduleIdle);
    ui.addEventListener('keydown', () => {
        keyboardInteracting = true;
        root.classList.add('is-keyboard');
        showControls();
    });
    ui.addEventListener('focusin', () => { if (keyboardInteracting) showControls(); });
    ui.addEventListener('focusout', () => window.setTimeout(scheduleIdle, 0));
    if (stage) {
        stage.setAttribute('tabindex', '0');
        stage.addEventListener('keydown', event => {
            const target = event.target;
            if (target && /INPUT|BUTTON|SELECT|TEXTAREA/.test(target.tagName)) return;
            if (event.ctrlKey || event.metaKey || event.altKey) return;
            const key = event.key.toLowerCase();
            if (key === ' ' || key === 'k') {event.preventDefault();void togglePlay();}
            else if (key === 'arrowleft' || key === 'j') {event.preventDefault();jump(-1);}
            else if (key === 'arrowright' || key === 'l') {event.preventDefault();jump(1);}
            else if (key === 'm') {event.preventDefault();video.muted = !video.muted;}
            else if (key === 'f' && fullscreen) {event.preventDefault();fullscreen.click();}
            else if (key === 'escape') closeSettings();
            scheduleIdle();
        });
    }
    ['loadedmetadata','durationchange','timeupdate','progress','seeking','seeked'].forEach(name =>
        video.addEventListener(name, updateTime));
    ['play','pause','ended','playing'].forEach(name => video.addEventListener(name, updatePlay));
    const setBuffering = (waiting) => {
        root.classList.toggle('is-buffering', waiting);
        if (spinner) spinner.hidden = !waiting;
    };
    video.addEventListener('waiting', () => setBuffering(!video.paused));
    ['playing', 'canplay', 'pause', 'ended', 'error'].forEach(name =>
        video.addEventListener(name, () => setBuffering(false)));
    video.addEventListener('error', () => {
        if (video.error) tell('The video cannot be decoded or was interrupted.');
    });
    // Advertisement UI is disabled until an approved ad adapter supplies
    // actual media and placement. It never invents ads, counts, or stream access.
    // Integration contract:
    // root.dispatchEvent(new CustomEvent('gomovie:ad-start', {
    //   detail: {media: adVideoElement, placement:'pre-roll'|'mid-roll'|'end-roll',
    //            skipAfter:5}
    // }));
    // Adapter owns ad delivery, tracking, and resume/skip enforcement; the UI
    // only displays state calculated from real ad media playback.
    const finishAd = () => {
        adCleanup?.();
        adCleanup = null;
        activeAd = null;
        root.classList.remove('is-ad-break');
        if (adLayer) adLayer.hidden = true;
        if (adSkip) { adSkip.hidden = true; adSkip.disabled = true; }
        scheduleIdle();
    };
    root.addEventListener('gomovie:ad-start', event => {
        const detail = event.detail || {};
        const placementName = detail.placement;
        const media = detail.media;
        if (!adLayer || !(media instanceof HTMLMediaElement) ||
            !['pre-roll', 'mid-roll', 'end-roll'].includes(placementName) ||
            activeAd || media === video) return;
        const skipAfter = Number.isFinite(detail.skipAfter) && detail.skipAfter >= 0
            ? detail.skipAfter : null;
        activeAd = {media, placement:placementName, skipAfter};
        video.pause();
        closeSettings();
        showControls();
        root.classList.add('is-ad-break');
        adLayer.hidden = false;
        if (adKind) adKind.textContent = 'Advertisement · ' +
            ({'pre-roll':'Before video','mid-roll':'During video','end-roll':'After video'}[placementName]);
        if (adMessage) adMessage.textContent = 'Advertisement playing';
        const updateAd = () => {
            if (!activeAd || activeAd.media !== media) return;
            const length = Number.isFinite(media.duration) ? media.duration : 0;
            const elapsed = Number.isFinite(media.currentTime) ? media.currentTime : 0;
            if (adCountdown) adCountdown.textContent = length > 0
                ? Math.max(0, Math.ceil(length - elapsed)) + 's remaining' : '';
            if (adSkip) {
                const canSkip = skipAfter !== null && elapsed >= skipAfter;
                adSkip.hidden = !canSkip;
                adSkip.disabled = !canSkip;
            }
        };
        media.addEventListener('timeupdate', updateAd);
        media.addEventListener('loadedmetadata', updateAd);
        media.addEventListener('ended', finishAd);
        adCleanup = () => {
            media.removeEventListener('timeupdate', updateAd);
            media.removeEventListener('loadedmetadata', updateAd);
            media.removeEventListener('ended', finishAd);
        };
        updateAd();
    });
    root.addEventListener('gomovie:ad-end', finishAd);
    adSkip?.addEventListener('click', () => {
        if (!activeAd || adSkip.disabled || adSkip.hidden) return;
        adSkip.disabled = true;
        root.dispatchEvent(new CustomEvent('gomovie:ad-skip-request', {
            detail: {placement:activeAd.placement, media:activeAd.media},
        }));
        // Only the adapter's actual ad end/skip acknowledgment clears UI.
    });

    window.addEventListener('pagehide', () => {
        finishAd();
        window.clearTimeout(idleTimer);
        window.clearTimeout(feedbackTimer);
    }, {once:true});
    updateTime();
    updateCapabilities();
    updatePlay();
    detectOrientation();
    syncFullscreen();
    if (volumePercent) volumePercent.textContent = Math.round(video.volume * 100) + '%';
});
