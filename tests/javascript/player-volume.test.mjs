import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../resources/js/player.js', import.meta.url), 'utf8');
const controls = readFileSync(new URL('../../resources/views/frontend/partials/modern-player-controls.blade.php', import.meta.url), 'utf8');

test('one accessible volume slider is attached to speaker; Gear has no Audio section', () => {
    assert.equal((controls.match(/data-gm-menu-volume/g) || []).length, 1);
    assert.equal((controls.match(/data-gm-volume-popover/g) || []).length, 1);
    assert.match(controls, /data-gm-volume-toggle/);
    assert.match(controls, /aria-expanded="false"/);
    assert.match(controls, /gm-modern-volume-popover/);
    assert.doesNotMatch(controls, /gm-settings-audio/);
    const gear = controls.slice(controls.indexOf('data-gm-settings-toggle'));
    assert.doesNotMatch(gear, /data-gm-menu-volume/);
    assert.match(source, /if \(volumePanel && !volumePanel\.hidden/);
    assert.match(source, /event\.key === 'Escape'/);
    assert.match(source, /closeVolume\(\)/);
});

test('speaker toggles single volume popover; slider controls mute and percent without duplication', () => {
    const start = source.indexOf('    // One and only one volume slider:');
    const end = source.indexOf("    speed?.addEventListener('change'", start);
    assert.ok(start >= 0 && end > start, 'live volume behavior handlers exist');
    const script = source.slice(start, end);
    const muteHandlers = {};
    const muteAttrs = {};
    const volumeHandlers = {};
    const mediaHandlers = {};
    const mute = {
        setAttribute(name, value) { muteAttrs[name] = value; },
        addEventListener(event, handler) { muteHandlers[event] = handler; },
    };
    const menuVolume = {
        value: '1',
        focused: false,
        addEventListener(event, handler) { volumeHandlers[event] = handler; },
        focus() { this.focused = true; },
    };
    const video = {
        volume: 1, muted: false,
        addEventListener(event, handler) { mediaHandlers[event] = handler; },
    };
    const volumePercent = {textContent: '100%'};
    const volumePanel = {hidden: true};
    let settingsClosed = false;
    let controlsShown = false;
    let idleScheduled = false;

    const setup = new Function('mute', 'menuVolume', 'video', 'volumePercent', 'volumePanel',
        'closeSettings', 'showControls', 'scheduleIdle', script);
    setup(mute, menuVolume, video, volumePercent, volumePanel,
        () => {settingsClosed = true;}, () => {controlsShown = true;},
        () => {idleScheduled = true;});

    muteHandlers.click();
    assert.equal(volumePanel.hidden, false);
    assert.equal(muteAttrs['aria-expanded'], 'true');
    assert.equal(menuVolume.focused, true);
    assert.equal(settingsClosed, true);
    assert.equal(controlsShown, true);
    menuVolume.value = '0';
    volumeHandlers.input();
    assert.equal(video.volume, 0);
    assert.equal(video.muted, true);
    mediaHandlers.volumechange();
    assert.equal(volumePercent.textContent, '0%');

    menuVolume.value = '0.55';
    volumeHandlers.input();
    assert.equal(video.volume, 0.55);
    assert.equal(video.muted, false);
    mediaHandlers.volumechange();
    assert.equal(volumePercent.textContent, '55%');
    assert.equal(muteAttrs['aria-label'], 'Adjust volume (55%)');

    muteHandlers.click();
    assert.equal(volumePanel.hidden, true);
    assert.equal(muteAttrs['aria-expanded'], 'false');
    assert.equal(idleScheduled, true);
});
