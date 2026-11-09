{{-- Gomovie's shared video UI; source permissions and entitlements remain server-controlled. --}}
<div class="gm-modern-ui" data-gm-controls data-gomovie-player-tools>
  <button class="gm-modern-center-play" type="button" data-gm-toggle aria-label="Play video" title="Play or pause">
    <span class="gm-center-ring" aria-hidden="true"></span>
    <svg class="gm-icon-play" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 4.7c0-.8.9-1.3 1.6-.9l11 7.2c.7.4.7 1.4 0 1.8l-11 7.2c-.7.4-1.6-.1-1.6-.9V4.7Z"/></svg>
    <svg class="gm-icon-pause" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="4" width="4.5" height="16" rx="1.3"/><rect x="13.5" y="4" width="4.5" height="16" rx="1.3"/></svg>
    <span class="gm-center-spinner" role="status" aria-label="Loading video" hidden></span>
  </button>

  <div class="gm-modern-bottom">
    <div class="gm-modern-timeline-wrap">
      <input type="range" class="gm-modern-timeline" data-gm-timeline min="0" max="100"
             step="0.1" value="0" aria-label="Seek through video" aria-valuetext="0 seconds"
             style="--gm-played:0%;--gm-buffered:0%">
    </div>
    <div class="gm-modern-toolbar">
      <div class="gm-modern-group">
        <button type="button" class="gm-modern-btn gm-modern-play" data-gm-toggle title="Play or pause" aria-label="Play video">
          <svg class="gm-icon-play" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 4.7c0-.8.9-1.3 1.6-.9l11 7.2c.7.4.7 1.4 0 1.8l-11 7.2c-.7.4-1.6-.1-1.6-.9V4.7Z"/></svg>
          <svg class="gm-icon-pause" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="4" width="4.5" height="16" rx="1.3"/><rect x="13.5" y="4" width="4.5" height="16" rx="1.3"/></svg>
        </button>
        <button type="button" class="gm-modern-btn gm-modern-skip" data-gm-skip="-1" title="Rewind {{ $playerPreferences['seek_seconds'] }} seconds" aria-label="Rewind {{ $playerPreferences['seek_seconds'] }} seconds">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 11a8 8 0 1 1 2.4 5.7M4 5v6h6"/></svg><span aria-hidden="true">{{ $playerPreferences['seek_seconds'] }}</span>
        </button>
        <button type="button" class="gm-modern-btn gm-modern-skip" data-gm-skip="1" title="Forward {{ $playerPreferences['seek_seconds'] }} seconds" aria-label="Forward {{ $playerPreferences['seek_seconds'] }} seconds">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2.4 5.7M20 5v6h-6"/></svg><span aria-hidden="true">{{ $playerPreferences['seek_seconds'] }}</span>
        </button>
        <span class="gm-modern-time" aria-live="off"><span data-gm-current>0:00</span><span class="gm-modern-time-sep">/</span><span data-gm-duration>0:00</span></span>
      </div>
      <div class="gm-modern-group gm-modern-trailing">
        @if($playerPreferences['show_volume'])
          <button type="button" class="gm-modern-btn gm-modern-volume-icon" data-gm-mute aria-label="Mute audio" title="Mute or unmute">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4V5ZM15 9a4 4 0 0 1 0 6M18 6a8 8 0 0 1 0 12"/></svg>
          </button>
        @endif

        <div class="gm-modern-settings-wrap">
          <button type="button" class="gm-modern-btn gm-modern-settings-button" data-gm-settings-toggle
                  aria-controls="gm-modern-settings-{{ $type }}-{{ $selectedEpisode?->id ?? $title->id }}"
                  aria-expanded="false" aria-label="Player settings" title="Player settings">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <circle cx="12" cy="12" r="3.05"/>
              <path d="M9.9 2.75h4.2l.56 2.25a7.2 7.2 0 0 1 1.57.92l2.21-.65 2.1 3.62-1.65 1.61a7.8 7.8 0 0 1 0 1.82l1.65 1.61-2.1 3.62-2.21-.65c-.48.39-1 .69-1.57.92l-.56 2.25H9.9l-.56-2.25a7.2 7.2 0 0 1-1.57-.92l-2.21.65-2.1-3.62 1.65-1.61a7.8 7.8 0 0 1 0-1.82L3.46 8.89l2.1-3.62 2.21.65a7.2 7.2 0 0 1 1.57-.92l.56-2.25Z" transform="translate(0 0.55) scale(1 .97)"/>
            </svg>
          </button>
          <div class="gm-modern-settings gm-settings-premium" data-gm-settings
               id="gm-modern-settings-{{ $type }}-{{ $selectedEpisode?->id ?? $title->id }}" hidden>
            <div class="gm-settings-heading">
              <strong>Player settings</strong>
              <button type="button" data-gm-settings-close aria-label="Close settings" title="Close settings">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
              </button>
            </div>
            <div class="gm-settings-group" aria-label="View settings">
              <span class="gm-settings-section-title">View</span>
              <label class="gm-settings-row">Display mode
                <select data-gm-view-mode aria-label="Player aspect ratio">
                  <option value="auto">Auto · Match video</option>
                  <option value="widescreen">16:9 Widescreen</option>
                  <option value="portrait">9:16 Portrait</option>
                </select>
              </label>
              <small data-gm-view-hint>Detecting video orientation…</small>
            </div>
            <div class="gm-settings-group" aria-label="Playback settings">
              <span class="gm-settings-section-title">Playback</span>
              @if($playerPreferences['show_speed'])
                <label class="gm-settings-row">Speed
                  <select data-playback-speed aria-label="Playback speed">
                    <option value="0.5">0.5×</option><option value="0.75">0.75×</option>
                    <option value="1" selected>Normal · 1×</option><option value="1.25">1.25×</option>
                    <option value="1.5">1.5×</option><option value="1.75">1.75×</option>
                    <option value="2">2×</option>
                  </select>
                </label>
              @endif
              @if($secure)
                <label class="gm-settings-row" data-quality-container hidden>Quality
                  <select data-playback-quality aria-label="Stream quality"><option value="-1">Auto</option></select>
                </label>
              @endif
              @if($playerPreferences['show_captions'])
                <button type="button" class="gm-settings-action" data-captions-toggle
                        aria-label="Toggle captions" aria-pressed="false" disabled hidden>Captions · Off</button>
              @endif
            </div>
            @if($playerPreferences['show_volume'])
              <div class="gm-settings-group gm-settings-audio" aria-label="Audio settings">
                <span class="gm-settings-section-title">Audio</span>
                <div class="gm-volume-vertical-wrap">
                  <span>Volume</span>
                  <div class="gm-volume-vertical-track">
                    <input type="range" min="0" max="1" step="0.05" value="1"
                           data-gm-menu-volume aria-label="Player volume" aria-orientation="vertical">
                  </div>
                  <output data-gm-volume-percent>100%</output>
                </div>
              </div>
            @endif
            @if($playerPreferences['show_pip'])
              <div class="gm-settings-group gm-settings-device">
                <span class="gm-settings-section-title">Device</span>
                <button type="button" class="gm-settings-action gm-modern-menu-pip" data-gm-menu-pip hidden disabled>
                  Picture-in-picture
                </button>
              </div>
            @endif
          </div>
        </div>
        @if($playerPreferences['show_fullscreen'])
          <button type="button" class="gm-modern-btn gm-modern-fullscreen-btn" data-fullscreen-player
                  title="Fullscreen" aria-label="Toggle fullscreen">
            <svg class="gm-icon-expand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.95" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M8 3H5a2 2 0 0 0-2 2v3M16 3h3a2 2 0 0 1 2 2v3M3 16v3a2 2 0 0 0 2 2h3M21 16v3a2 2 0 0 1-2 2h-3"/>
            </svg>
            <svg class="gm-icon-collapse" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.95" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M3 9h4a2 2 0 0 0 2-2V3M21 9h-4a2 2 0 0 1-2-2V3M3 15h4a2 2 0 0 1 2 2v4M21 15h-4a2 2 0 0 0-2 2v4"/>
            </svg>
          </button>
        @endif
      </div>
    </div>
  </div>
  {{-- Inactive by default. An approved ad adapter must drive these states from
       actual ad playback events; UI alone never requests or activates adverts. --}}
  <div class="gm-player-ad-layer" data-gm-ad-layer hidden aria-live="polite" aria-label="Advertisement playback">
    <div class="gm-player-ad-header"><span data-gm-ad-kind>Advertisement</span><span data-gm-ad-countdown></span></div>
    <div class="gm-player-ad-footer"><span data-gm-ad-message>Advertisement</span>
      <button type="button" data-gm-ad-skip disabled hidden>Skip ad</button>
    </div>
  </div>
  <p class="gm-modern-message" data-gm-feedback role="status" aria-live="polite" hidden></p>
</div>
