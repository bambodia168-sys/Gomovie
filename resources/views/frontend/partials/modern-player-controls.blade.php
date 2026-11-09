{{-- Shared controls for the existing secure player and private administrator preview.
     Only the video URL and existing server permissions determine playback access. --}}
<div class="gm-modern-ui" data-gm-controls data-gomovie-player-tools>
  <button class="gm-modern-center-play" type="button" data-gm-toggle aria-label="Play video" title="Play or pause">
    <svg class="gm-icon-play" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 4.7c0-.8.9-1.3 1.6-.9l11 7.2c.7.4.7 1.4 0 1.8l-11 7.2c-.7.4-1.6-.1-1.6-.9V4.7Z"/></svg>
    <svg class="gm-icon-pause" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="4" width="4.5" height="16" rx="1.3"/><rect x="13.5" y="4" width="4.5" height="16" rx="1.3"/></svg>
  </button>
  <div class="gm-modern-bottom">
    <div class="gm-modern-timeline-wrap">
      <input type="range" class="gm-modern-timeline" data-gm-timeline
             min="0" max="100" step="0.1" value="0"
             aria-label="Seek through video" aria-valuetext="0 seconds" style="--gm-played:0%;--gm-buffered:0%">
    </div>
    <div class="gm-modern-toolbar">
      <div class="gm-modern-group">
        <button type="button" class="gm-modern-btn gm-modern-play" data-gm-toggle title="Play or pause" aria-label="Play video">
          <svg class="gm-icon-play" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 4.7c0-.8.9-1.3 1.6-.9l11 7.2c.7.4.7 1.4 0 1.8l-11 7.2c-.7.4-1.6-.1-1.6-.9V4.7Z"/></svg>
          <svg class="gm-icon-pause" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="4" width="4.5" height="16" rx="1.3"/><rect x="13.5" y="4" width="4.5" height="16" rx="1.3"/></svg>
        </button>
        <button type="button" class="gm-modern-btn gm-modern-skip" data-gm-skip="-1" title="Rewind {{ $playerPreferences['seek_seconds'] }} seconds" aria-label="Rewind {{ $playerPreferences['seek_seconds'] }} seconds">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 11a8 8 0 1 1 2.4 5.7M4 5v6h6"/></svg>
          <span aria-hidden="true">{{ $playerPreferences['seek_seconds'] }}</span>
        </button>
        <button type="button" class="gm-modern-btn gm-modern-skip" data-gm-skip="1" title="Forward {{ $playerPreferences['seek_seconds'] }} seconds" aria-label="Forward {{ $playerPreferences['seek_seconds'] }} seconds">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2.4 5.7M20 5v6h-6"/></svg>
          <span aria-hidden="true">{{ $playerPreferences['seek_seconds'] }}</span>
        </button>
        <span class="gm-modern-time" aria-live="off"><span data-gm-current>0:00</span><span class="gm-modern-time-sep">/</span><span data-gm-duration>0:00</span></span>
      </div>
      <div class="gm-modern-group gm-modern-trailing">
        @if($playerPreferences['show_volume'])
          <button type="button" class="gm-modern-btn gm-modern-volume-icon" data-gm-mute aria-label="Mute audio" title="Mute or unmute">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4V5ZM15 9a4 4 0 0 1 0 6M18 6a8 8 0 0 1 0 12"/></svg>
          </button>
          <input class="gm-modern-volume" type="range" min="0" max="1" step="0.05" value="1"
                 data-gm-volume aria-label="Video volume">
        @endif
        @if($playerPreferences['show_captions'])
          <button type="button" class="gm-modern-btn gm-modern-cc" data-captions-toggle
                  aria-label="Turn captions on or off" aria-pressed="false" title="Captions when provided">CC</button>
        @endif
        @if($playerPreferences['show_pip'])
          <button type="button" class="gm-modern-btn gm-modern-pip" data-picture-in-picture aria-label="Picture in picture" title="Picture in picture">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="4" width="19" height="16" rx="2"/><rect x="11.5" y="11" width="8" height="6" rx="1"/></svg>
          </button>
        @endif
        <div class="gm-modern-settings-wrap">
          <button type="button" class="gm-modern-btn" data-gm-settings-toggle
                  aria-controls="gm-modern-settings-{{ $type }}-{{ $selectedEpisode?->id ?? $title->id }}"
                  aria-expanded="false" aria-label="Player settings" title="Player settings">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="m19.4 15 .2.3 1.3 1.1-1.5 2.6-1.7-.6a8.3 8.3 0 0 1-1.9 1.1l-.3 1.8h-3l-.3-1.8a8.3 8.3 0 0 1-1.9-1.1l-1.7.6-1.5-2.6 1.3-1.1c-.2-.7-.2-1.4 0-2.1l-1.3-1.1 1.5-2.6 1.7.6a8.3 8.3 0 0 1 1.9-1.1l.3-1.8h3l.3 1.8a8.3 8.3 0 0 1 1.9 1.1l1.7-.6 1.5 2.6-1.3 1.1c.2.7.2 1.4 0 2.1Z" transform="translate(-1.8 -1.8) scale(1.15)"/></svg>
          </button>
          <div class="gm-modern-settings" data-gm-settings
               id="gm-modern-settings-{{ $type }}-{{ $selectedEpisode?->id ?? $title->id }}" hidden>
            <h3>Playback settings</h3>
            @if($playerPreferences['show_speed'])
              <label>Speed
                <select data-playback-speed aria-label="Playback speed">
                  <option value="0.5">0.5×</option>
                  <option value="0.75">0.75×</option>
                  <option value="1" selected>Normal · 1×</option>
                  <option value="1.25">1.25×</option>
                  <option value="1.5">1.5×</option>
                  <option value="1.75">1.75×</option>
                  <option value="2">2×</option>
                </select>
              </label>
            @endif
            @if($secure)
              <label data-quality-container hidden>Quality
                <select data-playback-quality aria-label="Stream quality">
                  <option value="-1">Auto</option>
                </select>
              </label>
            @endif
            @unless($playerPreferences['show_speed'] || $secure)
              <p>Standard playback controls enabled.</p>
            @endunless
          </div>
        </div>
        @if($playerPreferences['show_fullscreen'])
          <button type="button" class="gm-modern-btn" data-fullscreen-player title="Fullscreen" aria-label="Toggle fullscreen">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3H3v5m13-5h5v5M3 16v5h5m13-5v5h-5"/></svg>
          </button>
        @endif
      </div>
    </div>
  </div>
  <p class="gm-modern-message" data-gm-feedback role="status" aria-live="polite" hidden></p>
</div>
