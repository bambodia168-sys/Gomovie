import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../resources/js/player.js', import.meta.url), 'utf8');
const blade = readFileSync(new URL('../../resources/views/frontend/partials/modern-player-controls.blade.php', import.meta.url), 'utf8');
const css = readFileSync(new URL('../../resources/css/app.css', import.meta.url), 'utf8');

function createHarness(mode = 'standard') {
    const begin = source.indexOf('    const getFullscreenElement = () =>');
    const end = source.indexOf('    for (const eventName of [', begin);
    assert.ok(begin > 0 && end > begin, 'fullscreen implementation must exist');
    const block = source.slice(begin, end);
    const classes = new Set();
    const stageClasses = new Set();
    const fullscreen = {
        hidden: false,
        disabled: false,
        dataset: { fullscreenState: 'windowed' },
        attrs: new Map(),
        setAttribute(key, value) { this.attrs.set(key, value); },
    };
    const stage = {
        classList: { toggle: (key, value) => value ? stageClasses.add(key) : stageClasses.delete(key) },
    };
    const root = {
        classList: { toggle: (key, value) => value ? classes.add(key) : classes.delete(key) },
    };
    const video = { webkitDisplayingFullscreen: false };
    const document = {
        fullscreenElement: null,
        webkitFullscreenElement: null,
        async exitFullscreen() { this.fullscreenElement = null; },
        webkitExitFullscreen() { this.webkitFullscreenElement = null; },
    };
    if (mode === 'standard') {
        stage.requestFullscreen = async () => { document.fullscreenElement = stage; };
    } else if (mode === 'webkit') {
        stage.webkitRequestFullscreen = () => { document.webkitFullscreenElement = stage; };
        stage.requestFullscreen = undefined;
    } else if (mode === 'native-ios') {
        stage.requestFullscreen = undefined;
        video.webkitEnterFullscreen = () => { video.webkitDisplayingFullscreen = true; };
        video.webkitExitFullscreen = () => { video.webkitDisplayingFullscreen = false; };
    }
    const notices = [];
    let closed = 0;
    const fn = new Function(
        'document', 'video', 'stage', 'root', 'fullscreen', 'closeSettings', 'scheduleIdle', 'tell',
        block + '\nreturn { toggleFullscreen, syncFullscreen, playerIsFullscreen };'
    );
    const api = fn(document, video, stage, root, fullscreen,
        () => { closed++; }, () => {}, message => notices.push(message));
    return { ...api, fullscreen, stage, root, video, document, classes, stageClasses, notices,
        get closed() { return closed; } };
}

test('fullscreen uses compact unframed SVG glyphs aligned with the other player controls', () => {
    assert.match(blade, /data-fullscreen-player data-fullscreen-state="windowed"/);
    assert.match(blade, /gm-fullscreen-glyph gm-icon-expand/);
    assert.match(blade, /gm-fullscreen-glyph gm-icon-collapse/);
    assert.equal((blade.match(/class="gm-fullscreen-frame"/g) || []).length, 0,
        'Fullscreen SVG must not draw a second frame inside the button');
    assert.match(blade, /d="M13\.5 4\.5h6v6m0-6-7 7M10\.5 19\.5h-6v-6m0 6 7-7"/);
    assert.match(css, /width:21px!important/);
    assert.match(css, /height:21px!important/);
    assert.match(css, /border:1px solid transparent/);
    assert.match(css, /background:transparent/);
    assert.doesNotMatch(css, /width:29px!important/);
    assert.doesNotMatch(css, /background:linear-gradient\(145deg,#f3306049/);
    assert.match(css, /data-fullscreen-state="expanded"/);
    assert.match(css, /data-fullscreen-state="windowed"/);
    assert.match(css, /gm-modern-fullscreen-btn svg\.gm-fullscreen-glyph/);
    assert.match(source, /video\.controls = false/);
});

test('standard fullscreen enters and exits with synced icon and accessibility state', async () => {
    const h = createHarness();
    h.syncFullscreen();
    assert.equal(h.fullscreen.dataset.fullscreenState, 'windowed');
    assert.equal(h.fullscreen.attrs.get('aria-label'), 'Enter fullscreen');
    await h.toggleFullscreen();
    assert.equal(h.fullscreen.dataset.fullscreenState, 'expanded');
    assert.equal(h.fullscreen.attrs.get('aria-pressed'), 'true');
    assert.equal(h.fullscreen.attrs.get('title'), 'Exit fullscreen');
    assert.ok(h.classes.has('is-custom-fullscreen'));
    assert.ok(h.stageClasses.has('gm-stage-fullscreen'));
    assert.equal(h.closed, 1);
    await h.toggleFullscreen();
    assert.equal(h.fullscreen.dataset.fullscreenState, 'windowed');
    assert.equal(h.fullscreen.attrs.get('aria-pressed'), 'false');
    assert.equal(h.fullscreen.attrs.get('aria-label'), 'Enter fullscreen');
    assert.ok(!h.classes.has('is-custom-fullscreen'));
});

test('WebKit fullscreen synchronizes both glyph states', async () => {
    const h = createHarness('webkit');
    await h.toggleFullscreen();
    assert.equal(h.document.webkitFullscreenElement, h.stage);
    assert.equal(h.fullscreen.dataset.fullscreenState, 'expanded');
    await h.toggleFullscreen();
    assert.equal(h.document.webkitFullscreenElement, null);
    assert.equal(h.fullscreen.dataset.fullscreenState, 'windowed');
});

test('iOS native fallback accurately reports fullscreen state without claiming custom native UI', async () => {
    const h = createHarness('native-ios');
    await h.toggleFullscreen();
    assert.equal(h.video.webkitDisplayingFullscreen, true);
    assert.equal(h.fullscreen.dataset.fullscreenState, 'expanded');
    await h.toggleFullscreen();
    assert.equal(h.video.webkitDisplayingFullscreen, false);
    assert.equal(h.fullscreen.dataset.fullscreenState, 'windowed');
});

test('fullscreen control never exits an unrelated fullscreen element', async () => {
    const h = createHarness();
    const other = {};
    h.document.fullscreenElement = other;
    await h.toggleFullscreen();
    assert.equal(h.document.fullscreenElement, other);
    assert.equal(h.fullscreen.dataset.fullscreenState, 'windowed');
    assert.ok(h.notices.some(msg => msg.includes('other fullscreen')));
});
