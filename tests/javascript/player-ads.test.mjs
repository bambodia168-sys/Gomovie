import {strict as assert} from 'node:assert';
import {test} from 'node:test';
import {createVideoAds} from '../../resources/js/player-ads.js';

function setup(t, ima) {
    const classes = () => ({
        values: new Set(),
        toggle(name, on) { if (on) this.values.add(name); else this.values.delete(name); },
    });
    const video = new EventTarget();
    video.pause = t.mock.fn();
    const controls = {inert: false};
    const layer = {
        classList: classes(),
        dataset: {gmImaTag: 'https://ads.example.test/tag'},
        getBoundingClientRect: () => ({width: 640, height: 360}),
    };
    const root = {
        classList: classes(),
        querySelector: () => layer,
        querySelectorAll: () => [controls],
        closest: () => null,
    };
    const script = new EventTarget();
    const page = Object.assign(new EventTarget(), {google: {ima}, setTimeout, clearTimeout});
    const document = Object.assign(new EventTarget(), {
        createElement: () => script,
        head: {append() {}},
        querySelector: () => null,
    });
    const globals = {window: page, document, ResizeObserver: class { observe() {} disconnect() {} }};
    Object.assign(globalThis, globals);
    const ads = createVideoAds(root, video);
    t.after(() => {
        page.dispatchEvent(new Event('pagehide'));
        for (const name of Object.keys(globals)) delete globalThis[name];
    });
    return {ads, controls, layer, script, video};
}

test('shared player pauses for linear ads and postpones continuation until the scheduled post-roll', async t => {
    const emit = (target, name, details = {}) =>
        target.dispatchEvent(Object.assign(new Event(name), details));
    const events = ['CONTENT_PAUSE_REQUESTED', 'CONTENT_RESUME_REQUESTED',
        'LOADED', 'STARTED', 'COMPLETE', 'ALL_ADS_COMPLETED'];
    const manager = Object.assign(new EventTarget(), {
        getCuePoints: () => [0, 10, -1],
        init() {},
        setVolume() {},
        start() { emit(this, 'CONTENT_PAUSE_REQUESTED'); emit(this, 'STARTED', {getAd: () => null}); },
        destroy() {},
    });
    const ima = {
        settings: {setDisableCustomPlaybackForIOS10Plus() {}, setVpaidMode() {}},
        ImaSdkSettings: {VpaidMode: {DISABLED: 0}},
        AdDisplayContainer: class { initialize() {} destroy() {} },
        AdsLoader: class extends EventTarget {
            requestAds(request) {
                assert.equal(request.adTagUrl, 'https://ads.example.test/tag');
                emit(this, 'manager-loaded', {getAdsManager: () => manager});
            }
            contentComplete() { manager.start(); }
            destroy() {}
        },
        AdsRequest: class { setAdWillAutoPlay() {} setAdWillPlayMuted() {} },
        AdEvent: {Type: Object.fromEntries(events.map(event => [event, event]))},
        AdErrorEvent: {Type: {AD_ERROR: 'ad-error'}},
        AdsManagerLoadedEvent: {Type: {ADS_MANAGER_LOADED: 'manager-loaded'}},
        ViewMode: {NORMAL: 'normal', FULLSCREEN: 'fullscreen'},
    };
    const {ads, controls, layer, video} = setup(t, ima);
    const play = t.mock.fn();
    const done = t.mock.fn();

    ads.waitForStart();
    video.dispatchEvent(new Event('play'));
    assert.equal(video.pause.mock.callCount(), 1);
    ads.start(play);
    await Promise.resolve();
    assert.equal(play.mock.callCount(), 0);
    assert.equal(controls.inert, true);
    emit(manager, 'CONTENT_RESUME_REQUESTED');
    assert.equal(play.mock.callCount(), 1);

    manager.start();
    assert.equal(controls.inert, true);
    emit(manager, 'CONTENT_RESUME_REQUESTED');
    assert.equal(play.mock.callCount(), 2);

    const overlay = {getAd: () => ({isLinear: () => false})};
    emit(manager, 'LOADED', overlay);
    assert.equal(controls.inert, false);
    assert.equal(layer.classList.values.has('is-active'), true);
    emit(manager, 'COMPLETE', overlay);
    assert.equal(layer.classList.values.has('is-active'), false);

    ads.contentComplete(done);
    assert.equal(controls.inert, true);
    assert.equal(done.mock.callCount(), 0);
    emit(manager, 'CONTENT_RESUME_REQUESTED');
    emit(manager, 'ALL_ADS_COMPLETED');
    assert.equal(done.mock.callCount(), 1);
    assert.equal(controls.inert, false);
});

test('ad SDK load errors release content playback', async t => {
    const {ads, script} = setup(t);
    const play = t.mock.fn();
    ads.waitForStart();
    ads.start(play);
    assert.equal(ads.blocksPlayback, true);
    script.dispatchEvent(new Event('error'));
    await Promise.resolve();
    assert.equal(play.mock.callCount(), 1);
    assert.equal(ads.blocksPlayback, false);
});
