const sdkUrl = 'https://imasdk.googleapis.com/js/sdkloader/ima3.js';
const adTimeout = 8000;

// A single Google IMA session controls client-side ads for either player.
export function createVideoAds(root, video) {
    const layer = root.querySelector('[data-gm-ima-tag]');
    if (!layer) return null;

    const companion = document.querySelector('[data-gm-ima-companion]');
    let display;
    let loader;
    let manager;
    let generation = 0;
    let requested = false;
    let waiting = false;
    let linear = false;
    let finished = false;
    let hasPostroll = false;
    let resume;
    let afterPostroll;
    let deadline;
    let contentTime = 0;

    const script = document.createElement('script');
    script.src = sdkUrl;
    script.async = true;
    const sdk = new Promise(resolve => {
        if (window.google?.ima) resolve(window.google.ima);
        else {
            script.addEventListener('load', () => resolve(window.google?.ima), {once: true});
            script.addEventListener('error', () => resolve(null), {once: true});
            document.head.append(script);
        }
    });

    function updateLayer(active, isLinear = false) {
        linear = isLinear;
        if (linear && video.webkitDisplayingFullscreen) video.webkitExitFullscreen?.();
        layer.classList.toggle('is-active', active);
        root.classList.toggle('is-ima-linear', linear);
        root.querySelectorAll('[data-gm-controls], .plyr, .gm-player-controls').forEach(control => {
            control.inert = linear;
        });
        if (linear && document.pictureInPictureElement === video) {
            document.exitPictureInPicture()?.catch(() => {});
        }
    }

    function clearCompanion() {
        companion?.querySelector('iframe')?.remove();
        if (companion) companion.hidden = true;
    }

    function showCompanion(ima, ad) {
        if (!companion) return;
        clearCompanion();
        const selection = new ima.CompanionAdSelectionSettings();
        selection.resourceType = ima.CompanionAdSelectionSettings.ResourceType.STATIC;
        const creative = ad.getCompanionAds(300, 250, selection)[0]
            ?? ad.getCompanionAds(300, 60, selection)[0];
        if (!creative) return;
        const html = creative.getContent();
        if (!html) return;

        const frame = document.createElement('iframe');
        frame.title = 'Companion advertisement';
        frame.setAttribute('sandbox', 'allow-popups allow-popups-to-escape-sandbox');
        frame.referrerPolicy = 'no-referrer';
        frame.width = creative.getWidth();
        frame.height = creative.getHeight();
        // Render provider markup in an isolated document with image-only resources.
        frame.srcdoc = `<!doctype html><meta http-equiv="Content-Security-Policy" content="default-src 'none'; img-src https:; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'"><base target="_blank"><style>body{margin:0}img{max-width:100%;height:auto}</style>${html}`;
        companion.append(frame);
        companion.hidden = false;
    }

    function resumeContent() {
        window.clearTimeout(deadline);
        waiting = false;
        updateLayer(false);
        if (!finished) resume?.();
    }

    function completeAds() {
        window.clearTimeout(deadline);
        hasPostroll = false;
        const done = afterPostroll;
        afterPostroll = undefined;
        resumeContent();
        done?.();
    }

    function destroyManager() {
        const previous = manager;
        manager = undefined;
        previous?.destroy();
    }

    function prime(ima = window.google?.ima) {
        if (!ima || display) return;
        // Keep the protected HLS element separate from ad playback on iOS.
        ima.settings.setDisableCustomPlaybackForIOS10Plus(true);
        ima.settings.setVpaidMode(ima.ImaSdkSettings.VpaidMode.DISABLED);
        display = new ima.AdDisplayContainer(layer, video);
        display.initialize();
    }

    function dimensions(ima) {
        const {width, height} = layer.getBoundingClientRect();
        const full = document.fullscreenElement || document.webkitFullscreenElement
            || root.closest('.gm-player-stage')?.classList.contains('gm-plyr-fullscreen');
        return [Math.max(1, Math.round(width)), Math.max(1, Math.round(height)),
            full ? ima.ViewMode.FULLSCREEN : ima.ViewMode.NORMAL];
    }

    const resize = () => {
        const ima = window.google?.ima;
        if (manager && ima) manager.resize(...dimensions(ima));
    };
    const observer = new ResizeObserver(resize);
    observer.observe(layer);
    document.addEventListener('fullscreenchange', resize);
    document.addEventListener('webkitfullscreenchange', resize);

    // Do not report transient playhead positions while the viewer scrubs.
    video.addEventListener('timeupdate', () => {
        if (!video.seeking && !linear) contentTime = video.currentTime;
    });
    video.addEventListener('seeked', () => {
        if (!linear) contentTime = video.currentTime;
    });
    video.addEventListener('play', () => {
        if (waiting || linear) video.pause();
    });

    function waitForStart() {
        waiting = true;
    }

    function start(playContent) {
        if (requested) {
            if (!waiting && !linear) playContent();
            return;
        }
        requested = waiting = true;
        resume = playContent;
        const request = ++generation;
        const isCurrent = () => request === generation;
        const fail = () => {
            if (!isCurrent()) return;
            ++generation;
            destroyManager();
            completeAds();
        };

        deadline = window.setTimeout(fail, adTimeout);
        sdk.then(ima => {
            if (!isCurrent()) return;
            if (!ima) return fail();

            prime(ima);
            loader = new ima.AdsLoader(display);
            const events = ima.AdEvent.Type;
            const error = ima.AdErrorEvent.Type.AD_ERROR;
            loader.addEventListener(error, fail);
            loader.addEventListener(ima.AdsManagerLoadedEvent.Type.ADS_MANAGER_LOADED, event => {
                if (!isCurrent()) return;
                try {
                    manager = event.getAdsManager({get currentTime() { return contentTime; }});
                    const points = manager.getCuePoints();
                    hasPostroll = points.includes(-1);
                    const on = (type, handler) => manager.addEventListener(type, adEvent => {
                        if (isCurrent()) handler(adEvent);
                    });
                    on(error, fail);
                    on(events.CONTENT_PAUSE_REQUESTED, () => {
                        window.clearTimeout(deadline);
                        deadline = window.setTimeout(fail, adTimeout);
                        updateLayer(true, true);
                        video.pause();
                    });
                    on(events.CONTENT_RESUME_REQUESTED, () => {
                        if (finished && afterPostroll) completeAds();
                        else if (!finished) resumeContent();
                    });
                    on(events.LOADED, event => {
                        if (event.getAd()?.isLinear() === false) {
                            window.clearTimeout(deadline);
                            waiting = false;
                            updateLayer(true);
                            if (!finished) playContent();
                        }
                    });
                    on(events.STARTED, event => {
                        window.clearTimeout(deadline);
                        const ad = event.getAd();
                        if (ad) showCompanion(ima, ad);
                    });
                    on(events.COMPLETE, event => {
                        if (event.getAd()?.isLinear() === false) updateLayer(false);
                    });
                    on(events.ALL_ADS_COMPLETED, completeAds);
                    manager.init(...dimensions(ima));
                    manager.setVolume(video.muted ? 0 : video.volume);
                    manager.start();
                    // A VMAP with only scheduled mid/post-rolls starts with content.
                    if (points.length && !points.includes(0) && !linear) resumeContent();
                } catch {
                    fail();
                }
            });

            const adsRequest = new ima.AdsRequest();
            const [width, height] = dimensions(ima);
            adsRequest.adTagUrl = layer.dataset.gmImaTag;
            adsRequest.linearAdSlotWidth = width;
            adsRequest.linearAdSlotHeight = height;
            adsRequest.nonLinearAdSlotWidth = width;
            adsRequest.nonLinearAdSlotHeight = Math.min(150, Math.floor(height / 3));
            adsRequest.setAdWillAutoPlay(false);
            adsRequest.setAdWillPlayMuted(video.muted);
            loader.requestAds(adsRequest);
        }).catch(fail);
    }

    function contentComplete(done) {
        if (finished) {
            if (!afterPostroll) done();
            return;
        }
        finished = true;
        if (!manager || !hasPostroll) {
            try {
                loader?.contentComplete();
            } finally {
                done();
            }
            return;
        }

        afterPostroll = done;
        const current = generation;
        deadline = window.setTimeout(() => {
            ++generation;
            destroyManager();
            completeAds();
        }, adTimeout);
        try {
            loader.contentComplete();
        } catch {
            if (current === generation) completeAds();
        }
    }

    function stop() {
        ++generation;
        window.clearTimeout(deadline);
        destroyManager();
        loader?.destroy();
        loader = undefined;
        requested = waiting = finished = hasPostroll = false;
        resume = afterPostroll = undefined;
        contentTime = 0;
        updateLayer(false);
        clearCompanion();
    }

    window.addEventListener('pagehide', () => {
        stop();
        display?.destroy();
        observer.disconnect();
        document.removeEventListener('fullscreenchange', resize);
        document.removeEventListener('webkitfullscreenchange', resize);
    }, {once: true});

    return {prime, waitForStart, start, stop, contentComplete, get blocksPlayback() { return waiting || linear; }};
}
