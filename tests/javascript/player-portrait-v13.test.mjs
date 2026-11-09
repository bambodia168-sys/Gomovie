import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const js = readFileSync(new URL('../../resources/js/player.js', import.meta.url),'utf8');
const css = readFileSync(new URL('../../resources/css/app.css', import.meta.url),'utf8');
const player = readFileSync(new URL('../../resources/views/frontend/partials/modern-player-controls.blade.php', import.meta.url),'utf8');
const studio = readFileSync(new URL('../../resources/views/admin/upload-studio.blade.php', import.meta.url),'utf8');

test('portrait settings menu is portaled to the full player overlay and click-away is safe', () => {
    assert.match(js, /if \(settingsPanel\) ui\.appendChild\(settingsPanel\)/);
    assert.match(js, /event\.target\.closest\('\.gm-modern-settings-wrap, \[data-gm-settings\]'\)/);
    assert.match(css, /\.gm-modern-player \.gm-modern-ui > \.gm-modern-settings\.gm-settings-premium/);
    assert.match(css, /left:50%/);
    assert.match(css, /transform:translateX\(-50%\)/);
    assert.match(css, /max-width:calc\(100% - 24px\)/);
});

test('portrait has a single transparent control row with no phantom extra line', () => {
    assert.match(css, /\.gm-player-placement\.is-portrait \.gm-modern-toolbar\{/);
    assert.match(css, /display:flex!important/);
    assert.match(css, /flex-wrap:nowrap!important/);
    assert.match(css, /flex-direction:row!important/);
    assert.match(css, /background:linear-gradient\(to top,rgba\(2,3,9,\.38\)/);
    assert.match(css, /min-height:0!important/);
});

test('controls fade quickly and leave the video unobstructed on pointer exit', () => {
    assert.match(js, /const hideSeconds = \[0, 1, 2\]/);
    assert.match(js, /root\.addEventListener\('pointerleave', \(\) => \{/);
    assert.match(js, /root\.classList\.add\('is-idle'\)/);
    assert.match(css, /transition:opacity \.14s ease,transform \.14s ease/);
    assert.match(css, /gm-modern-center-play\{\s*opacity:0!important/);
});

test('quality is derived from actual manifest levels, never fabricated', () => {
    assert.match(js, /hls\.levels\.forEach/);
    assert.match(js, /height >= 2160 \? '4K · 2160p'/);
    assert.match(js, /qualityContainer\.hidden = hls\.levels\.length < 2/);
    assert.match(js, /video\.dispatchEvent\(new CustomEvent\('gomovie:subtitles-change'/);
    assert.match(js, /instance\.subtitleTrack = index/);
    assert.match(player, /data-captions-wrap hidden/);
    assert.match(player, /<select data-captions-toggle/);
});

test('Upload Studio has optional WebVTT CRUD without adding mandatory upload steps', () => {
    assert.match(studio, /data-panel="1"/);
    assert.match(studio, /id="gm-caption-manager" hidden/);
    assert.match(studio, /id="gm-caption-upload"/);
    assert.match(studio, /id="gm-caption-list"/);
    assert.match(studio, /id="gm-delete-private-upload" hidden/);
    assert.match(studio, /function refreshCaptions\(\)/);
    assert.match(studio, /captions\/' \+ item\.id/);
});
