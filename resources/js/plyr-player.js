import Plyr from 'plyr';
import 'plyr/css';
import '../../node_modules/plyr/LICENSE.md?url&no-inline';
import iconUrl from '../../node_modules/plyr/dist/plyr.svg?url&no-inline';
import '../css/plyr-player.css';

export function mountPlyr(root, video, ui, stage, togglePlay) {
    const controls = ['play-large', 'play', 'rewind', 'fast-forward', 'progress', 'current-time'];
    if (ui.querySelector('[data-gm-menu-volume]')) controls.push('mute', 'volume');
    const showCaptions = Boolean(ui.querySelector('[data-captions-toggle]'));
    const showSpeed = Boolean(ui.querySelector('[data-playback-speed]'));
    if (showCaptions) controls.push('captions');
    if (showSpeed || showCaptions) controls.push('settings');
    if (ui.querySelector('[data-gm-menu-pip]')) controls.push('pip');
    const fullscreen = Boolean(ui.querySelector('[data-fullscreen-player]'));
    if (fullscreen) controls.push('fullscreen');

    const seekTime = Number(root.dataset.seekSeconds);
    const player = new Plyr(video, {
        ads: {enabled: false},
        iconUrl,
        controls,
        settings: [showCaptions && 'captions', showSpeed && 'speed'].filter(Boolean),
        speed: {options: [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2]},
        captions: {update: showCaptions, active: Boolean(video.querySelector('track[default]'))},
        seekTime,
        // The watch container applies the configured inactivity timer to both engines.
        hideControls: false,
        clickToPlay: false,
        keyboard: {focused: false, global: false},
        fullscreen: {enabled: fullscreen, container: '.gm-player-stage', iosNative: false},
        listeners: {
            play: () => {
                void togglePlay();
                return false;
            },
        },
    });

    if (!player.elements.controls) return;
    const options = document.querySelector('[data-gm-plyr-options]');
    if (options) {
        const displayMode = ui.querySelector('[data-gm-view-mode]')?.closest('label');
        const quality = ui.querySelector('[data-quality-container]');
        if (displayMode) options.append(displayMode);
        if (quality) options.append(quality);
        options.hidden = false;
    }

    ui.classList.add('gm-plyr-fallback');
    root.classList.add('is-plyr-ready');
    player.on('enterfullscreen', () => stage?.classList.add('gm-plyr-fullscreen'));
    player.on('exitfullscreen', () => stage?.classList.remove('gm-plyr-fullscreen'));
}
